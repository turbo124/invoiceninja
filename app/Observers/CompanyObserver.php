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

use App\Events\Company\CompanyDocumentsDeleted;
use App\Models\Company;
use App\Utils\Ninja;

class CompanyObserver
{
    /**
     * Handle the company "created" event.
     */
    public function created(Company $company): void
    {
        //
    }

    /**
     * Handle the company "updated" event.
     */
    public function updated(Company $company): void
    {
        if (Ninja::isHosted() && $company->portal_mode == 'domain' && $company->isDirty('portal_domain')) {
            \Modules\Admin\Jobs\Domain\CustomDomain::dispatch($company->getOriginal('portal_domain'), $company)->onQueue('domain');
        }

    }

    /**
     * Handle the company "deleted" event.
     */
    public function deleted(Company $company): void
    {
        event(new CompanyDocumentsDeleted($company));
    }

    /**
     * Handle the company "restored" event.
     */
    public function restored(Company $company): void
    {
        //
    }

    /**
     * Handle the company "force deleted" event.
     */
    public function forceDeleted(Company $company): void
    {
        //
    }
}
