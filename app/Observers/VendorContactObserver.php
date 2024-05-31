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

use App\Models\PurchaseOrderInvitation;
use App\Models\VendorContact;

class VendorContactObserver
{
    /**
     * Handle the vendor contact "created" event.
     */
    public function created(VendorContact $vendorContact): void
    {
        //
    }

    /**
     * Handle the vendor contact "updated" event.
     */
    public function updated(VendorContact $vendorContact): void
    {
        //
    }

    /**
     * Handle the vendor contact "deleted" event.
     */
    public function deleted(VendorContact $vendorContact): void
    {
        $vendor_contact_id = $vendorContact->id;

        $vendorContact->purchase_order_invitations()->delete();

        PurchaseOrderInvitation::withTrashed()->where('vendor_contact_id', $vendor_contact_id)->cursor()->each(function ($invite) {
            if ($invite->purchase_order()->doesnthave('invitations')) {
                $invite->purchase_order->service()->createInvitations();
            }
        });
    }

    /**
     * Handle the vendor contact "restored" event.
     */
    public function restored(VendorContact $vendorContact): void
    {
    }

    /**
     * Handle the vendor contact "force deleted" event.
     */
    public function forceDeleted(VendorContact $vendorContact): void
    {
        //
    }
}
