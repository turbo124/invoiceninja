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

namespace App\Listeners\Workflow;

use App\Events\Client\ClientWasCreated;
use App\Events\Client\ClientWasUpdated;
use App\Events\Credit\CreditWasCreated;
use App\Events\Credit\CreditWasEmailed;
use App\Events\Expense\ExpenseWasCreated;
use App\Events\Expense\ExpenseWasUpdated;
use App\Events\Invoice\InvoiceWasCancelled;
use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Invoice\InvoiceWasReversed;
use App\Events\Invoice\InvoiceWasViewed;
use App\Events\Payment\PaymentWasCreated;
use App\Events\Payment\PaymentWasRefunded;
use App\Events\PurchaseOrder\PurchaseOrderWasCreated;
use App\Events\PurchaseOrder\PurchaseOrderWasEmailed;
use App\Events\Quote\QuoteWasApproved;
use App\Events\Quote\QuoteWasCreated;
use App\Events\Quote\QuoteWasEmailed;
use App\Events\Quote\QuoteWasRejected;
use App\Events\Task\TaskWasCreated;
use App\Events\Task\TaskWasUpdated;
use App\Events\Vendor\VendorWasCreated;
use App\Jobs\Workflow\ProcessWorkflowEvent;
use App\Models\Workflow;
use App\Models\WorkflowRun;

class AdvanceWorkflows
{
    /**
     * Handle workflow-relevant events by dispatching a queued job.
     *
     * Two checks:
     * 1. Type-level: does any active workflow trigger on this entity+event? → start new runs
     * 2. Instance-level: does this entity have waiting workflow runs? → resume them
     */
    public function handle($event): void
    {
        $mapping = $this->mapEvent($event);

        if (! $mapping) {
            return;
        }

        [$entityType, $eventName, $entity, $company] = $mapping;

        if (! $entity || ! $company) {
            return;
        }

        $hasNewTrigger = Workflow::where('company_id', $company->id)
            ->where('trigger_entity', $entityType)
            ->where('trigger_event', $eventName)
            ->where('is_deleted', false)
            ->where('is_template', false)
            ->exists();

        $hasWaitingRuns = $entity->workflowRuns()
            ->where('status', WorkflowRun::STATUS_WAITING)
            ->exists();

        if (! $hasNewTrigger && ! $hasWaitingRuns) {
            return;
        }

        ProcessWorkflowEvent::dispatch($entityType, $eventName, $entity, $company)
            ->delay(now()->addSeconds(2));
    }

    private function mapEvent($event): ?array
    {
        return match (true) {
            // Invoice events
            $event instanceof InvoiceWasCreated => ['invoice', 'created', $event->invoice, $event->company],
            $event instanceof InvoiceWasEmailed => ['invoice', 'sent', $event->invitation->invoice, $event->company],
            $event instanceof InvoiceWasPaid => ['invoice', 'paid', $event->invoice, $event->company],
            $event instanceof InvoiceWasViewed => ['invoice', 'viewed', $event->invitation->invoice, $event->company],
            $event instanceof InvoiceWasCancelled => ['invoice', 'cancelled', $event->invoice, $event->company],
            $event instanceof InvoiceWasReversed => ['invoice', 'reversed', $event->invoice, $event->company],

            // Quote events
            $event instanceof QuoteWasCreated => ['quote', 'created', $event->quote, $event->company],
            $event instanceof QuoteWasEmailed => ['quote', 'sent', $event->invitation->quote, $event->company],
            $event instanceof QuoteWasApproved => ['quote', 'approved', $event->quote, $event->company],
            $event instanceof QuoteWasRejected => ['quote', 'rejected', $event->quote, $event->company],

            // Client events
            $event instanceof ClientWasCreated => ['client', 'created', $event->client, $event->company],
            $event instanceof ClientWasUpdated => ['client', 'updated', $event->client, $event->company],

            // Payment events
            $event instanceof PaymentWasCreated => ['payment', 'completed', $event->payment, $event->company],
            $event instanceof PaymentWasRefunded => ['payment', 'refunded', $event->payment, $event->company],

            // Credit events
            $event instanceof CreditWasCreated => ['credit', 'created', $event->credit, $event->company],
            $event instanceof CreditWasEmailed => ['credit', 'sent', $event->invitation->credit, $event->company],

            // Expense events
            $event instanceof ExpenseWasCreated => ['expense', 'created', $event->expense, $event->company],
            $event instanceof ExpenseWasUpdated => ['expense', 'updated', $event->expense, $event->company],

            // Task events
            $event instanceof TaskWasCreated => ['task', 'created', $event->task, $event->company],
            $event instanceof TaskWasUpdated => ['task', 'updated', $event->task, $event->company],

            // Purchase Order events
            $event instanceof PurchaseOrderWasCreated => ['purchase_order', 'created', $event->purchase_order, $event->company],
            $event instanceof PurchaseOrderWasEmailed => ['purchase_order', 'sent', $event->invitation->purchase_order, $event->company],

            // Vendor events
            $event instanceof VendorWasCreated => ['vendor', 'created', $event->vendor, $event->company],

            default => null,
        };
    }
}
