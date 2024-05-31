<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Observers;

use App\Jobs\Util\WebhookHandler;
use App\Models\Invoice;
use App\Models\Webhook;

class InvoiceObserver
{
    public $afterCommit = true;

    /**
     * Handle the client "created" event.
     */
    public function created(Invoice $invoice): void
    {
        $subscriptions = Webhook::where('company_id', $invoice->company_id)
            ->where('event_id', Webhook::EVENT_CREATE_INVOICE)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_INVOICE, $invoice, $invoice->company, 'client')->delay(0);
        }
    }

    /**
     * Handle the client "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        $event = Webhook::EVENT_UPDATE_INVOICE;

        if ($invoice->getOriginal('deleted_at') && ! $invoice->deleted_at) {
            $event = Webhook::EVENT_RESTORE_INVOICE;
        }

        if ($invoice->is_deleted) {
            $event = Webhook::EVENT_DELETE_INVOICE;
        }

        $subscriptions = Webhook::where('company_id', $invoice->company->id)
            ->where('event_id', $event)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $invoice, $invoice->company, 'client')->delay(0);
        }
    }

    /**
     * Handle the client "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        if ($invoice->is_deleted) {
            return;
        }

        $subscriptions = Webhook::where('company_id', $invoice->company_id)
            ->where('event_id', Webhook::EVENT_ARCHIVE_INVOICE)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_INVOICE, $invoice, $invoice->company, 'client')->delay(0);
        }
    }

    /**
     * Handle the client "restored" event.
     */
    public function restored(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the client "force deleted" event.
     */
    public function forceDeleted(Invoice $invoice): void
    {
        //
    }
}
