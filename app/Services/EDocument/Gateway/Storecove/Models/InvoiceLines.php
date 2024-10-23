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


class InvoiceLines
{
    public string $lineId;
    public float $amountExcludingVat;
    public float $itemPrice;
    public int $baseQuantity;
    public int $quantity;
    public string $quantityUnitCode;
    /** @var AllowanceCharges[] */
    public array $allowanceCharges;
    public Tax $tax;
    public string $orderLineReferenceLineId;
    public string $accountingCost;
    public string $name;
    public string $description;
    public string $invoicePeriod;
    public string $note;
    /** @var References[] */
    public array $references;
    /** @var AdditionalItemProperties[] */
    public array $additionalItemProperties;

    /**
     * @param AllowanceCharges[] $allowanceCharges
     * @param References[] $references
     * @param AdditionalItemProperties[] $additionalItemProperties
     */
    public function __construct(
        string $lineId,
        float $amountExcludingVat,
        float $itemPrice,
        int $baseQuantity,
        int $quantity,
        string $quantityUnitCode,
        array $allowanceCharges,
        Tax $tax,
        string $orderLineReferenceLineId,
        string $accountingCost,
        string $name,
        string $description,
        string $invoicePeriod,
        string $note,
        array $references,
        array $additionalItemProperties
    ) {
        $this->lineId = $lineId;
        $this->amountExcludingVat = $amountExcludingVat;
        $this->itemPrice = $itemPrice;
        $this->baseQuantity = $baseQuantity;
        $this->quantity = $quantity;
        $this->quantityUnitCode = $quantityUnitCode;
        $this->allowanceCharges = $allowanceCharges;
        $this->tax = $tax;
        $this->orderLineReferenceLineId = $orderLineReferenceLineId;
        $this->accountingCost = $accountingCost;
        $this->name = $name;
        $this->description = $description;
        $this->invoicePeriod = $invoicePeriod;
        $this->note = $note;
        $this->references = $references;
        $this->additionalItemProperties = $additionalItemProperties;
    }
}
