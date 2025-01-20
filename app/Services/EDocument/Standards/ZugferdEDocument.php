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

namespace App\Services\EDocument\Standards;

use DateTime;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\DataMapper\InvoiceItem;
use App\Services\AbstractService;
use App\Helpers\Invoice\InvoiceSum;
use horstoeko\zugferd\ZugferdProfiles;
use App\Helpers\Invoice\InvoiceSumInclusive;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\codelists\ZugferdDocumentType;
use horstoeko\zugferd\codelists\ZugferdDutyTaxFeeCategories;

class ZugferdEDocument extends AbstractService
{
    public ZugferdDocumentBuilder $xdocument;

    private Company $company;

    private Client $client;

    private InvoiceSum | InvoiceSumInclusive $calc;
    /**
     * __construct
     *
     * @param \App\Models\Invoice | \App\Models\Quote | \App\Models\PurchaseOrder | \App\Models\Credit $document
     * @param  bool $returnObject
     * @param  array $tax_map
     * @return void
     */
    public function __construct(public \App\Models\Invoice | \App\Models\Quote | \App\Models\PurchaseOrder | \App\Models\Credit $document, private readonly bool $returnObject = false, private array $tax_map = [])
    {
    }

    public function run(): self
    {

        /** @var \App\Models\Company $company */
        $this->company = $this->document->company;

        /** @var \App\Models\Client $client */
        $this->client = $this->document->client;

        $profile = $this->client->getSetting('e_invoice_type');

        $profile = match ($profile) {
            "XInvoice_3_0" => ZugferdProfiles::PROFILE_XRECHNUNG_3,
            "XInvoice_2_3" => ZugferdProfiles::PROFILE_XRECHNUNG_2_3,
            "XInvoice_2_2" => ZugferdProfiles::PROFILE_XRECHNUNG_2_2,
            "XInvoice_2_1" => ZugferdProfiles::PROFILE_XRECHNUNG_2_1,
            "XInvoice_2_0" => ZugferdProfiles::PROFILE_XRECHNUNG_2,
            "XInvoice_1_0" => ZugferdProfiles::PROFILE_XRECHNUNG,
            "XInvoice-Extended" => ZugferdProfiles::PROFILE_EXTENDED,
            "XInvoice-BasicWL" => ZugferdProfiles::PROFILE_BASICWL,
            "XInvoice-Basic" => ZugferdProfiles::PROFILE_BASIC,
            default => ZugferdProfiles::PROFILE_EN16931,
        };

        $this->xdocument = ZugferdDocumentBuilder::CreateNew($profile);


        $this->bootFlags()
            ->setBaseDocument()
            ->setDocumentInformation()
            ->setPoNumber()
            ->setRoutingNumber()
            ->setDeliveryAddress()
            ->setDocumentTaxes()        // 1. First set taxes
            ->setPaymentMeans()         // 2. Then payment means
            ->setPaymentTerms()         // 3. Then payment terms
            ->setLineItems()            // 4. Then line items
            ->setDocumentSummation();   // 5. Finally document summation

        return $this;

    }


    private function setDocumentTaxes(): self
    {
        if ($this->document->total_taxes == 0) {
            $this->xdocument->addDocumentTax(
                ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX,
                "VAT",
                0,
                0,
                0,
                ctrans('texts.vat_not_registered'),
                "VATNOTREG"
            );

            return $this;
        }

        // Get document level discount
        // $document_discount = $this->calc->getTotalDiscount();
        // $total_taxable = $this->getTaxable();
        $net_subtotal = $this->calc->getNetSubTotal();
        
        // Process each tax rate group
        foreach ($this->calc->getTaxMap() as $item) {
            $tax_type = $this->getTaxType($item["tax_id"]);
            // Add tax information
            $this->xdocument->addDocumentTax(
                $tax_type,
                "VAT",
                $item["base_amount"], // Taxable amount after discount
                $item["total"],
                $item["tax_rate"],
                $tax_type == ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES
                    ? ctrans('texts.intracommunity_tax_info')
                    : ''
            );
                
            if ($this->calc->getTotalDiscount() > 0) {

                $ratio = $item["base_amount"] / $net_subtotal;

                $this->xdocument->addDocumentAllowanceCharge(
                    round($this->calc->getTotalDiscount() * $ratio, 2),
                    false,
                    $tax_type,
                    "VAT",
                    $item["tax_rate"]
                );
            }

        
        
        }

        return $this;
    }

