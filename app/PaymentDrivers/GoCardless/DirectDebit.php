<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\GoCardless;

use App\Exceptions\PaymentFailed;
use App\Http\Controllers\ClientPortal\InvoiceController;
use App\Http\Requests\ClientPortal\Invoices\ProcessInvoicesInBulkRequest;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\ClientGatewayToken;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\GoCardlessPaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DirectDebit implements MethodInterface, LivewireMethodInterface
{
    use MakesHash;

    protected GoCardlessPaymentDriver $go_cardless;

    public function __construct(GoCardlessPaymentDriver $go_cardless)
    {
        $this->go_cardless = $go_cardless;

        $this->go_cardless->init();
    }

    public function authorizeView(array $data): \Illuminate\Http\RedirectResponse
    {
        $gateway_type_id = (int) request()->query('method', GatewayType::DIRECT_DEBIT);
        $context_id = $this->createHostedFlowContext($gateway_type_id);
        $authorisation_url = (new HostedPaymentPage($this->go_cardless, $context_id, false))
            ->start($gateway_type_id);

        return redirect()->away($authorisation_url);
    }


    /**
     * Handle unsuccessful authorization.
     *
     * @param \Exception | \Throwable $exception
     * @throws PaymentFailed
     */
    public function processUnsuccessfulAuthorization($exception)
    {
        SystemLogger::dispatch(
            $exception->getMessage(),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_GOCARDLESS,
            $this->go_cardless->client,
            $this->go_cardless->client->company,
        );

        throw new PaymentFailed($exception->getMessage(), $exception->getCode());
    }

    /**
     * Handle authorization response for Direct Debit.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|void
     */
    public function authorizeResponse(Request $request)
    {

        try {

            $billing_request = $this->go_cardless->gateway->billingRequests()->get($request->billing_request);

            $payment_meta = new \stdClass();
            $payment_meta->brand = $billing_request->resources->customer_bank_account->bank_name;
            $payment_meta->type = $this->resolveScheme($billing_request->mandate_request->scheme);
            $payment_meta->state = 'pending';
            $payment_meta->last4 = $billing_request->resources->customer_bank_account->account_number_ending;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $billing_request->mandate_request->links->mandate,
                'payment_method_id' => $this->resolveScheme($billing_request->mandate_request->scheme),
            ];

            $payment_method = $this->go_cardless->storeGatewayToken($data, ['gateway_customer_reference' => $billing_request->resources->customer->id]);

            $mandate = $this->go_cardless->gateway->mandates()->get($billing_request->mandate_request->links->mandate);

            if ($request->has('authorize_then_redirect') && $request->payment_hash !== null) {
                $this->go_cardless->payment_hash = PaymentHash::where('hash', $request->payment_hash)->firstOrFail();

                $data = [
                    'invoices' => collect($this->go_cardless->payment_hash->data->invoices)->map(fn($invoice) => $invoice->invoice_id)->toArray(),
                    'action' => 'payment',
                ];

                $request = new ProcessInvoicesInBulkRequest();
                $request->replace($data);

                session()->flash('message', ctrans('texts.payment_method_added'));

                return app(InvoiceController::class)->bulk($request);
            }

            return redirect()->route('client.payment_methods.show', $payment_method->hashed_id);

        } catch (\Exception $exception) {
            return $this->processUnsuccessfulAuthorization($exception);
        }

    }

    private function resolveScheme(string $scheme): int
    {
        match ($scheme) {
            'sepa_core' => $type = GatewayType::SEPA,
            'ach' => $type = GatewayType::BANK_TRANSFER,
            default => $type = GatewayType::DIRECT_DEBIT,
        };

        return $type;
    }


    /**
     * Payment view for Direct Debit.
     *
     * @param array $data
     */
    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.gocardless.direct_debit.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $gateway_type_id = (int) $request->payment_method_id;
        $amount_with_fee = $this->go_cardless->payment_hash->amount_with_fee();

        if (! $request->source) {
            $authorisation_url = (new HostedPaymentPage($this->go_cardless))
                ->start($gateway_type_id);

            return redirect()->away($authorisation_url);
        }

        $token = $this->go_cardless->resolveClientGatewayToken(
            (string) $request->source,
            $gateway_type_id,
            $amount_with_fee,
        );

        $this->go_cardless->ensureMandateIsReady($token->token);

        $invoice = Invoice::query()->whereIn('id', $this->transformKeys(array_column($this->go_cardless->payment_hash->invoices(), 'invoice_id')))
                          ->withTrashed()
                          ->first();

        if ($invoice) {
            $description = "Invoice {$invoice->number} for {$request->amount} for client {$this->go_cardless->client->present()->name()}";
        } else {
            $description = "Amount {$request->amount} from client {$this->go_cardless->client->present()->name()}";
        }

        $amount = $this->go_cardless->convertToGoCardlessAmount($this->go_cardless->payment_hash?->amount_with_fee(), $this->go_cardless->client->currency()->precision);

        try {
            $payment = $this->go_cardless->gateway->payments()->create([
                'params' => [
                    // 'amount' => $request->amount,
                    'amount' => $amount,
                    'currency' => $request->currency,
                    'description' => $description,
                    'metadata' => [
                        'payment_hash' => $this->go_cardless->payment_hash->hash,
                    ],
                    'links' => [
                        'mandate' => $token->token,
                    ],
                ],
            ]);

            if (in_array($payment->status, ['pending_submission', 'submitted', 'confirmed', 'paid_out'], true)) {
                return $this->processPendingPayment($payment, ['token' => $token->token]);
            }

            return $this->processUnsuccessfulPayment($payment);
        } catch (\Exception $exception) {
            throw new PaymentFailed($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * Handle pending payments for Direct Debit.
     *
     * @param ResourcesPayment $payment
     * @param array $data
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processPendingPayment(\GoCardlessPro\Resources\Payment $payment, array $data = [])
    {
        $token = ClientGatewayToken::query()
            ->where('company_gateway_id', $this->go_cardless->company_gateway->id)
            ->where('client_id', $this->go_cardless->client->id)
            ->where('token', $data['token'] ?? data_get($payment, 'links.mandate'))
            ->first();

        if (! $token) {
            throw new PaymentFailed(ctrans('texts.gateway_temporarily_unavailable'), 403);
        }

        $gateway_type_id = $token->gateway_type_id;

        $data = [
            'payment_method' => $token->hashed_id,
            'payment_type' => HostedPaymentPage::paymentTypeForStoredMandate(
                $gateway_type_id,
                $this->go_cardless->client->getCurrencyCode(),
                data_get($token->meta, 'scheme'),
            ),
            'amount' => $this->go_cardless->payment_hash->data->amount_with_fee,
            'transaction_reference' => $payment->id,
            'gateway_type_id' => $gateway_type_id,
        ];

        $status = in_array($payment->status, ['confirmed', 'paid_out'], true)
            ? Payment::STATUS_COMPLETED
            : Payment::STATUS_PENDING;
        $_payment = $this->go_cardless->createPayment($data, $status);

        SystemLogger::dispatch(
            ['response' => $payment, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_GOCARDLESS,
            $this->go_cardless->client,
            $this->go_cardless->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $_payment->hashed_id]);
    }

    /**
     * Process unsuccessful payments for Direct Debit.
     *
     * @param ResourcesPayment $payment
     * @return never
     */
    public function processUnsuccessfulPayment(\GoCardlessPro\Resources\Payment $payment)
    {
        $this->go_cardless->sendFailureMail("Direct Debit payment failed with status: {$payment->status}");

        $message = [
            'server_response' => $payment,
            'data' => $this->go_cardless->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_GOCARDLESS,
            $this->go_cardless->client,
            $this->go_cardless->client->company,
        );

        throw new PaymentFailed('Failed to process the payment.', 500);
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.gocardless.direct_debit.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->go_cardless;
        $data['amount'] = $this->go_cardless->convertToGoCardlessAmount($data['total']['amount_with_fee'], $this->go_cardless->client->currency()->precision);
        $data['currency'] = $this->go_cardless->client->getCurrencyCode();

        return $data;
    }

    private function createHostedFlowContext(int $gateway_type_id): string
    {
        $key = 'gocardless-' . Str::uuid();
        $contact = auth()->guard('contact')->user()?->fresh();

        Cache::put($key, array_filter([
            'db' => $this->go_cardless->company_gateway->company->db,
            'company_gateway_id' => $this->go_cardless->company_gateway->id,
            'gateway_type_id' => $gateway_type_id,
            'contact' => $contact,
        ]), now()->addDays(HostedPaymentPage::FLOW_EXPIRY_DAYS));

        return $key;
    }

}
