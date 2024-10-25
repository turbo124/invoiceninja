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


class Address
{
    public string $street1;
    public null $street2;
    public string $city;
    public string $zip;
    public null $county;
    public string $country;

    public function __construct(
        string $street1,
        null $street2,
        string $city,
        string $zip,
        null $county,
        string $country
    ) {
        $this->street1 = $street1;
        $this->street2 = $street2;
        $this->city = $city;
        $this->zip = $zip;
        $this->county = $county;
        $this->country = $country;
    }
}
