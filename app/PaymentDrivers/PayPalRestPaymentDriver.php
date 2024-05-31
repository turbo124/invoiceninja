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
use Illuminate\Support\Str;

class PayPalRestPaymentDriver extends PayPalBasePaymentDriver
{
    use MakesHash;

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYPAL;

    public function processPaymentView($data)
    {
        $this->init();

        $data['gateway'] = $this;

        $this->payment_hash->data = array_merge((array) $this->payment_hash->data, ['amount' => $data['total']['amount_with_fee']]);
        $this->payment_hash->save();

        $data['client_id'] = $this->company_gateway->getConfigField('clientId');
        $data['token'] = $this->getClientToken();
        $data['order_id'] = $this->createOrder($data);
        $data['funding_source'] = $this->paypal_payment_method;
        $data['gateway_type_id'] = $this->gateway_type_id;
        $data['currency'] = $this->client->currency()->code;

        if ($this->gateway_type_id == 29) {
            return render('gateways.paypal.ppcp.card', $data);
        } else {
            return render('gateways.paypal.pay', $data);
        }

    }

    /**
     * processPaymentResponse
     *
     * @param  mixed  $request
     * @return void
     */
    public function processPaymentResponse($request)
    {

        $this->init();

        $request['gateway_response'] = str_replace('Error: ', '', $request['gateway_response']);
        $response = json_decode($request['gateway_response'], true);

        nlog($response);

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
                    'address_line_1' => strlen($this->client->shipping_address1) > 1 ? $this->client->shipping_address1 : $this->client->address1,
                    'address_line_2' => $this->client->shipping_address2,
                    'admin_area_2' => strlen($this->client->shipping_city) > 1 ? $this->client->shipping_city : $this->client->city,
                    'admin_area_1' => strlen($this->client->shipping_state) > 1 ? $this->client->shipping_state : $this->client->state,
                    'postal_code' => strlen($this->client->shipping_postal_code) > 1 ? $this->client->shipping_postal_code : $this->client->postal_code,
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

        nlog('Process response =>');
        nlog($response->json());

        if (isset($response['status']) && $response['status'] == 'COMPLETED' && isset($response['purchase_units'])) {

            return $this->createNinjaPayment($request, $response);

        } else {

            if (isset($response['headers']) ?? false) {
                unset($response['headers']);
            }

            SystemLogger::dispatch(
                ['response' => $response],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_PAYPAL,
                $this->client,
                $this->client->company,
            );

            $message = $response['body']['details'][0]['description'] ?? 'Payment failed. Please try again.';

            return response()->json(['message' => $message], 400);

            //throw new PaymentFailed($message, 400);
        }

    }

    private function createNinjaPayment($request, $response)
    {

        $data = [
            'payment_type' => $this->getPaymentMethod($request->gateway_type_id),
            'amount' => $response['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
            'transaction_reference' => $response['purchase_units'][0]['payments']['captures'][0]['id'],
            'gateway_type_id' => GatewayType::PAYPAL,
        ];

        $payment = $this->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

        if ($request->has('store_card') && $request->input('store_card') === true) {
            $payment_source = $response->json()['payment_source'];

            if (isset($payment_source['card']) && ($payment_source['card']['attributes']['vault']['status'] ?? false) && $payment_source['card']['attributes']['vault']['status'] == 'VAULTED') {

                $last4 = $payment_source['card']['last_digits'];
                $expiry = $payment_source['card']['expiry']; //'2025-01'
                $expiry_meta = explode('-', $expiry);
                $brand = $payment_source['card']['brand'];

                $payment_meta = new \stdClass();
                $payment_meta->exp_month = $expiry_meta[1] ?? '';
                $payment_meta->exp_year = $expiry_meta[0] ?? $expiry;
                $payment_meta->brand = $brand;
                $payment_meta->last4 = $last4;
                $payment_meta->type = GatewayType::CREDIT_CARD;

                $token = $payment_source['card']['attributes']['vault']['id']; // 09f28652d01257021
                $gateway_customer_reference = $payment_source['card']['attributes']['vault']['customer']['id']; //rbTHnLsZqE;

                $data['token'] = $token;
                $data['payment_method_id'] = GatewayType::PAYPAL_ADVANCED_CARDS;
                $data['payment_meta'] = $payment_meta;

                $additional['gateway_customer_reference'] = $gateway_customer_reference;

                $this->storeGatewayToken($data, $additional);

            }
        }

        SystemLogger::dispatch(
            ['response' => $response->json(), 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_PAYPAL,
            $this->client,
            $this->client->company,
        );

        return response()->json(['redirect' => route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)], false)]);

        // return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);

    }

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

        nlog($r->json());

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
            SystemLog::TYPE_PAYPAL,
            $this->client,
            $this->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);

    }
}
