<?php

namespace App\Services\EDocument\Gateway\Transformers;

use App\Helpers\Invoice\Taxer;
use App\Utils\Traits\NumberFormatter;
use App\Services\EDocument\Gateway\Storecove\Models\Tax;
use App\Services\EDocument\Gateway\Storecove\Models\Party;
use App\Services\EDocument\Gateway\Storecove\Models\Address;
use App\Services\EDocument\Gateway\Storecove\Models\Contact;
use App\Services\EDocument\Gateway\Storecove\Models\References;
use App\Services\EDocument\Gateway\Storecove\Models\InvoiceLines;
use App\Services\EDocument\Gateway\Storecove\Models\PaymentMeans;
use App\Services\EDocument\Gateway\Storecove\Models\TaxSubtotals;
use App\Services\EDocument\Gateway\Storecove\Models\AllowanceCharges;
use App\Services\EDocument\Gateway\Storecove\Models\AccountingCustomerParty;
use App\Services\EDocument\Gateway\Storecove\Models\AccountingSupplierParty;
use App\Services\EDocument\Gateway\Storecove\Models\Invoice as StorecoveInvoice;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Support\Str;

class StorecoveTransformer implements TransformerInterface
{
    use Taxer;
    use NumberFormatter;

    private StorecoveInvoice $s_invoice;

    private array $tax_map = [];

    public function setInvoice($s_invoice): self
    {
        $this->s_invoice = $s_invoice;
        return $this;
    }

    public function getInvoice($s_invoice): StorecoveInvoice
    {
        return $this->s_invoice;
    }

    public function createNewStorecoveInvoice(): self
    {
        $this->s_invoice = (new \ReflectionClass(StorecoveInvoice::class))->newInstanceWithoutConstructor();
    }

    //$invoice = inbound peppol
    public function transform(mixed $invoice)
    {
    
        $this->s_invoice->setTaxPointDate($invoice->IssueDate->format('Y-m-d'));

        // Only use this if we are billing for services between a period.
        if (isset($invoice->InvoicePeriod[0]) && 
        isset($invoice->InvoicePeriod[0]->StartDate) && 
        isset($invoice->InvoicePeriod[0]->EndDate)) {
            $this->s_invoice->setInvoicePeriod("{$invoice->InvoicePeriod[0]->StartDate->format('Y-m-d')} - {$invoice->InvoicePeriod[0]->EndDate->format('Y-m-d')}");
        }

        $lines = [];

        foreach($invoice->InvoiceLine as $peppolLine)
        {

            // Tax handling
            if(isset($peppolLine->Item->ClassifiedTaxCategory) && is_array($peppolLine->Item->ClassifiedTaxCategory)){       
                foreach($peppolLine->Item->ClassifiedTaxCategory as $ctc)
                {
                    $this->setTaxMap($ctc, $peppolLine, $invoice);
                }
            }

    //         //discounts 
    //         if(isset($peppolLine->Price->AllowanceCharge) && is_array($peppolLine->Price->AllowanceCharge)){       
            
    //             foreach($peppolLine->Price->AllowanceCharge as $allowance)
    //             {
    //                 $reason = isset($allowance->ChargeIndicator) ? ctrans('texts.discount') : ctrans('texts.fee');
    //                 $amount = $allowance->Amount->amount;

    //                 $ac = new AllowanceCharges(reason: $reason, amountExcludingTax: $amount);
    //                 $line->addAllowanceCharge($ac);
    //             }
    //         }


    //         $lines[] = $line;
    
    //     }

    //     $this->s_invoice->invoiceLines = $lines;


        }

        $sub_taxes = collect($this->tax_map)
            ->groupBy('percentage')
            ->map(function ($group) {

                return new TaxSubtotals(
                    taxable_amount: $group->sum('taxableAmount'),
                    tax_amount: $group->sum('taxAmount'),
                    percentage: $group->first()['percentage'],
                    country: $group->first()['country'],
                    category: null
                );

            })->toArray();

            
            $this->s_invoice->setTaxSubtotals($sub_taxes);


    //     $this->s_invoice->setAmountIncludingVat($invoice->LegalMonetaryTotal->TaxInclusiveAmount->amount);
    //     $this->s_invoice->setPrepaidAmount(0);
       
        return $this->s_invoice;

    }

    private function setTaxMap($ctc, $peppolLine, $invoice): self
    {
        $taxAmount = 0;
        $taxableAmount = 0;

        foreach($peppolLine->Item as $item)
        {
             
            $_taxAmount = $this->calcAmountLineTax($ctc->Percent, $peppolLine->LineExtensionAmount->amount);

            $taxAmount += $_taxAmount;
            $taxableAmount += $peppolLine->LineExtensionAmount->amount;

        }

        $this->tax_map[] = [
            'percentage' => $ctc->Percent, 
            'country' => $this->resolveJurisdication($ctc, $invoice), 
            'taxAmount' => $taxAmount, 
            'taxableAmount' => $taxableAmount,
        ]; 
                   
        return $this;

    }

    private function resolveJurisdication($ctc, $invoice): string 
    {
        if(isset($ctc->TaxTotal[0]->JurisdictionRegionAddress->Country->IdentificationCode->value))
            return $ctc->TaxTotal[0]->JurisdictionRegionAddress->Country->IdentificationCode->value;

        return $invoice->AccountingSupplierParty->Party->PostalAddress->Country->IdentificationCode->value;
    }

    public function buildDocument(): mixed
    {
        $doc = new \stdClass;
        $doc->document->documentType = "invoice";
        $doc->document->invoice = $this->getInvoice();
        $doc->attachments = [];
        $doc->legalEntityId = '';
        $doc->idempotencyGuid = Str::uuid();
        $doc->routing->eIdentifiers = [];
        $doc->emails = [];
        
        return $doc;
    }

    public function getInvoice(): StorecoveInvoice
    {
        return $this->s_invoice;
    }

    public function toJson(): string
    {
        return json_encode($this->s_invoice, JSON_PRETTY_PRINT);
    }
}