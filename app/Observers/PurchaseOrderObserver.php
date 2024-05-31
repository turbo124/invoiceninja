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
use App\Models\PurchaseOrder;
use App\Models\Webhook;

class PurchaseOrderObserver
{
    public $afterCommit = true;

    /**
     * Handle the client "created" event.
     */
    public function created(PurchaseOrder $purchase_order): void
    {
        $subscriptions = Webhook::where('company_id', $purchase_order->company_id)
            ->where('event_id', Webhook::EVENT_CREATE_PURCHASE_ORDER)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_PURCHASE_ORDER, $purchase_order, $purchase_order->company, 'vendor')->delay(0);
        }
    }

    /**
     * Handle the client "updated" event.
     */
    public function updated(PurchaseOrder $purchase_order): void
    {
        $event = Webhook::EVENT_UPDATE_PURCHASE_ORDER;

        if ($purchase_order->getOriginal('deleted_at') && ! $purchase_order->deleted_at) {
            $event = Webhook::EVENT_RESTORE_PURCHASE_ORDER;
        }

        if ($purchase_order->is_deleted) {
            $event = Webhook::EVENT_DELETE_PURCHASE_ORDER;
        }

        $subscriptions = Webhook::where('company_id', $purchase_order->company_id)
            ->where('event_id', $event)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $purchase_order, $purchase_order->company, 'vendor')->delay(0);
        }
    }

    /**
     * Handle the client "deleted" event.
     */
    public function deleted(PurchaseOrder $purchase_order): void
    {
        if ($purchase_order->is_deleted) {
            return;
        }

        $subscriptions = Webhook::where('company_id', $purchase_order->company_id)
            ->where('event_id', Webhook::EVENT_ARCHIVE_PURCHASE_ORDER)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_PURCHASE_ORDER, $purchase_order, $purchase_order->company, 'vendor')->delay(0);
        }
    }

    /**
     * Handle the client "restored" event.
     */
    public function restored(PurchaseOrder $purchase_order): void
    {
        //
    }

    /**
     * Handle the client "force deleted" event.
     */
    public function forceDeleted(PurchaseOrder $purchase_order): void
    {
        //
    }
}
