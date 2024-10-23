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

class AllowanceCharges
{
    public string $reason;
    public float $amountExcludingTax;
    public Tax $tax;

    public function __construct(
        string $reason,
        float $amountExcludingTax,
        Tax $tax
    ) {
        $this->reason = $reason;
        $this->amountExcludingTax = $amountExcludingTax;
        $this->tax = $tax;
    }
}