    private function setPaymentTerms(): self
    {
        $this->xdocument->addDocumentPaymentTerm(
            ctrans("texts.xinvoice_payable", [
                'payeddue' => date_create($this->document->date ?? now()->format('Y-m-d'))
                    ->diff(date_create($this->document->due_date ?? now()->format('Y-m-d')))
                    ->format("%d"),
                'paydate' => $this->document->due_date
            ])
        );

        return $this;
    }

    public function getDocument()
    {
        return $this->xdocument;
    }

    public function getXml(): string
    {
        return $this->xdocument->getContent();
    }

    private function bootFlags(): self
    {

        $this->calc = $this->document->calc();

        return $this;
    }

    private function setDocumentSummation(): self
    {
        $document_discount = $this->calc->getTotalDiscount();
        $total_tax = $this->calc->getTotalTaxes();
        $subtotal = $this->calc->getTotal() - $total_tax;

        // Calculate amounts after discount
        $taxable_amount = $this->getTaxable();
        
        nlog([
             $this->document->amount,                    // Total amount with VAT
            $this->document->balance,                   // Amount due
            $this->calc->getSubTotal(),                                  // Sum before tax
            $this->calc->getTotalSurcharges(),         // Total charges
            $document_discount,                         // Total allowances
            $taxable_amount,                           // Tax basis total (net)
            $total_tax,                                // Total tax amount
            0.0,                                       // Total prepaid amount
            $this->document->amount - $this->document->balance, 
        ]);
        
        $this->xdocument->setDocumentSummation(
            $this->document->amount,                    // Total amount with VAT
            $this->document->balance,                   // Amount due
            $this->calc->getSubTotal(),                                  // Sum before tax
            $this->calc->getTotalSurcharges(),         // Total charges
            $document_discount,                         // Total allowances
            $taxable_amount,                           // Tax basis total (net)
            $total_tax,                                // Total tax amount
            0.0,                                       // Total prepaid amount
            $this->document->amount - $this->document->balance  // Amount already paid
        );

        return $this;
    }
        
    private function setLineItems(): self
    {
        foreach ($this->document->line_items as $index => $item) {
            /** @var InvoiceItem $item **/
            
            // 1. Start new position and set basic details
            $this->xdocument->addNewPosition($index)
                ->setDocumentPositionProductDetails(
                    strlen($item->product_key ?? '') >= 1 ? $item->product_key : "no product name defined", 
                    $item->notes
                )
                ->setDocumentPositionQuantity(
                    $item->quantity, 
                    $item->type_id == 2 ? "HUR" : "H87"
                )
                ->setDocumentPositionNetPrice(
                    $this->document->uses_inclusive_taxes ? $item->net_cost : $item->cost
                );
                
            // 2. ALWAYS add tax information (even if zero)
            if(strlen($item->tax_name1) > 1) {
                $this->xdocument->addDocumentPositionTax(
                    $this->getTaxType($item->tax_id ?? '2'), 
                    'VAT', 
                    $item->tax_rate1
                );
            } else {
                // Add zero tax if no tax is specified
                $this->xdocument->addDocumentPositionTax(
                    ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX,
                    'VAT',
                    0
                );
            }

            // 3. Add allowances/charges (discounts) if any
            if($item->discount > 0) {
                $line_discount = $this->calculateTotalItemDiscountAmount($item);
                $this->xdocument->addDocumentPositionGrossPriceAllowanceCharge(
                    abs($line_discount), 
                    false
                );
            }

            // 4. Finally add monetary summation
            $this->xdocument->setDocumentPositionLineSummation($item->line_total);
        }

        return $this;
    }

    private function calculateTotalItemDiscountAmount($item): float
    {
        if ($item->is_amount_discount) {
            return $item->discount;
        }

        return ($item->cost * $item->quantity) * ($item->discount / 100);
    }


    private function setClientTaxRegistration(): self
    {
        if (empty($this->client->vat_number)) {

            return $this;

        }

        $this->xdocument->addDocumentBuyerTaxRegistration("VA", $this->client->vat_number);

        return $this;

    }

    private function setCompanyTaxRegistration(): array
    {
        if (str_contains($this->company->getSetting('vat_number'), "/")) 
            return ["FC", $this->company->getSetting('vat_number')];
    
        return ["VA", $this->company->getSetting('vat_number')];      
    }

