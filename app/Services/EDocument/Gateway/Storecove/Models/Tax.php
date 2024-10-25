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


class Tax
{
    #[SerializedName('Item.ClassifiedTaxCategory.0.Percent')]
    public float $taxPercentage = 0.0;

    #[SerializedName('LineExtensionAmount.amount')]
    public float $taxableAmount = 0.0;

    #[SerializedName('TaxTotal.0.TaxAmount.amount')]
    public float $taxAmount = 0.0;

    #[SerializedName('Item.ClassifiedTaxCategory.0.ID.value')]
    public string $taxCategory = '';

    // Getters and setters
    public function getTaxPercentage(): float
    {
        return $this->taxPercentage;
    }

    public function setTaxPercentage(float $taxPercentage): void
    {
        $this->taxPercentage = $taxPercentage;
    }

    public function getTaxableAmount(): float
    {
        return $this->taxableAmount;
    }

    public function setTaxableAmount(float $taxableAmount): void
    {
        $this->taxableAmount = $taxableAmount;
    }

    public function getTaxAmount(): float
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(float $taxAmount): void
    {
        $this->taxAmount = $taxAmount;
    }

    public function getTaxCategory(): string
    {
        return $this->taxCategory;
    }

    public function setTaxCategory(string $taxCategory): void
    {
        $this->taxCategory = $taxCategory;
    }
}
