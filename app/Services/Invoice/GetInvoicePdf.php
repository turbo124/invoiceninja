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

namespace App\Services\Invoice;

use App\Jobs\Invoice\CreateInvoicePdf;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Services\AbstractService;
use Illuminate\Support\Facades\Storage;

class GetInvoicePdf extends AbstractService
{
    public function __construct(Invoice $invoice, ClientContact $contact = null)
    {
        $this->invoice = $invoice;

        $this->contact = $contact;
    }

    public function run()
    {
        if (! $this->contact) {
            $this->contact = $this->invoice->client->primary_contact()->first();
        }

        $invitation = $this->invoice->invitations->where('client_contact_id', $this->contact->id)->first();

        $path = $this->invoice->client->invoice_filepath();

        $file_path = $path.$this->invoice->number.'.pdf';

        $disk = config('filesystems.default');

        $file = Storage::disk($disk)->exists($file_path);

        if (! $file) {
            $file_path = CreateInvoicePdf::dispatchNow($invitation);
        }

        return Storage::disk($disk)->path($file_path);
    }
}
