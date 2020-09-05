<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Observers;

use App\Models\CompanyToken;

class CompanyTokenObserver
{
    /**
     * Handle the company token "created" event.
     *
     * @param  \App\Models\CompanyToken  $companyToken
     * @return void
     */
    public function created(CompanyToken $companyToken)
    {
        //
    }

    /**
     * Handle the company token "updated" event.
     *
     * @param  \App\Models\CompanyToken  $companyToken
     * @return void
     */
    public function updated(CompanyToken $companyToken)
    {
        //
    }

    /**
     * Handle the company token "deleted" event.
     *
     * @param  \App\Models\CompanyToken  $companyToken
     * @return void
     */
    public function deleted(CompanyToken $companyToken)
    {
        //
    }

    /**
     * Handle the company token "restored" event.
     *
     * @param  \App\Models\CompanyToken  $companyToken
     * @return void
     */
    public function restored(CompanyToken $companyToken)
    {
        //
    }

    /**
     * Handle the company token "force deleted" event.
     *
     * @param  \App\Models\CompanyToken  $companyToken
     * @return void
     */
    public function forceDeleted(CompanyToken $companyToken)
    {
        //
    }
}