    private function setPaymentMeans(): self
    {

        /**Check if the e_invoice object is populated */
        if (isset($this->company->e_invoice->Invoice->PaymentMeans) && ($pm = $this->company->e_invoice->Invoice->PaymentMeans[0] ?? false)) {

            switch ($pm->PaymentMeansCode->value ?? false) {
                case '30':
                case '58':
                    $iban = $pm->PayeeFinancialAccount->ID->value;
                    $name = $pm->PayeeFinancialAccount->Name ?? '';
                    $bic = $pm->PayeeFinancialAccount->FinancialInstitutionBranch->FinancialInstitution->ID->value ?? '';
                    $typecode = $pm->PaymentMeansCode->value;

                    $this->xdocument->addDocumentPaymentMean(typeCode: $typecode, payeeIban: $iban, payeeAccountName: $name, payeeBic: $bic);

                    return $this;

                default:
                    # code...
                    break;
            }

        }

        //Otherwise default to the "old style"

        $custom_value1 = $this->company->settings->custom_value1;
        //BR-DE-23 - If „Payment means type code“ (BT-81) contains a code for credit transfer (30, 58), „CREDIT TRANSFER“ (BG-17) shall be provided.
        //Payment Means - Switcher
        if (isset($custom_value1) && !empty($custom_value1) && ($custom_value1 == '30' || $custom_value1 == '58')) {
            $this->xdocument->addDocumentPaymentMean(typeCode: $this->company->settings->custom_value1, payeeIban: $this->company->settings->custom_value2, payeeAccountName: $this->company->settings->custom_value4, payeeBic: $this->company->settings->custom_value3);
        } else {
            $this->xdocument->addDocumentPaymentMean('68', ctrans("texts.xinvoice_online_payment"));
        }

        return $this;

    }

    private function setDeliveryAddress(): self
    {

        if (isset($client->shipping_address1) && $client->shipping_country) {
            $this->xdocument->setDocumentShipToAddress(
                $this->client->shipping_address1,
                $this->client->shipping_address2,
                "",
                $this->client->shipping_postal_code,
                $this->client->shipping_city,
                $this->client->shipping_country->iso_3166_2,
                $this->client->shipping_state
            );
        }

        return $this;
    }

    private function setDocumentInformation(): self
    {
        $this->xdocument->setDocumentInformation(
            $this->getDocumentNumber(),
            $this->getDocumentType(),
            $this->getDocumentDate(),
            $this->getDocumentCurrency()
        );

        return $this;
    }

    private function setBaseDocument(): self
    {

        $user_or_company_phone = strlen($this->document->user->present()->phone()) > 3 ? $this->document->user->present()->phone() : $this->company->present()->phone();

        $company_tax_registration = $this->setCompanyTaxRegistration();

        $this->xdocument
            ->setDocumentSupplyChainEvent($this->getDocumentDate())
            ->setDocumentSeller($this->company->getSetting('name'))
            ->setDocumentSellerAddress($this->company->getSetting("address1"), $this->company->getSetting("address2"), "", $this->company->getSetting("postal_code"), $this->company->getSetting("city"), $this->company->country()->iso_3166_2, $this->company->getSetting("state"))
            ->setDocumentSellerContact($this->document->user->present()->getFullName(), "", $user_or_company_phone, "", $this->document->user->email)
            ->setDocumentSellerCommunication("EM", $this->document->user->email)
            ->addDocumentSellerTaxRegistration($company_tax_registration[0], $company_tax_registration[1])
            ->setDocumentBuyer($this->client->present()->name(), $this->client->number)
            ->setDocumentBuyerAddress($this->client->address1, "", "", $this->client->postal_code, $this->client->city, $this->client->country->iso_3166_2, $this->client->state)
            ->setDocumentBuyerContact($this->client->present()->primary_contact_name(), "", $this->client->present()->phone(), "", $this->client->present()->email())
            ->setDocumentBuyerCommunication("EM", $this->client->present()->email())
            ->addDocumentPaymentTerm(ctrans("texts.xinvoice_payable", ['payeddue' => date_create($this->document->date ?? now()->format('Y-m-d'))->diff(date_create($this->document->due_date ?? now()->format('Y-m-d')))->format("%d"), 'paydate' => $this->document->due_date]));


        return $this;
    }

    private function setRoutingNumber(): self
    {
        if (empty($this->client->routing_id)) {
            $this->xdocument->setDocumentBuyerReference(ctrans("texts.xinvoice_no_buyers_reference"));
        } else {
            $this->xdocument->setDocumentBuyerReference($this->client->routing_id)
                 ->setDocumentBuyerCommunication("0204", $this->client->routing_id);
        }
        return $this;
    }

    private function setPoNumber(): self
    {
        if (isset($this->document->po_number) && strlen($this->document->po_number) > 1) {
            $this->xdocument->setDocumentBuyerOrderReferencedDocument($this->document->po_number);
        }

        return $this;
    }

