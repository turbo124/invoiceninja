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

class Invoice 
{
    public string $taxSystem = 'tax_line_percentages';

    public string $documentCurrency = '';

    public string $invoiceNumber = '';

    public string $issueDate = '';

    public string $taxPointDate = '';

	public string $dueDate = '';

    public string $invoicePeriod = '';

    public array $references = [];

    public ?string $accountingCost = null;

    public string $note = '';

    public float $amountIncludingVat = 0.0;

    public ?float $prepaidAmount = null;

    public array $accountingSupplierParty = [];

    public array $accountingCustomerParty = [];

    public array $paymentMeans = [];

    public array $taxTotal = [];

    public array $invoiceLines = [];


    public function setDocumentCurrency(string $documentCurrency): void
    {
        $this->documentCurrency = $documentCurrency;
    }

    public function setInvoiceNumber(string $invoiceNumber): void
    {
        $this->invoiceNumber = $invoiceNumber;
    }

    public function setIssueDate($issueDate): void
    {
        $this->issueDate = $issueDate;
    }

    public function setTaxPointDate($taxPointDate): void
    {
        $this->taxPointDate = $taxPointDate;
    }

    public function setDueDate($dueDate): void
    {
        $this->dueDate = $dueDate;
    }

    public function setInvoicePeriod($invoicePeriod): void
    {
        $this->invoicePeriod = $invoicePeriod;
    }

    public function setReferences( $references): void
    {
        $this->references = $references;
    }

    public function setAccountingCost($accountingCost): void
    {
        $this->accountingCost = $accountingCost;
    }

    public function setNote($note): void
    {
        $this->note = $note;
    }

    public function setAmountIncludingVat ($amountIncludingVat): void
    {
        $this->amountIncludingVat = $amountIncludingVat;
    }

    public function setPrepaidAmount( $prepaidAmount): void
    {
        $this->prepaidAmount = $prepaidAmount;
    }

    public function setAccountingSupplierParty( $accountingSupplierParty): void
    {
        $this->accountingSupplierParty = $accountingSupplierParty;
    }

    public function setAccountingCustomerParty( $accountingCustomerParty): void
    {
        $this->accountingCustomerParty = $accountingCustomerParty;
    }

    public function setPaymentMeans( $paymentMeans): void
    {
        $this->paymentMeans = $paymentMeans;
    }

    public function setTaxTotal( $taxTotal): void
    {
        $this->taxTotal = $taxTotal;
    }

    public function setInvoiceLines(array $invoiceLines): void
    {
        $this->invoiceLines = $invoiceLines;
    }

    public function getInvoiceLines()
    {
        return $this->invoiceLines;
    }

    public function getTaxSystem(): string
    {
        return $this->taxSystem;
    }

    public function getDocumentCurrency(): string
    {
        return $this->documentCurrency;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function getIssueDate(): string
    {
        return $this->issueDate;
    }

    public function getTaxPointDate(): string
    {
        return $this->taxPointDate;
    }

    public function getDueDate(): string
    {
        return $this->dueDate;
    }

    public function getInvoicePeriod(): string
    {
        return $this->invoicePeriod;
    }

    public function getReferences(): array
    {
        return $this->references;
    }

    public function getAccountingCost(): ?string
    {
        return $this->accountingCost;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function getAmountIncludingVat(): float
    {
        return $this->amountIncludingVat;
    }

    public function getPrepaidAmount(): ?float
    {
        return $this->prepaidAmount;
    }

    public function getAccountingSupplierParty(): array
    {
        return $this->accountingSupplierParty;
    }

    public function getAccountingCustomerParty(): array
    {
        return $this->accountingCustomerParty;
    }

    public function getPaymentMeans(): array
    {
        return $this->paymentMeans;
    }

    public function getTaxTotal(): array
    {
        return $this->taxTotal;
    }

    public function setTaxSystem(string $taxSystem): void
    {
        $this->taxSystem = $taxSystem;
    }
}
