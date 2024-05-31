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

use App\Models\Subscription;

class SubscriptionObserver
{
    /**
     * Handle the subscription "created" event.
     *
     * @return void
     */
    public function created(Subscription $subscription): void
    {
        //
    }

    /**
     * Handle the subscription "updated" event.
     *
     * @return void
     */
    public function updated(Subscription $subscription): void
    {
        //
    }

    /**
     * Handle the subscription "deleted" event.
     *
     * @return void
     */
    public function deleted(Subscription $subscription): void
    {
        //
    }

    /**
     * Handle the subscription "restored" event.
     *
     * @return void
     */
    public function restored(Subscription $subscription): void
    {
        //
    }

    /**
     * Handle the subscription "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Subscription $subscription): void
    {
        //
    }
}
