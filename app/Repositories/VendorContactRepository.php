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

namespace App\Repositories;

use App\Models\Vendor;
use App\Models\VendorContact;
use Illuminate\Support\Str;

/**
 * VendorContactRepository.
 */
class VendorContactRepository extends BaseRepository
{
    public function save($contacts, Vendor $vendor) : void
    {

        /* Convert array to collection */
        $contacts = collect($contacts);

        /* Get array of IDs which have been removed from the contacts array and soft delete each contact */
        collect($vendor->contacts->pluck('id'))->diff($contacts->pluck('id'))->each(function ($contact) {
            VendorContact::destroy($contact);
        });

        $this->is_primary = true;
        /* Set first record to primary - always */
        $contacts = $contacts->sortByDesc('is_primary')->map(function ($contact) {
            $contact['is_primary'] = $this->is_primary;
            $this->is_primary = false;

            return $contact;
        });

        //loop and update/create contacts
        $contacts->each(function ($contact) use ($vendor) {
            $update_contact = null;

            if (isset($contact['id'])) {
                $update_contact = VendorContact::find($this->decodePrimaryKey($contact['id']));
            }

            if (! $update_contact) {
                $update_contact = new VendorContact;
                $update_contact->vendor_id = $vendor->id;
                $update_contact->company_id = $vendor->company_id;
                $update_contact->user_id = $vendor->user_id;
                $update_contact->contact_key = Str::random(40);
            }

            $update_contact->fill($contact);

            $update_contact->save();
        });

        //always made sure we have one blank contact to maintain state
        if ($contacts->count() == 0) {
            $new_contact = new VendorContact;
            $new_contact->vendor_id = $vendor->id;
            $new_contact->company_id = $vendor->company_id;
            $new_contact->user_id = $vendor->user_id;
            $new_contact->contact_key = Str::random(40);
            $new_contact->is_primary = true;
            $new_contact->save();
        }
    }
}
