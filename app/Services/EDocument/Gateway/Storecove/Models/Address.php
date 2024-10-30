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
    public function __construct(
        public string $street1,
        public string $city,
        public string $zip,
        public string $country,
        public ?string $county = null,
        public ?string $street2 = null,
    ) { }
}
