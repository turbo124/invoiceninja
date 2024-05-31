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

use App\Models\Account;

class AccountObserver
{
    /**
     * Handle the account "created" event.
     *
     * @return void
     */
    public function created(Account $account): void
    {
        //
    }

    /**
     * Handle the account "updated" event.
     *
     * @return void
     */
    public function updated(Account $account): void
    {
        //
    }

    /**
     * Handle the account "deleted" event.
     *
     * @return void
     */
    public function deleted(Account $account): void
    {
        //
    }

    /**
     * Handle the account "restored" event.
     *
     * @return void
     */
    public function restored(Account $account): void
    {
        //
    }

    /**
     * Handle the account "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Account $account): void
    {
        //
    }
}
