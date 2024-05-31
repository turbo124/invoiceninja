<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\SystemLog;
use App\PaymentDrivers\PayPal\PayPalBasePaymentDriver;
use App\Utils\Traits\MakesHash;
use Str;

class PayPalPPCPPaymentDriver extends PayPalBasePaymentDriver
{
    use MakesHash;

    ///v1/customer/partners/merchant-accounts/{merchant_id}/capabilities - test if advanced cards is available.
    //     {
    //     "capabilities": [
    //         {
    //             "name": "ADVANCED_CARD_PAYMENTS",
    //             "status": "ENABLED"
    //         },
    //         {
    //             "name": "VAULTING",
    //             "status": "ENABLED"
    //         }
    //     ]
    // }

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYPAL_PPCP;

    /**
     * Checks whether payments are enabled on the merchant account
     */
    private function checkPaymentsReceivable(): self
    {

        if ($this->company_gateway->getConfigField('status') != 'activated') {

            if (class_exists(\Modules\Admin\Services\PayPal\PayPalService::class)) {
                $pp = new \Modules\Admin\Services\PayPal\PayPalService($this->company_gateway->company, $this->company_gateway->user);
                $pp->updateMerchantStatus($this->company_gateway);

                $this->company_gateway = $this->company_gateway->fresh();
                $config = $this->company_gateway->getConfig();

                if ($config->status == 'activated') {
                    return $this;
                }

            }

            throw new PaymentFailed('Unable to accept payments at this time, please contact PayPal for more information.', 401);
        }

        return $this;

    }

