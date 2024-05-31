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
use App\Models\Quote;
use App\Models\Webhook;

class QuoteObserver
{
    public $afterCommit = true;

    /**
     * Handle the quote "created" event.
     */
    public function created(Quote $quote): void
    {
        $subscriptions = Webhook::where('company_id', $quote->company_id)
            ->where('event_id', Webhook::EVENT_CREATE_QUOTE)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_QUOTE, $quote, $quote->company, 'client')->delay(0);
        }
    }

    /**
     * Handle the quote "updated" event.
     */
    public function updated(Quote $quote): void
    {
        $event = Webhook::EVENT_UPDATE_QUOTE;

        if ($quote->getOriginal('deleted_at') && ! $quote->deleted_at) {
            $event = Webhook::EVENT_RESTORE_QUOTE;
        }

        if ($quote->is_deleted) {
            $event = Webhook::EVENT_DELETE_QUOTE;
        }

        $subscriptions = Webhook::where('company_id', $quote->company_id)
            ->where('event_id', $event)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $quote, $quote->company, 'client')->delay(0);
        }
    }

    /**
     * Handle the quote "deleted" event.
     */
    public function deleted(Quote $quote): void
    {
        if ($quote->is_deleted) {
            return;
        }

        $subscriptions = Webhook::where('company_id', $quote->company_id)
            ->where('event_id', Webhook::EVENT_ARCHIVE_QUOTE)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_QUOTE, $quote, $quote->company, 'client')->delay(0);
        }
    }

    /**
     * Handle the quote "restored" event.
     */
    public function restored(Quote $quote): void
    {
        //
    }

    /**
     * Handle the quote "force deleted" event.
     */
    public function forceDeleted(Quote $quote): void
    {
        //
    }
}