    //////////////////Getters//////////////////
    private function getDocumentNumber(): string
    {
        return empty($this->document->number) ? "DRAFT" : $this->document->number;
    }

    private function getDocumentType(): string
    {
        return match (get_class($this->document)) {
            Quote::class => ZugferdDocumentType::CONTRACT_PRICE_QUOTE,
            Invoice::class => ZugferdDocumentType::COMMERCIAL_INVOICE,
            Credit::class => ZugferdDocumentType::CREDIT_NOTE,
            default => ZugferdDocumentType::COMMERCIAL_INVOICE,
        };
    }

    private function getDocumentDate(): ?DateTime
    {
        return date_create($this->document->date ?? now()->format('Y-m-d'));
    }

    private function getDocumentCurrency(): string
    {
        return $this->client->getCurrencyCode();
    }

    private function getTaxType(string $tax_id): string
    {

        switch ($tax_id) {
            case Product::PRODUCT_TYPE_SERVICE:
            case Product::PRODUCT_TYPE_DIGITAL:
            case Product::PRODUCT_TYPE_PHYSICAL:
            case Product::PRODUCT_TYPE_SHIPPING:
            case Product::PRODUCT_TYPE_REDUCED_TAX:
                $tax_type = ZugferdDutyTaxFeeCategories::STANDARD_RATE;
                break;
            case Product::PRODUCT_TYPE_EXEMPT:
                $tax_type =  ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX;
                break;
            case Product::PRODUCT_TYPE_ZERO_RATED:
                $tax_type = ZugferdDutyTaxFeeCategories::ZERO_RATED_GOODS;
                break;
            case Product::PRODUCT_TYPE_REVERSE_TAX:
                $tax_type = ZugferdDutyTaxFeeCategories::VAT_REVERSE_CHARGE;
                break;

            default:
                $tax_type = null;
                break;
        }

        if ($this->client->is_tax_exempt) {
            $tax_type = ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX;
        }

        $br = new \App\DataMapper\Tax\BaseRule();
        $eu_states = $br->eu_country_codes;

        if (empty($tax_type)) {
            if ((in_array($this->company->country()->iso_3166_2, $eu_states) && in_array($this->client->country->iso_3166_2, $eu_states)) && $this->company->country()->iso_3166_2 != $this->client->country->iso_3166_2) {
                $tax_type = ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES;
            } elseif (!in_array($this->document->client->country->iso_3166_2, $eu_states)) {
                $tax_type = ZugferdDutyTaxFeeCategories::SERVICE_OUTSIDE_SCOPE_OF_TAX;
            } elseif ($this->document->client->country->iso_3166_2 == "ES-CN") {
                $tax_type = ZugferdDutyTaxFeeCategories::CANARY_ISLANDS_GENERAL_INDIRECT_TAX;
            } elseif (in_array($this->document->client->country->iso_3166_2, ["ES-CE", "ES-ML"])) {
                $tax_type = ZugferdDutyTaxFeeCategories::TAX_FOR_PRODUCTION_SERVICES_AND_IMPORTATION_IN_CEUTA_AND_MELILLA;
            } else {
                nlog("Unkown tax case for xinvoice");
                $tax_type = ZugferdDutyTaxFeeCategories::STANDARD_RATE;
            }
        }

        return $tax_type;
    }

    private function getTaxable(): float
    {
        $total = 0;

        foreach ($this->document->line_items as $item) {
            $line_total = $item->quantity * $item->cost;

            if ($item->discount != 0) {
                if ($this->document->is_amount_discount) {
                    $line_total -= $item->discount;
                } else {
                    $line_total -= $line_total * $item->discount / 100;
                }
            }

            $total += $line_total;
        }

        $total = round($total, 2);

        if ($this->document->discount > 0) {
            if ($this->document->is_amount_discount) {
                $total -= $this->document->discount;
            } else {
                $total *= (100 - $this->document->discount) / 100;

            }
        }

        //** Surcharges are taxable regardless, if control is needed over taxable components, add it as a line item! */
        if ($this->document->custom_surcharge1 > 0) {
            $total += $this->document->custom_surcharge1;
        }

        if ($this->document->custom_surcharge2 > 0) {
            $total += $this->document->custom_surcharge2;
        }

        if ($this->document->custom_surcharge3 > 0) {
            $total += $this->document->custom_surcharge3;
        }

        if ($this->document->custom_surcharge4 > 0) {
            $total += $this->document->custom_surcharge4;
        }

        return round($total, 2);
    }
}
