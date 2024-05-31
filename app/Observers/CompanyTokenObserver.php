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

use App\Models\CompanyToken;

class CompanyTokenObserver
{
    /**
     * Handle the company token "created" event.
     */
    public function created(CompanyToken $companyToken): void
    {
        //
    }

    /**
     * Handle the company token "updated" event.
     */
    public function updated(CompanyToken $companyToken): void
    {
        //
    }

    /**
     * Handle the company token "deleted" event.
     */
    public function deleted(CompanyToken $companyToken): void
    {
        //
    }

    /**
     * Handle the company token "restored" event.
     */
    public function restored(CompanyToken $companyToken): void
    {
        //
    }

    /**
     * Handle the company token "force deleted" event.
     */
    public function forceDeleted(CompanyToken $companyToken): void
    {
        //
    }
}
