<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\Payment;

use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidInvoicesRulesV2 implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // $attribute example: "invoices.2.amount"
        preg_match('/invoices\.(\d+)\.amount/', $attribute, $matches);
nlog($matches);

        $index = ($matches[1] ?? 0) + 1; // convert 0-based to 1-based

        $inv = Invoice::withTrashed()->where('id', $value)->company()->first();

        $item = request()->invoices[$index];

        if (! $inv) {
            $fail("Invoice #{$index} not found.");
        }
        elseif ($inv->status_id == Invoice::STATUS_DRAFT && floatval($item['amount']) > floatval($inv->amount)) {
            $fail('Amount cannot be greater than invoice balance');
            
        } elseif ($item['amount'] < 0 && $inv->amount >= 0) {
            $fail('Amount cannot be negative');
            
        } elseif (floatval($item['amount']) > floatval($inv->balance)) {
            $fail(ctrans('texts.amount_greater_than_balance_v5'));
            
        } elseif ($inv->is_deleted) {
            $fail('One or more invoices in this request have since been deleted');
            
        }
     

        // etc...
    }
}
