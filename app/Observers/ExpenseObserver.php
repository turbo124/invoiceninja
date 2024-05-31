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
use App\Models\Expense;
use App\Models\Webhook;

class ExpenseObserver
{
    public $afterCommit = true;

    /**
     * Handle the expense "created" event.
     */
    public function created(Expense $expense): void
    {
        $subscriptions = Webhook::where('company_id', $expense->company_id)
            ->where('event_id', Webhook::EVENT_CREATE_EXPENSE)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_EXPENSE, $expense, $expense->company)->delay(0);
        }
    }

    /**
     * Handle the expense "updated" event.
     */
    public function updated(Expense $expense): void
    {
        $event = Webhook::EVENT_UPDATE_EXPENSE;

        if ($expense->getOriginal('deleted_at') && ! $expense->deleted_at) {
            $event = Webhook::EVENT_RESTORE_EXPENSE;
        }

        if ($expense->is_deleted) {
            $event = Webhook::EVENT_DELETE_EXPENSE;
        }

        $subscriptions = Webhook::where('company_id', $expense->company_id)
            ->where('event_id', $event)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $expense, $expense->company)->delay(0);
        }
    }

    /**
     * Handle the expense "deleted" event.
     */
    public function deleted(Expense $expense): void
    {
        if ($expense->is_deleted) {
            return;
        }

        $subscriptions = Webhook::where('company_id', $expense->company_id)
            ->where('event_id', Webhook::EVENT_ARCHIVE_EXPENSE)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_EXPENSE, $expense, $expense->company)->delay(0);
        }
    }

    /**
     * Handle the expense "restored" event.
     */
    public function restored(Expense $expense): void
    {
        //
    }

    /**
     * Handle the expense "force deleted" event.
     */
    public function forceDeleted(Expense $expense): void
    {
        //
    }
    /**
     * Handle the expense "archive" event.
     *
     * @param  Expense  $expense
     * @return void
     */
}
