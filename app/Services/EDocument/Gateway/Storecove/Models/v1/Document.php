<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Gateway\Storecove\Models;


class Document
{
    public string $documentType;
    public Invoice $invoice;

    public function __construct(string $documentType, Invoice $invoice)
    {
        $this->documentType = $documentType;
        $this->invoice = $invoice;
    }
}
