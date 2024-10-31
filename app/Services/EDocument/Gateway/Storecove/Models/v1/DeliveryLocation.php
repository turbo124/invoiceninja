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

class DeliveryLocation
{
    public string $id;
    public string $schemeId;
    public Address $address;

    public function __construct(
        string $id,
        string $schemeId,
        Address $address
    ) {
        $this->id = $id;
        $this->schemeId = $schemeId;
        $this->address = $address;
    }
}
