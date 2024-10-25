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

class Party
{
    public string $companyName;
    public Address $address;
    public Contact $contact;

    public function __construct(
        string $companyName,
        Address $address,
        Contact $contact
    ) {
        $this->companyName = $companyName;
        $this->address = $address;
        $this->contact = $contact;
    }
}
