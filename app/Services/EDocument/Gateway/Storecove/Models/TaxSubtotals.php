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

class TaxSubtotals
{
    public float $taxableAmount;
    public float $taxAmount;
    public int $percentage;
    public string $country;

    public function __construct(
        float $taxableAmount,
        float $taxAmount,
        int $percentage,
        string $country
    ) {
        $this->taxableAmount = $taxableAmount;
        $this->taxAmount = $taxAmount;
        $this->percentage = $percentage;
        $this->country = $country;
    }
}
