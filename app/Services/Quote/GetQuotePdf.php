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

namespace App\Services\Quote;

use App\Jobs\Quote\CreateQuotePdf;
use App\Models\ClientContact;
use App\Models\Quote;
use App\Services\AbstractService;
use Illuminate\Support\Facades\Storage;

class GetQuotePdf extends AbstractService
{
    public function __construct(Quote $quote, ClientContact $contact = null)
    {
        $this->quote = $quote;

        $this->contact = $contact;
    }

    public function run()
    {
        if (! $this->contact) {
            $this->contact = $this->quote->client->primary_contact()->first();
        }

        $invitation = $this->quote->invitations->where('client_contact_id', $this->contact->id)->first();

        $path = $this->quote->client->invoice_filepath();

        $file_path = $path.$this->quote->number.'.pdf';

        $disk = config('filesystems.default');

        $file = Storage::disk($disk)->exists($file_path);

        if (! $file) {
            $file_path = CreateQuotePdf::dispatchNow($invitation);
        }

        return Storage::disk($disk)->path($file_path);
    }
}
