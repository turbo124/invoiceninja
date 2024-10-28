<?php

namespace App\Services\EDocument\Gateway\Transformers;

use App\Services\EDocument\Gateway\Storecove\Models\AccountingCustomerParty;
use App\Services\EDocument\Gateway\Storecove\Models\AccountingSupplierParty;
use App\Services\EDocument\Gateway\Storecove\Models\Address;
use App\Services\EDocument\Gateway\Storecove\Models\Contact;
use App\Services\EDocument\Gateway\Storecove\Models\Invoice as StorecoveInvoice;
use App\Services\EDocument\Gateway\Storecove\Models\Party;
use App\Services\EDocument\Gateway\Storecove\Models\References;

class StorecoveTransformer implements TransformerInterface
{
    private StorecoveInvoice $s_invoice;

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
            $this->s_invoice->setInvoicePeriod("{$peppolInvoice->InvoicePeriod[0]->StartDate} - {$peppolInvoice->InvoicePeriod[0]->EndDate}");
        }

        if($peppolInvoice->BuyerReference ?? false){
            $ref = new References(documentId: $peppolInvoice->BuyerReference, documentType: 'buyer_reference');
            $this->s_invoice->addReferences($ref);
        }
        
        if ($peppolInvoice->OrderReference->ID ?? false) {
            $ref = new References(documentId: $peppolInvoice->OrderReference->ID, documentType: 'sales_order');
            $this->s_invoice->addReferences($ref);
        }

        if($peppolInvoice->AccountingCostCode ?? false){
            $this->s_invoice->setAccountingCost($peppolInvoice->AccountingCostCode);
        }
        
        
        $customer_company_name = $peppolInvoice->AccountingCustomerParty->Party->PartyName[0]->Name ?? '';

        $address = new Address(
            street1: $peppolInvoice->AccountingCustomerParty->Party->PostalAddress->StreetName,
            street2: $peppolInvoice->AccountingCustomerParty->Party->PostalAddress->BuildingName ?? null,
            city: $peppolInvoice->AccountingCustomerParty->Party->PostalAddress->CityName,
            zip: $peppolInvoice->AccountingCustomerParty->Party->PostalAddress->PostalZone,
            county: $peppolInvoice->AccountingCustomerParty->Party->PostalAddress->CountrySubentity ?? null,
            country: $peppolInvoice->AccountingCustomerParty->Party->PostalAddress->Country->IdentificationCode,
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

        $supplier_part = new Party()
// $asp = new AccountingSupplierParty($party);


if (isset($peppolInvoice->AccountingSupplierParty->Party)) {
    $supplier = $peppolInvoice->AccountingSupplierParty->Party;
    $this->s_invoice->setAccountingSupplierParty([
        'name' => $supplier->PartyName[0]->Name ?? '',
        'vatNumber' => $supplier->PartyIdentification[0]->ID->value ?? '',
        'streetName' => $supplier->PostalAddress->StreetName ?? '',
        'cityName' => $supplier->PostalAddress->CityName ?? '',
        'postalZone' => $supplier->PostalAddress->PostalZone ?? '',
        'countryCode' => $supplier->PostalAddress->Country->IdentificationCode->value ?? '',
    ]);
}

// if (isset($peppolInvoice->AccountingCustomerParty->Party)) {
//     $customer = $peppolInvoice->AccountingCustomerParty->Party;
//     $this->s_invoice->setAccountingCustomerParty([
//         'name' => $customer->PartyName[0]->Name ?? '',
//         'vatNumber' => $customer->PartyIdentification[0]->ID->value ?? '',
//         'streetName' => $customer->PostalAddress->StreetName ?? '',
//         'cityName' => $customer->PostalAddress->CityName ?? '',
//         'postalZone' => $customer->PostalAddress->PostalZone ?? '',
//         'countryCode' => $customer->PostalAddress->Country->IdentificationCode->value ?? '',
//     ]);
// }

// if (isset($peppolInvoice->PaymentMeans[0])) {
//     $this->s_invoice->setPaymentMeans([
//         'paymentID' => $peppolInvoice->PaymentMeans[0]->PayeeFinancialAccount->ID->value ?? '',
//     ]);
// }

// // Map tax total at invoice level
// $taxTotal = [];
// if (isset($peppolInvoice->InvoiceLine[0]->TaxTotal[0])) {
//     $taxTotal[] = [
//         'taxAmount' => (float)($peppolInvoice->InvoiceLine[0]->TaxTotal[0]->TaxAmount->amount ?? 0),
//         'taxCurrency' => $peppolInvoice->DocumentCurrencyCode ?? '',
//     ];
// }
// $this->s_invoice->setTaxTotal($taxTotal);

// if (isset($peppolInvoice->InvoiceLine)) {
//     $invoiceLines = [];
//     foreach ($peppolInvoice->InvoiceLine as $line) {
//         $invoiceLine = new InvoiceLines();
//         $invoiceLine->setLineId($line->ID->value ?? '');
//         $invoiceLine->setAmountExcludingVat((float)($line->LineExtensionAmount->amount ?? 0));
//         $invoiceLine->setQuantity((float)($line->InvoicedQuantity ?? 0));
//         $invoiceLine->setQuantityUnitCode(''); // Not present in the provided JSON
//         $invoiceLine->setItemPrice((float)($line->Price->PriceAmount->amount ?? 0));
//         $invoiceLine->setName($line->Item->Name ?? '');
//         $invoiceLine->setDescription($line->Item->Description ?? '');

//         $tax = new Tax();
//         if (isset($line->TaxTotal[0])) {
//             $taxTotal = $line->TaxTotal[0];
//             $tax->setTaxAmount((float)($taxTotal->TaxAmount->amount ?? 0));

//             if (isset($line->Item->ClassifiedTaxCategory[0])) {
//                 $taxCategory = $line->Item->ClassifiedTaxCategory[0];
//                 $tax->setTaxPercentage((float)($taxCategory->Percent ?? 0));
//                 $tax->setTaxCategory($taxCategory->ID->value ?? '');
//             }

//             $tax->setTaxableAmount((float)($line->LineExtensionAmount->amount ?? 0));
//         }
//         $invoiceLine->setTax($tax);

//         $invoiceLines[] = $invoiceLine;
//     }
//     $this->s_invoice->setInvoiceLines($invoiceLines);
// }

// $this->s_invoice->setAmountIncludingVat((float)($peppolInvoice->LegalMonetaryTotal->TaxInclusiveAmount->amount ?? 0));

// return $this->s_invoice;

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