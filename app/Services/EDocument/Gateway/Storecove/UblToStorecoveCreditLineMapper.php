<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Gateway\Storecove;

use App\Services\EDocument\Gateway\Storecove\Models\AllowanceCharges;
use App\Services\EDocument\Gateway\Storecove\Models\CreditLines;

/**
 * Deterministic UBL CreditNoteLine → Storecove credit-as-negative-invoice wire fields.
 *
 * Input values are the deserialized UBL fields (CreditedQuantity, PriceAmount,
 * LineExtensionAmount, AllowanceCharge) BEFORE CreditLines sign transforms.
 */
class UblToStorecoveCreditLineMapper
{
    /**
     * @param  float|null  $itemPrice  UBL Price/PriceAmount
     * @param  float|null  $quantity  UBL CreditedQuantity
     * @param  float|null  $lineExtension  UBL LineExtensionAmount
     */
    public function mapLineAmounts(?float $itemPrice, ?float $quantity, ?float $lineExtension): array
    {
        $qtySign = (!is_null($quantity) && $quantity < 0) ? -1.0 : 1.0;

        $signedLineAmount = is_null($lineExtension) ? null : -$lineExtension;

        return [
            'item_price' => is_null($itemPrice) ? null : -($itemPrice * $qtySign),
            'quantity' => is_null($quantity) ? null : abs($quantity),
            'amount_excluding_vat' => $signedLineAmount,
            'amount_excluding_tax' => $signedLineAmount,
        ];
    }

    /**
     * Storecove decorate() allowance sign for a deserialized line discount/charge.
     */
    public function mapLineAllowanceAmount(
        AllowanceCharges $allowance,
        float $wireItemPrice,
        bool $isCreditDocument,
    ): float {
        if ($allowance->reason !== 'Discount' || is_null($allowance->amount_excluding_tax)) {
            return $allowance->amount_excluding_tax ?? 0.0;
        }

        $amount = abs($allowance->amount_excluding_tax);

        if (!$isCreditDocument) {
            return -$amount;
        }

        $isClawbackLine = $wireItemPrice > 0;

        return $isClawbackLine ? -$amount : $amount;
    }

    /**
     * Document-level discount allowance sign for Storecove.
     */
    public function mapDocumentAllowanceAmount(float $amount, bool $isCreditDocument): float
    {
        return $isCreditDocument ? abs($amount) : -abs($amount);
    }

    /**
     * Apply line amount mapping once per CreditLines instance.
     *
     * @internal Called from CreditLines only — not re-entrant.
     */
    public function applyMappingOnce(CreditLines $line): CreditLines
    {
        if ($line->isStorecoveCreditMapped()) {
            return $line;
        }

        $this->applyToCreditLine($line);
        $line->markStorecoveCreditMapped();

        return $line;
    }

    /**
     * @internal
     */
    private function applyToCreditLine(CreditLines $line): CreditLines
    {
        $rawQuantity = $line->quantity;
        $rawIncludingTax = $line->amount_including_tax;

        $mapped = $this->mapLineAmounts($line->item_price, $rawQuantity, $line->amount_excluding_vat);

        $line->item_price = $mapped['item_price'];
        $line->quantity = $mapped['quantity'];
        $line->amount_excluding_vat = $mapped['amount_excluding_vat'];
        // Storecove line amount alias — must match LineExtensionAmount, not unit price.
        $line->amount_excluding_tax = $mapped['amount_excluding_vat'];
        // Exclusive-tax Peppol lines carry net in LineExtensionAmount only; never
        // synthesize a tax-inclusive figure from the extension.
        $line->amount_including_tax = is_null($rawIncludingTax) ? null : -$rawIncludingTax;

        return $line;
    }
}
