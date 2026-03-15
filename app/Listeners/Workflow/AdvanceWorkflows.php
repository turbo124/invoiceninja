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
use App\Events\Expense\ExpenseWasCreated;
use App\Events\Expense\ExpenseWasUpdated;
use App\Events\Invoice\InvoiceWasCancelled;
use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Invoice\InvoiceWasReversed;
use App\Events\Invoice\InvoiceWasViewed;
use App\Events\Payment\PaymentCompleted;
use App\Events\Payment\PaymentFailed;
use App\Events\Payment\PaymentWasRefunded;
use App\Events\Quote\QuoteWasApproved;
use App\Events\Quote\QuoteWasCreated;
use App\Events\Quote\QuoteWasEmailed;
use App\Jobs\Workflow\ProcessWorkflowEvent;

class AdvanceWorkflows
{
    /**
     * Handle workflow-relevant events by dispatching a queued job.
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

        ProcessWorkflowEvent::dispatch($entityType, $eventName, $entity, $company)
            ->delay(now()->addSeconds(2));
    }

    private function mapEvent($event): ?array
    {
        return match (true) {
            $event instanceof InvoiceWasCreated => ['invoice', 'created', $event->invoice, $event->company],
            $event instanceof InvoiceWasEmailed => ['invoice', 'sent', $event->invitation->invoice, $event->company],
            $event instanceof InvoiceWasPaid => ['invoice', 'paid', $event->invoice, $event->company],
            $event instanceof InvoiceWasViewed => ['invoice', 'viewed', $event->invitation->invoice, $event->company],
            $event instanceof InvoiceWasCancelled => ['invoice', 'cancelled', $event->invoice, $event->company],
            $event instanceof InvoiceWasReversed => ['invoice', 'reversed', $event->invoice, $event->company],
            $event instanceof QuoteWasCreated => ['quote', 'created', $event->quote, $event->company],
            $event instanceof QuoteWasEmailed => ['quote', 'sent', $event->invitation->quote, $event->company],
            $event instanceof QuoteWasApproved => ['quote', 'approved', $event->quote, $event->company],
            $event instanceof ClientWasCreated => ['client', 'created', $event->client, $event->company],
            $event instanceof ClientWasUpdated => ['client', 'updated', $event->client, $event->company],
            $event instanceof PaymentCompleted => ['payment', 'completed', $event->payment, $event->company],
            $event instanceof PaymentFailed => ['payment', 'failed', $event->payment, $event->company],
            $event instanceof PaymentWasRefunded => ['payment', 'refunded', $event->payment, $event->company],
            $event instanceof ExpenseWasCreated => ['expense', 'created', $event->expense, $event->company],
            $event instanceof ExpenseWasUpdated => ['expense', 'updated', $event->expense, $event->company],
            default => null,
        };
    }
}
