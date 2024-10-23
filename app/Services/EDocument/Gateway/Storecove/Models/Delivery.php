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


class Delivery
{
    public string $deliveryPartyName;
    public string $actualDeliveryDate;
    public DeliveryLocation $deliveryLocation;

    public function __construct(
        string $deliveryPartyName,
        string $actualDeliveryDate,
        DeliveryLocation $deliveryLocation
    ) {
        $this->deliveryPartyName = $deliveryPartyName;
        $this->actualDeliveryDate = $actualDeliveryDate;
        $this->deliveryLocation = $deliveryLocation;
    }
}
