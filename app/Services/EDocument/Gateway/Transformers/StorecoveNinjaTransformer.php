<?php

namespace App\Services\EDocument\Gateway\Transformers;

use App\Helpers\Invoice\Taxer;
use App\Utils\Traits\NumberFormatter;
use App\Services\EDocument\Gateway\Storecove\Models\Tax;
use App\Services\EDocument\Gateway\Storecove\Models\Party;
use App\Services\EDocument\Gateway\Storecove\Models\Address;
use App\Services\EDocument\Gateway\Storecove\Models\Contact;
use App\Services\EDocument\Gateway\Storecove\Models\References;
use App\Services\EDocument\Gateway\Storecove\Models\InvoiceLines;
use App\Services\EDocument\Gateway\Storecove\Models\PaymentMeans;
use App\Services\EDocument\Gateway\Storecove\Models\TaxSubtotals;
use App\Services\EDocument\Gateway\Storecove\Models\AllowanceCharges;
use App\Services\EDocument\Gateway\Storecove\Models\AccountingCustomerParty;
use App\Services\EDocument\Gateway\Storecove\Models\AccountingSupplierParty;
use App\Services\EDocument\Gateway\Storecove\Models\Invoice as StorecoveInvoice;
use Illuminate\Support\Str;

class StorecoveNinjaTransformer implements TransformerInterface
{
    public function transform(mixed $invoice)
    {
        $document = data_get($invoice, 'document.invoice');
    }

    public function getInvoice()
    {

    }

    public function toJson()
    {

    }
}