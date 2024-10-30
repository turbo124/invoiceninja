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

class StorecoveTransformer implements TransformerInterface
{
    use Taxer;
    use NumberFormatter;

    private StorecoveInvoice $s_invoice;

    private array $tax_map = [];

    public function transform(mixed $peppolInvoice)
    {
    
        $this->s_invoice = new StorecoveInvoice();

        $this->s_invoice->setDocumentCurrency($peppolInvoice->DocumentCurrencyCode ?? '');
        $this->s_invoice->setInvoiceNumber($peppolInvoice->ID ?? '');
        $this->s_invoice->setIssueDate($peppolInvoice->IssueDate->format('Y-m-d'));
        $this->s_invoice->setTaxPointDate($peppolInvoice->IssueDate->format('Y-m-d'));
        $this->s_invoice->setDueDate($peppolInvoice->DueDate->format('Y-m-d') ?? '');
        $this->s_invoice->setNote($peppolInvoice->Note ?? '');

        // Only use this if we are billing for services between a period.
        if (isset($peppolInvoice->InvoicePeriod[0]) && 
        isset($peppolInvoice->InvoicePeriod[0]->StartDate) && 
        isset($peppolInvoice->InvoicePeriod[0]->EndDate)) {
            $this->s_invoice->setInvoicePeriod("{$peppolInvoice->InvoicePeriod[0]->StartDate->format('Y-m-d')} - {$peppolInvoice->InvoicePeriod[0]->EndDate->format('Y-m-d')}");
        }

        if($peppolInvoice->BuyerReference ?? false){
            $ref = new References(documentId: $peppolInvoice->BuyerReference, documentType: 'buyer_reference');
            $this->s_invoice->addReferences($ref);
        }
        
        if ($peppolInvoice->OrderReference->ID ?? false) {
            $ref = new References(documentId: $peppolInvoice->OrderReference->ID->value, documentType: 'sales_order');
            $this->s_invoice->addReferences($ref);
        }

        if($peppolInvoice->AccountingCostCode ?? false){
            $this->s_invoice->setAccountingCost($peppolInvoice->AccountingCostCode);
        }
        
        $customer_company_name = $peppolInvoice->AccountingCustomerParty->Party->PartyName[0]->Name ?? '';

        $address = new Address(
            street1: $peppolInvoice->AccountingCustomerParty->Party->PostalAddress->StreetName,
            street2: $peppolInvoice->AccountingCustomerParty->Party->PostalAddress->AdditionalStreetName ?? null,
            city: $peppolInvoice->AccountingCustomerParty->Party->PostalAddress->CityName,
            zip: $peppolInvoice->AccountingCustomerParty->Party->PostalAddress->PostalZone,
            county: $peppolInvoice->AccountingCustomerParty->Party->PostalAddress->CountrySubentity ?? null,
            country: $peppolInvoice->AccountingCustomerParty->Party->PostalAddress->Country->IdentificationCode->value,
        );

        $contact = new Contact(
            email: $peppolInvoice->AccountingCustomerParty->Party->Contact->ElectronicMail, 
            firstName: $peppolInvoice->AccountingCustomerParty->Party->Contact->Name ?? null, 
            phone: $peppolInvoice->AccountingCustomerParty->Party->Contact->Telephone ?? null,
        );
        
        $customer_party = new Party(companyName: $customer_company_name, address: $address, contact: $contact);
        $party_identifiers = []; // do this outside the transformer.
        $acp = new AccountingCustomerParty($party_identifiers, $customer_party);
        $this->s_invoice->setAccountingCustomerParty($acp);
        

        $supplier_contact = new Contact(
            email: $peppolInvoice->AccountingSupplierParty->Party->Contact->ElectronicMail,
            firstName: $peppolInvoice->AccountingSupplierParty->Party->Contact->Name ?? null,
            phone: $peppolInvoice->AccountingSupplierParty->Party->Contact->Telephone ?? null,
        );

        $supplier_party = new Party(contact: $supplier_contact);
        $asp = new AccountingSupplierParty($supplier_party);
        $this->s_invoice->setAccountingSupplierParty($asp);

        if (isset($peppolInvoice->PaymentMeans[0])) {

            $payment_means = new PaymentMeans();
            $payment_means->setCodeProps($peppolInvoice->PaymentMeans[0]);

            $this->s_invoice->addPaymentMeans($payment_means);
        }

        $lines = [];

        foreach($peppolInvoice->InvoiceLine as $peppolLine)
        {

            $line = new InvoiceLines();

            // Basic line details
            $line->setLineId($peppolLine->ID->value);
            $line->setQuantity((int)$peppolLine->InvoicedQuantity->amount);
            $line->setItemPrice((float)$peppolLine->Price->PriceAmount->amount);
            $line->setAmountExcludingVat((float)$peppolLine->LineExtensionAmount->amount);

            // Item details
            $line->setName($peppolLine->Item->Name);
            $line->setDescription($peppolLine->Item->Description);

            // Tax handling
            if(isset($peppolLine->Item->ClassifiedTaxCategory) && is_array($peppolLine->Item->ClassifiedTaxCategory)){       
                foreach($peppolLine->Item->ClassifiedTaxCategory as $ctc)
                {
                    $this->setTaxMap($ctc, $peppolLine, $peppolInvoice);
                    $tax = new Tax((float)$ctc->Percent, $this->resolveJurisdication($ctc, $peppolInvoice));
                    $line->setTax($tax);
                }
            }

            //discounts 
            if(isset($peppolLine->Price->AllowanceCharge) && is_array($peppolLine->Price->AllowanceCharge)){       
            
                foreach($peppolLine->Price->AllowanceCharge as $allowance)
                {
                    $reason = isset($allowance->ChargeIndicator) ? ctrans('texts.discount') : ctrans('texts.fee');
                    $amount = $allowance->Amount->amount;

                    $ac = new AllowanceCharges(reason: $reason, amountExcludingTax: $amount);
                    $line->addAllowanceCharge($ac);
                }
            }


            $lines[] = $line;
    
        }

        $this->s_invoice->invoiceLines = $lines;

        //invoice level discounts + surcharges
        if(isset($peppolLine->AllowanceCharge) && is_array($peppolLine->AllowanceCharge)){    

            foreach ($peppolLine->AllowanceCharge as $allowance)
            {
                                
                $reason = $allowance->ChargeIndicator ? ctrans('texts.fee') : ctrans('texts.discount');
                $amount = $allowance->Amount->amount;

                $ac = new AllowanceCharges(reason: $reason, amountExcludingTax: $amount);
                $this->s_invoice->addAllowanceCharge($ac); //todo handle surcharge taxes

            }
        }

        
        collect($this->tax_map)
            ->groupBy('percentage')
            ->each(function ($group) {

                $taxSubtotals = new TaxSubtotals(
                    taxableAmount: $group->sum('taxableAmount'),
                    taxAmount: $group->sum('taxAmount'),
                    percentage: $group->first()['percentage'],
                    country: $group->first()['country']
                );

                $this->s_invoice->addTaxSubtotals($taxSubtotals);


            });
            
        $this->s_invoice->setAmountIncludingVat($peppolInvoice->LegalMonetaryTotal->TaxInclusiveAmount->amount);
        $this->s_invoice->setPrepaidAmount(0);
       
        return $this->s_invoice;

    }

    private function setTaxMap($ctc, $peppolLine, $peppolInvoice): self
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
            'country' => $this->resolveJurisdication($ctc, $peppolInvoice), 
            'taxAmount' => $taxAmount, 
            'taxableAmount' => $taxableAmount,
        ]; 
                   
        return $this;

    }

    private function resolveJurisdication($ctc, $peppolInvoice): string 
    {
        if(isset($ctc->TaxTotal[0]->JurisdictionRegionAddress->Country->IdentificationCode->value))
            return $ctc->TaxTotal[0]->JurisdictionRegionAddress->Country->IdentificationCode->value;

        return $peppolInvoice->AccountingSupplierParty->Party->PostalAddress->Country->IdentificationCode->value;
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