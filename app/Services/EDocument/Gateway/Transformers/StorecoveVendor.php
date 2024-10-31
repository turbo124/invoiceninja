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

use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Attribute\Context;
use DateTime;

class StorecoveVendor
{
    #[SerializedName('name')]
    public string $name = '';

    #[SerializedName('vat_number')]
    public string $vatNumber = '';

    #[SerializedName('number')]
    public string $number = '';

    #[SerializedName('website')]
    public string $website = '';

    #[SerializedName('phone')]
    public string $phone = '';

    #[SerializedName('address1')]
    public string $streetName = '';

    #[SerializedName('address2')]
    public string $additionalStreetName = '';

    #[SerializedName('city')]
    public string $city = '';

    #[SerializedName('state')]
    public string $countrySubentity = '';

    #[SerializedName('postal_code')]
    public string $postalZone = '';

    #[SerializedName('country_id')]
    public string $countryCode = '';

    #[SerializedName('custom_value1')]
    public ?string $companyId = null;

    #[SerializedName('email')]
    public string $email = '';

    #[SerializedName('currency_id')]
    public string $currency = '';
}
