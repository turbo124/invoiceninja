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

namespace App\Transformers;

use App\Models\Invoice;

class InvoiceProfileTransformer extends InvoiceTransformer
{
    protected array $defaultIncludes = [];

    protected array $availableIncludes = [];

    public function transform(Invoice $invoice)
    {
        return array_merge(parent::transform($invoice), [
            'private_notes' => '',
            'amount' => 0.0,
            'balance' => 0.0,
            'paid_to_date' => 0.0,
        ]);
    }
}
