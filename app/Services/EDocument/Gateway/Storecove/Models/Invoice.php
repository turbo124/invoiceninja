<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

use JsonSerializable;
use Symfony\Component\Serializer\Annotation\SerializedName;
use DateTime;

class Invoice implements JsonSerializable
{
    #[SerializedName('taxSystem')]
    private string $taxSystem = 'tax_line_percentages';

    #[SerializedName('documentCurrency')]
    private string $documentCurrency = '';

    #[SerializedName('invoiceNumber')]
    private string $invoiceNumber = '';

    #[SerializedName('issueDate')]
    private DateTime $issueDate;

    #[SerializedName('taxPointDate')]
    private ?DateTime $taxPointDate = null;

    #[SerializedName('dueDate')]
    private DateTime $dueDate;

    #[SerializedName('invoicePeriod')]
    private array $invoicePeriod = [];

    #[SerializedName('references')]
    private array $references = [];

    #[SerializedName('accountingCost')]
    private ?string $accountingCost = null;

    #[SerializedName('note')]
    private string $note = '';

    #[SerializedName('amountIncludingVat')]
    private float $amountIncludingVat = 0.0;

    #[SerializedName('prepaidAmount')]
    private ?float $prepaidAmount = null;

    #[SerializedName('accountingSupplierParty')]
    private array $accountingSupplierParty = [];

    #[SerializedName('accountingCustomerParty')]
    private array $accountingCustomerParty = [];

    #[SerializedName('paymentMeans')]
    private array $paymentMeans = [];

    #[SerializedName('taxTotal')]
    private array $taxTotal = [];

    /**
     * @var InvoiceLines[]
     */
    private array $invoiceLines = [];

    // Getters and setters for all properties

    public function setDocumentCurrency(string $documentCurrency): void
    {
        $this->documentCurrency = $documentCurrency;
    }

    public function setInvoiceNumber(string $invoiceNumber): void
    {
        $this->invoiceNumber = $invoiceNumber;
    }

    public function setIssueDate(DateTime $issueDate): void
    {
        $this->issueDate = $issueDate;
    }

    public function setTaxPointDate(?DateTime $taxPointDate): void
    {
        $this->taxPointDate = $taxPointDate;
    }

    public function setDueDate(DateTime $dueDate): void
    {
        $this->dueDate = $dueDate;
    }

    public function setInvoicePeriod(array $invoicePeriod): void
    {
        $this->invoicePeriod = $invoicePeriod;
    }

    public function setReferences(array $references): void
    {
        $this->references = $references;
    }

    public function setAccountingCost(?string $accountingCost): void
    {
        $this->accountingCost = $accountingCost;
    }

    public function setNote(string $note): void
    {
        $this->note = $note;
    }

    public function setAmountIncludingVat(float $amountIncludingVat): void
    {
        $this->amountIncludingVat = $amountIncludingVat;
    }

    public function setPrepaidAmount(?float $prepaidAmount): void
    {
        $this->prepaidAmount = $prepaidAmount;
    }

    public function setAccountingSupplierParty(array $accountingSupplierParty): void
    {
        $this->accountingSupplierParty = $accountingSupplierParty;
    }

    public function setAccountingCustomerParty(array $accountingCustomerParty): void
    {
        $this->accountingCustomerParty = $accountingCustomerParty;
    }

    public function setPaymentMeans(array $paymentMeans): void
    {
        $this->paymentMeans = $paymentMeans;
    }

    public function setTaxTotal(array $taxTotal): void
    {
        $this->taxTotal = $taxTotal;
    }

     /**
     * @param InvoiceLines[] $invoiceLines
     */
    public function setInvoiceLines(array $invoiceLines): void
    {
        $this->invoiceLines = $invoiceLines;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'taxSystem' => $this->taxSystem,
            'documentCurrency' => $this->documentCurrency,
            'invoiceNumber' => $this->invoiceNumber,
            'issueDate' => $this->issueDate->format('Y-m-d'),
            'taxPointDate' => $this->taxPointDate ? $this->taxPointDate->format('Y-m-d') : null,
            'dueDate' => $this->dueDate->format('Y-m-d'),
            'invoicePeriod' => $this->invoicePeriod,
            'references' => $this->references,
            'accountingCost' => $this->accountingCost,
            'note' => $this->note,
            'amountIncludingVat' => $this->amountIncludingVat,
            'prepaidAmount' => $this->prepaidAmount,
            'accountingSupplierParty' => $this->accountingSupplierParty,
            'accountingCustomerParty' => $this->accountingCustomerParty,
            'paymentMeans' => $this->paymentMeans,
            'taxTotal' => $this->taxTotal,
            'invoiceLines' => $this->invoiceLines,
        ];
    }
}