    /**
     * Presents the Payment View to the client
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function processPaymentView($data)
    {
        $this->init()->checkPaymentsReceivable();

        $data['gateway'] = $this;
        $this->payment_hash->data = array_merge((array) $this->payment_hash->data, ['amount' => $data['total']['amount_with_fee']]);
        $this->payment_hash->save();

        $data['client_id'] = config('ninja.paypal.client_id');
        $data['token'] = $this->getClientToken();
        $data['order_id'] = $this->createOrder($data);
        $data['funding_source'] = $this->paypal_payment_method;
        $data['gateway_type_id'] = $this->gateway_type_id;
        $data['merchantId'] = $this->company_gateway->getConfigField('merchantId');
        $data['currency'] = $this->client->currency()->code;

        if ($this->gateway_type_id == 29) {
            return render('gateways.paypal.ppcp.card', $data);
        } else {
            return render('gateways.paypal.ppcp.pay', $data);
        }

    }

    /**
     * Processes the payment response
     *
     * @param  mixed  $request
     * @return void
     */
    public function processPaymentResponse($request)
    {

        $request['gateway_response'] = str_replace('Error: ', '', $request['gateway_response']);
        $response = json_decode($request['gateway_response'], true);

        if ($request->has('token') && strlen($request->input('token')) > 2) {
            return $this->processTokenPayment($request, $response);
        }

        //capture
        $orderID = $response['orderID'];

        if ($this->company_gateway->require_shipping_address) {

            $shipping_data =
            [[
                'op' => 'replace',
                'path' => "/purchase_units/@reference_id=='default'/shipping/address",
                'value' => [
                    'address_line_1' => strlen($this->client->shipping_address1 ?? '') > 1 ? $this->client->shipping_address1 : $this->client->address1,
                    'address_line_2' => $this->client->shipping_address2,
                    'admin_area_2' => strlen($this->client->shipping_city ?? '') > 1 ? $this->client->shipping_city : $this->client->city,
                    'admin_area_1' => strlen($this->client->shipping_state ?? '') > 1 ? $this->client->shipping_state : $this->client->state,
                    'postal_code' => strlen($this->client->shipping_postal_code ?? '') > 1 ? $this->client->shipping_postal_code : $this->client->postal_code,
                    'country_code' => $this->client->present()->shipping_country_code(),
                ],
            ]];

            $r = $this->gatewayRequest("/v2/checkout/orders/{$orderID}", 'patch', $shipping_data);

        }

        try {
            $r = $this->gatewayRequest("/v2/checkout/orders/{$orderID}/capture", 'post', ['body' => '']);

            if ($r->status() == 422) {
                //handle conditions where the client may need to try again.
                return $this->handleRetry($r, $request);
            }

        } catch (\Exception $e) {

            //Rescue for duplicate invoice_id
            if (stripos($e->getMessage(), 'DUPLICATE_INVOICE_ID') !== false) {

                $_invoice = collect($this->payment_hash->data->invoices)->first();
                $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));
                $new_invoice_number = $invoice->number.'_'.Str::random(5);

                $update_data =
                        [[
                            'op' => 'replace',
                            'path' => "/purchase_units/@reference_id=='default'/invoice_id",
                            'value' => $new_invoice_number,
                        ]];

                $r = $this->gatewayRequest("/v2/checkout/orders/{$orderID}", 'patch', $update_data);

                $r = $this->gatewayRequest("/v2/checkout/orders/{$orderID}/capture", 'post', ['body' => '']);

            }

        }

        $response = $r;

        if (isset($response['status']) && $response['status'] == 'COMPLETED' && isset($response['purchase_units'])) {

            $data = [
                'payment_type' => $this->getPaymentMethod($request->gateway_type_id),
                'amount' => $response['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                'transaction_reference' => $response['purchase_units'][0]['payments']['captures'][0]['id'],
                'gateway_type_id' => GatewayType::PAYPAL,
            ];

            $payment = $this->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_PAYPAL_PPCP,
                $this->client,
                $this->client->company,
            );

            return response()->json(['redirect' => route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)], false)]);

            // return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);

        } else {

            if (isset($response['headers']) ?? false) {
                unset($response['headers']);
            }

            SystemLogger::dispatch(
                ['response' => $response],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_PAYPAL_PPCP,
                $this->client,
                $this->client->company,
            );

            $message = $response['body']['details'][0]['description'] ?? 'Payment failed. Please try again.';

            return response()->json(['message' => $message], 400);

            // throw new PaymentFailed($message, 400);
        }
    }

    public function getOrder(string $order_id)
    {
        $this->init();

        $r = $this->gatewayRequest("/v2/checkout/orders/{$order_id}", 'get', ['body' => '']);

        return $r->json();
    }

    /**
     * Creates the PayPal Order object
     */
    public function createOrder(array $data): string
    {

        $_invoice = collect($this->payment_hash->data->invoices)->first();

        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));

        $description = collect($invoice->line_items)->map(function ($item) {
            return $item->notes;
        })->implode("\n");

        $order = [

            'intent' => 'CAPTURE',
            'payment_source' => $this->getPaymentSource(),
            'purchase_units' => [
                [
                    'custom_id' => $this->payment_hash->hash,
                    'description' => ctrans('texts.invoice_number').'# '.$invoice->number,
                    'invoice_id' => $invoice->number,
                    'payee' => [
                        'merchant_id' => $this->company_gateway->getConfigField('merchantId'),
                    ],
                    'payment_instruction' => [
                        'disbursement_mode' => 'INSTANT',
                    ],
                    'amount' => [
                        'value' => (string) $data['amount_with_fee'],
                        'currency_code' => $this->client->currency()->code,
                        'breakdown' => [
                            'item_total' => [
                                'currency_code' => $this->client->currency()->code,
                                'value' => (string) $data['amount_with_fee'],
                            ],
                        ],
                    ],
                    'items' => [
                        [
                            'name' => ctrans('texts.invoice_number').'# '.$invoice->number,
                            'description' => mb_substr($description, 0, 127),
                            'quantity' => '1',
                            'unit_amount' => [
                                'currency_code' => $this->client->currency()->code,
                                'value' => (string) $data['amount_with_fee'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($shipping = $this->getShippingAddress()) {
            $order['purchase_units'][0]['shipping'] = $shipping;
        }

        if (isset($data['payment_source'])) {
            $order['payment_source'] = $data['payment_source'];
        }

        $r = $this->gatewayRequest('/v2/checkout/orders', 'post', $order);

        return $r->json()['id'];

    }

    /**
     * processTokenPayment
     *
     * With PayPal and token payments, the order needs to be
     * deleted and then created with the payment source that
     * has been selected by the client.
     *
     * This method handle the deletion of the current paypal order,
     * and the automatic payment of the order with the selected payment source.
     *
     * @param  mixed  $request
     * @return void
     */
    public function processTokenPayment($request, array $response)
    {

        $cgt = ClientGatewayToken::where('client_id', $this->client->id)
            ->where('token', $request['token'])
            ->firstOrFail();

        $orderId = $response['orderID'];
        $r = $this->gatewayRequest("/v1/checkout/orders/{$orderId}/", 'delete', ['body' => '']);

        $data['amount_with_fee'] = $this->payment_hash->data->amount_with_fee;
        $data['payment_source'] = [
            'card' => [
                'vault_id' => $cgt->token,
                'stored_credential' => [
                    'payment_initiator' => 'MERCHANT',
                    'payment_type' => 'UNSCHEDULED',
                    'usage' => 'SUBSEQUENT',
                ],
            ],
        ];

        $orderId = $this->createOrder($data);

        $r = $this->gatewayRequest("/v2/checkout/orders/{$orderId}", 'get', ['body' => '']);

        $response = $r->json();

        $data = [
            'payment_type' => $this->getPaymentMethod($request->gateway_type_id),
            'amount' => $response['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
            'transaction_reference' => $response['purchase_units'][0]['payments']['captures'][0]['id'],
            'gateway_type_id' => $this->gateway_type_id,
        ];

        $payment = $this->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_PAYPAL_PPCP,
            $this->client,
            $this->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);

    }
}
