<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

use App\Services\EDocument\Gateway\Storecove\Models\Tax;
use Symfony\Component\Serializer\Annotation\SerializedName;

class InvoiceLines
{
    public string $lineId = '';

    public float $amountExcludingVat = 0.0;

    public float $itemPrice = 0.0;

    public int $quantity = 0;

    public string $quantityUnitCode = '';

    public string $name = '';

    public string $description = '';

    public Tax $tax;

    public function __construct()
    {
        $this->tax = new Tax();
    }

    // Getters and setters
    public function getLineId(): string
    {
        return $this->lineId;
    }

    public function setLineId(string $lineId): void
    {
        $this->lineId = $lineId;
    }

    public function getAmountExcludingVat(): float
    {
        return $this->amountExcludingVat;
    }

    public function setAmountExcludingVat(float $amountExcludingVat): void
    {
        $this->amountExcludingVat = $amountExcludingVat;
    }

    public function getItemPrice(): float
    {
        return $this->itemPrice;
    }

    public function setItemPrice(float $itemPrice): void
    {
        $this->itemPrice = $itemPrice;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getQuantityUnitCode(): string
    {
        return $this->quantityUnitCode;
    }

    public function setQuantityUnitCode(string $quantityUnitCode): void
    {
        $this->quantityUnitCode = $quantityUnitCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getTax(): Tax
    {
        return $this->tax;
    }

    public function setTax(Tax $tax): void
    {
        $this->tax = $tax;
    }
}

