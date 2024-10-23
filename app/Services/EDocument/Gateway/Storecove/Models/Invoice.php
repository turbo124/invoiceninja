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

class Invoice
{
    public string $taxSystem;
    public string $documentCurrency;
    public string $invoiceNumber;
    public string $issueDate;
    public string $taxPointDate;
    public string $dueDate;
    public string $invoicePeriod;
    /** @var References[] */
    public array $references;
    public string $accountingCost;
    public string $note;
    public AccountingSupplierParty $accountingSupplierParty;
    public AccountingCustomerParty $accountingCustomerParty;
    public Delivery $delivery;
    public PaymentTerms $paymentTerms;
    /** @var PaymentMeansArray[] */
    public array $paymentMeansArray;
    /** @var InvoiceLines[] */
    public array $invoiceLines;
    /** @var AllowanceCharges[] */
    public array $allowanceCharges;
    /** @var TaxSubtotals[] */
    public array $taxSubtotals;
    public float $amountIncludingVat;
    public int $prepaidAmount;

    /**
     * @param References[] $references
     * @param PaymentMeansArray[] $paymentMeansArray
     * @param InvoiceLines[] $invoiceLines
     * @param AllowanceCharges[] $allowanceCharges
     * @param TaxSubtotals[] $taxSubtotals
     */
    public function __construct(
        string $taxSystem,
        string $documentCurrency,
        string $invoiceNumber,
        string $issueDate,
        string $taxPointDate,
        string $dueDate,
        string $invoicePeriod,
        array $references,
        string $accountingCost,
        string $note,
        AccountingSupplierParty $accountingSupplierParty,
        AccountingCustomerParty $accountingCustomerParty,
        Delivery $delivery,
        PaymentTerms $paymentTerms,
        array $paymentMeansArray,
        array $invoiceLines,
        array $allowanceCharges,
        array $taxSubtotals,
        float $amountIncludingVat,
        int $prepaidAmount
    ) {
        $this->taxSystem = $taxSystem;
        $this->documentCurrency = $documentCurrency;
        $this->invoiceNumber = $invoiceNumber;
        $this->issueDate = $issueDate;
        $this->taxPointDate = $taxPointDate;
        $this->dueDate = $dueDate;
        $this->invoicePeriod = $invoicePeriod;
        $this->references = $references;
        $this->accountingCost = $accountingCost;
        $this->note = $note;
        $this->accountingSupplierParty = $accountingSupplierParty;
        $this->accountingCustomerParty = $accountingCustomerParty;
        $this->delivery = $delivery;
        $this->paymentTerms = $paymentTerms;
        $this->paymentMeansArray = $paymentMeansArray;
        $this->invoiceLines = $invoiceLines;
        $this->allowanceCharges = $allowanceCharges;
        $this->taxSubtotals = $taxSubtotals;
        $this->amountIncludingVat = $amountIncludingVat;
        $this->prepaidAmount = $prepaidAmount;
    }
}
