<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Xero;

use App\Libraries\MultiDB;
use Illuminate\Contracts\Queue\ShouldQueue;

class XeroUpdateInvoiceListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        MultiDB::setDb($event->company->db);

        if(!$event->company->xero_sync_invoices || !$event->company->xero_tenant()->exists())
            return;

        $tenant = $event->company->xero_tenant->tenant_id;
        $user = $event->company->xero_tenant->user;
        
    }
}
