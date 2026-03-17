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

namespace App\Services\Workflow\Actions;

use App\Factory\PaymentFactory;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\WorkflowRun;
use App\Repositories\PaymentRepository;
use App\Services\Workflow\ContextResolver;
use App\Services\Workflow\OperationFailureType;
use App\Services\Workflow\WorkflowOperationException;

class ApplyPaymentAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $invoice = ContextResolver::resolveEntity($params['invoice_ref'] ?? '$trigger', $context, $run);
        if (! $invoice || ! $invoice instanceof Invoice) {
            throw new WorkflowOperationException(
                'Cannot resolve invoice for payment',
                OperationFailureType::PERMANENT
            );
        }

        $amount = $params['amount'] ?? 'full';
        if ($amount === 'full') {
            $amount = $invoice->balance;
        }

        $amount = (float) $amount;
        if ($amount <= 0) {
            throw new WorkflowOperationException(
                'Payment amount must be greater than zero',
                OperationFailureType::GUARD_FAILED
            );
        }

        $payment = PaymentFactory::create($company->id, $run->user_id, $invoice->client_id);
        $payment->amount = $amount;
        $payment->applied = $amount;
        $payment->number = $params['reference'] ?? '';
        $payment->status_id = 4; // completed

        $paymentRepo = new PaymentRepository();
        $paymentRepo->save([
            'invoices' => [
                ['invoice_id' => $invoice->hashed_id, 'amount' => $amount],
            ],
        ], $payment);

        return [
            'action' => 'apply_payment',
            'entity_type' => 'payment',
            'entity_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
        ];
    }
}
