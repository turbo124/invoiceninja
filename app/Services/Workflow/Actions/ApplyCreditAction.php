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

use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\WorkflowRun;
use App\Services\Workflow\ContextResolver;
use App\Services\Workflow\OperationFailureType;
use App\Services\Workflow\WorkflowOperationException;

class ApplyCreditAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $credit = ContextResolver::resolveEntity($params['credit_ref'] ?? '$credit', $context, $run);
        if (! $credit || ! $credit instanceof Credit) {
            throw new WorkflowOperationException(
                'Cannot resolve credit',
                OperationFailureType::PERMANENT
            );
        }

        $invoice = ContextResolver::resolveEntity($params['invoice_ref'] ?? '$invoice', $context, $run);
        if (! $invoice || ! $invoice instanceof Invoice) {
            throw new WorkflowOperationException(
                'Cannot resolve invoice for credit application',
                OperationFailureType::PERMANENT
            );
        }

        $amount = $params['amount'] ?? 'full';
        if ($amount === 'full') {
            $amount = min($credit->balance, $invoice->balance);
        }

        $amount = (float) $amount;
        if ($amount <= 0) {
            throw new WorkflowOperationException(
                'Credit amount must be greater than zero',
                OperationFailureType::GUARD_FAILED
            );
        }

        $invoice = $invoice->service()->applyCredit($credit, $amount)->save();

        return [
            'action' => 'apply_credit',
            'entity_type' => 'invoice',
            'entity_id' => $invoice->id,
            'credit_id' => $credit->id,
            'amount' => $amount,
        ];
    }
}
