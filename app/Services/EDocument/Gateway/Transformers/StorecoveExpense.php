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
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Attribute\Context;
use DateTime;

class StorecoveExpense 
{
    
    #[SerializedName('currency_id')]
    public string $document_currency_code = '';

    #[SerializedName('number')]
    public string $invoice_number = '';

    /** @var ?DateTime */
    #[SerializedName('date')]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    public ?DateTime $issue_date;

    // References could be mapped to custom fields if needed
    public array $references = [];

    // Accounting cost could be mapped to a custom field if needed
    public ?string $accounting_cost = null;

    #[SerializedName('public_notes')]
    public string $note = '';

    #[SerializedName('amount')]
    public float $amount_including_tax = 0.0;

    /** @var StorecoveVendor */
    #[SerializedName('vendor')]
    public $accounting_supplier_party;

    // Payment means could be mapped to payment_type_id if needed
    public $payment_means_array = [];

    #[SerializedName('tax_amount')]
    public $tax_subtotals = [];

    // Invoice lines would need special handling for expense categories
    public $invoice_ines = [];

    // These don't have direct mappings in IN expenses
    public $allowance_charges = [];
}