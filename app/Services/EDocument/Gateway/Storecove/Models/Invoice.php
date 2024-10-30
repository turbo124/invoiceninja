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

    public $accountingSupplierParty;

    public $accountingCustomerParty;

    public $paymentMeans = [];

    public $taxTotal = [];

    public $invoiceLines = [];

    public $allowanceCharges = [];

    public $taxSubtotals = [];

    public function setDocumentCurrency(string $documentCurrency): self
    {
        $this->documentCurrency = $documentCurrency;
    
        return $this;
}

    public function setInvoiceNumber(string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;
    
        return $this;
}

    public function setIssueDate($issueDate): self
    {
        $this->issueDate = $issueDate;

        return $this;
    }

    public function setTaxPointDate($taxPointDate): self
    {
        $this->taxPointDate = $taxPointDate;

        return $this;
    }

    public function setDueDate($dueDate): self
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function setInvoicePeriod($invoicePeriod): self
    {
        $this->invoicePeriod = $invoicePeriod;
        return $this;
    }

    public function setReferences($references): self
    {
        $this->references = $references;
        return $this;

    }

    public function addReferences($reference): self
    {
        $this->references[] = $reference;

        return $this;
    }
    public function setAccountingCost($accountingCost): self
    {
        $this->accountingCost = $accountingCost;
        
return $this;

    }

    public function setNote($note): self
    {
        $this->note = $note;
        
return $this;

    }

    public function setAmountIncludingVat ($amountIncludingVat): self
    {
        $this->amountIncludingVat = $amountIncludingVat;
        
return $this;

    }

    public function setPrepaidAmount( $prepaidAmount): self
    {
        $this->prepaidAmount = $prepaidAmount;
        
return $this;

    }

    public function setAccountingSupplierParty($accountingSupplierParty): self
    {
        $this->accountingSupplierParty = $accountingSupplierParty;
        
return $this;

    }

    public function setAccountingCustomerParty( $accountingCustomerParty): self
    {
        $this->accountingCustomerParty = $accountingCustomerParty;
        
return $this;

    }

    public function setPaymentMeans( $paymentMeans): self
    {
        $this->paymentMeans = $paymentMeans;
        
return $this;

    }

    public function addPaymentMeans($paymentMeans): self
    {
        $this->paymentMeans[] = $paymentMeans;
        return $this;
    }

    public function setTaxTotal( $taxTotal): self
    {
        $this->taxTotal = $taxTotal;
        
return $this;

    }

    public function setInvoiceLines(array $invoiceLines): self
    {
        $this->invoiceLines = $invoiceLines;
        
return $this;

    }

    public function addInvoiceLines($invoiceLine): self
    {
        $this->invoiceLines[] = $invoiceLine;
        return $this;
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

    public function setTaxSystem(string $taxSystem): self
    {
        $this->taxSystem = $taxSystem;
        return $this;
    }

    public function getAllowanceCharges()
    {
        return $this->allowanceCharges;
    }

    public function setAllowanceCharges($allowanceCharges): self
    {
        $this->allowanceCharges = $allowanceCharges;

        return $this;
    }

    public function addAllowanceCharge($allowanceCharge): self
    {
        $this->allowanceCharges[] = $allowanceCharge;

        return $this;
    }

    public function getTaxSubtotals()
    {
        return $this->taxSubtotals;
    }

    public function setTaxSubtotals($taxSubtotals): self
    {
        $this->taxSubtotals = $taxSubtotals;

        return $this;
    }

    public function addTaxSubtotals($taxSubtotals): self
    {
        $this->taxSubtotals[] = $taxSubtotals;

        return $this;
    }

    
}
