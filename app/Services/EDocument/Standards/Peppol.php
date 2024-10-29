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

use App\DataMapper\Tax\BaseRule;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Helpers\Invoice\Taxer;
use App\Services\AbstractService;
use App\Helpers\Invoice\InvoiceSum;
use InvoiceNinja\EInvoice\EInvoice;
use App\Utils\Traits\NumberFormatter;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Exceptions\PeppolValidationException;
use App\Services\EDocument\Standards\Peppol\RO;
use App\Http\Requests\Client\StoreClientRequest;
use App\Services\EDocument\Gateway\Qvalia\Qvalia;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use InvoiceNinja\EInvoice\Models\Peppol\ItemType\Item;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use InvoiceNinja\EInvoice\Models\Peppol\PartyType\Party;
use InvoiceNinja\EInvoice\Models\Peppol\PriceType\Price;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\AddressType\Address;
use InvoiceNinja\EInvoice\Models\Peppol\ContactType\Contact;
use InvoiceNinja\EInvoice\Models\Peppol\CountryType\Country;
use InvoiceNinja\EInvoice\Models\Peppol\PartyIdentification;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxAmount;
use InvoiceNinja\EInvoice\Models\Peppol\Party as PeppolParty;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotalType\TaxTotal;
use App\Services\EDocument\Standards\Settings\PropertyResolver;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PriceAmount;
use InvoiceNinja\EInvoice\Models\Peppol\PartyNameType\PartyName;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PayableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\PeriodType\InvoicePeriod;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotal as PeppolTaxTotal;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\IdentificationCode;
use InvoiceNinja\EInvoice\Models\Peppol\InvoiceLineType\InvoiceLine;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSubtotalType\TaxSubtotal;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxExclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxInclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\LocationType\PhysicalLocation;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\LineExtensionAmount;
use InvoiceNinja\EInvoice\Models\Peppol\OrderReferenceType\OrderReference;
use InvoiceNinja\EInvoice\Models\Peppol\MonetaryTotalType\LegalMonetaryTotal;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\ClassifiedTaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CustomerAssignedAccountID;
use InvoiceNinja\EInvoice\Models\Peppol\CustomerPartyType\AccountingCustomerParty;
use InvoiceNinja\EInvoice\Models\Peppol\SupplierPartyType\AccountingSupplierParty;
use InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount;

class Peppol extends AbstractService
{
    use Taxer;
    use NumberFormatter;

    /**
     * Assumptions:
     *
     * Line Item Taxes Only
     * Exclusive Taxes
     *
     */
    
    /** @var array $InvoiceTypeCodes */
    private array $InvoiceTypeCodes = [
        "380" => "Commercial invoice",
        "381" => "Credit note",
        "383" => "Corrected invoice",
        "384" => "Prepayment invoice",
        "386" => "Proforma invoice",
        "875" => "Self-billed invoice",
        "976" => "Factored invoice",
        "84" => "Invoice for cross border services",
        "82" => "Simplified invoice",
        "80" => "Debit note",
        "875" => "Self-billed credit note",
        "896" => "Debit note related to self-billed invoice"
    ];
    
    /** @var array $tax_codes */
    private array $tax_codes = [
        'AE' => [
            'name' => 'Vat Reverse Charge',
            'description' => 'Code specifying that the standard VAT rate is levied from the invoicee.'
        ],
        'E' => [
            'name' => 'Exempt from Tax',
            'description' => 'Code specifying that taxes are not applicable.'
        ],
        'S' => [
            'name' => 'Standard rate',
            'description' => 'Code specifying the standard rate.'
        ],
        'Z' => [
            'name' => 'Zero rated goods',
            'description' => 'Code specifying that the goods are at a zero rate.'
        ],
        'G' => [
            'name' => 'Free export item, VAT not charged',
            'description' => 'Code specifying that the item is free export and taxes are not charged.'
        ],
        'O' => [
            'name' => 'Services outside scope of tax',
            'description' => 'Code specifying that taxes are not applicable to the services.'
        ],
        'K' => [
            'name' => 'VAT exempt for EEA intra-community supply of goods and services',
            'description' => 'A tax category code indicating the item is VAT exempt due to an intra-community supply in the European Economic Area.'
        ],
        'L' => [
            'name' => 'Canary Islands general indirect tax',
            'description' => 'Impuesto General Indirecto Canario (IGIC) is an indirect tax levied on goods and services supplied in the Canary Islands (Spain) by traders and professionals, as well as on import of goods.'
        ],
        'M' => [
            'name' => 'Tax for production, services and importation in Ceuta and Melilla',
            'description' => 'Impuesto sobre la Producción, los Servicios y la Importación (IPSI) is an indirect municipal tax, levied on the production, processing and import of all kinds of movable tangible property, the supply of services and the transfer of immovable property located in the cities of Ceuta and Melilla.'
        ],
        'B' => [
            'name' => 'Transferred (VAT), In Italy',
            'description' => 'VAT not to be paid to the issuer of the invoice but directly to relevant tax authority. This code is allowed in the EN 16931 for Italy only based on the Italian A-deviation.'
        ]
    ];
    
    /** @var Company $company */
    private Company $company;

    private InvoiceSum | InvoiceSumInclusive $calc;

    private \InvoiceNinja\EInvoice\Models\Peppol\Invoice $p_invoice;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_client_settings;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_company_settings;

    private EInvoice $e;

    private string $api_network = Storecove::class; // Storecove::class; // Qvalia::class;

    public Qvalia | Storecove $gateway;

    private string $customizationID = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    private string $profileID = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';    

    /** @var array $tax_map */
    private array $tax_map = [];

    /**
    * @param Invoice $invoice
    */
    public function __construct(public Invoice $invoice)
    {
        $this->company = $invoice->company;
        $this->calc = $this->invoice->calc();
        $this->e = new EInvoice();
        $this->gateway = new $this->api_network;
        $this->setSettings()->setInvoice();
    }
    
    /**
     * Entry point for building document
     *
     * @return self
     */
    public function run(): self
    {
        /** Invoice Level Props */
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CustomizationID();
        $id->value = $this->customizationID;
        $this->p_invoice->CustomizationID = $id;

        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ProfileID();
        $id->value = $this->profileID;
        $this->p_invoice->ProfileID = $id;

        $this->p_invoice->ID = $this->invoice->number;
        $this->p_invoice->IssueDate = new \DateTime($this->invoice->date);

        if($this->invoice->due_date) 
            $this->p_invoice->DueDate = new \DateTime($this->invoice->due_date);

        if(strlen($this->invoice->public_notes ?? '') > 0)
            $this->p_invoice->Note = $this->invoice->public_notes;

        $this->p_invoice->DocumentCurrencyCode = $this->invoice->client->currency()->code;

        if ($this->invoice->date && $this->invoice->due_date) {
            $ip = new InvoicePeriod();
            $ip->StartDate = new \DateTime($this->invoice->date);
            $ip->EndDate = new \DateTime($this->invoice->due_date);
            $this->p_invoice->InvoicePeriod[] = $ip;
        }
        
        if ($this->invoice->project_id) {
            $pr = new \InvoiceNinja\EInvoice\Models\Peppol\ProjectReferenceType\ProjectReference();
            $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
            $id->value = $this->invoice->project->number;
            $pr->ID = $id;
            $this->p_invoice->ProjectReference[] = $pr;
        }

        /** Auto switch between Invoice / Credit based on the amount value */
        $this->p_invoice->InvoiceTypeCode = ($this->invoice->amount >= 0) ? 380 : 381;

        $this->p_invoice->AccountingSupplierParty = $this->getAccountingSupplierParty();
        $this->p_invoice->AccountingCustomerParty = $this->getAccountingCustomerParty();
        $this->p_invoice->InvoiceLine = $this->getInvoiceLines();
        $this->p_invoice->LegalMonetaryTotal = $this->getLegalMonetaryTotal();
        $this->p_invoice->AllowanceCharge = $this->getAllowanceCharges();

        $this->setOrderReference()->setTaxBreakdown();

        $this->p_invoice = $this->gateway
                                ->mutator
                                ->senderSpecificLevelMutators()
                                ->receiverSpecificLevelMutators()
                                ->getPeppol();
                                
        //** @todo double check this logic, this will only ever write the doc once */
        if(strlen($this->invoice->backup ?? '') == 0)
        {
            $this->invoice->e_invoice = $this->toObject();
            $this->invoice->save();
        }

        return $this;

    }
    
    /**
     * Transforms a stdClass Invoice
     * to Peppol\Invoice::class
     *
     * @param  mixed $invoice
     * @return self
     */
    public function decode(mixed $invoice):self
    {
        $this->p_invoice = $this->e->decode('Peppol', json_encode($invoice), 'json');

        return $this;
    }

    /**
     * Rehydrates an existing e invoice - or - scaffolds a new one
     *
     * @return self
     */
    private function setInvoice(): self
    {

        if($this->invoice->e_invoice && isset($this->invoice->e_invoice->Invoice)) {

        $this->decode($this->invoice->e_invoice->Invoice);

        $this->gateway
            ->mutator
            ->setInvoice($this->invoice)
            ->setPeppol($this->p_invoice)
            ->setClientSettings($this->_client_settings)
            ->setCompanySettings($this->_company_settings);

            return $this;

        }

        $this->p_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $this->gateway
            ->mutator
            ->setInvoice($this->invoice)
            ->setPeppol($this->p_invoice)
            ->setClientSettings($this->_client_settings)
            ->setCompanySettings($this->_company_settings);

        $this->setInvoiceDefaults();

        return $this;
    }

    /**
     * Transforms the settings props into usable models we can merge.
     *
     * @return self
     */
    private function setSettings(): self
    {
        $this->_client_settings = isset($this->invoice->client->e_invoice->Invoice) ? $this->e->decode('Peppol', json_encode($this->invoice->client->e_invoice->Invoice), 'json') : null;

        $this->_company_settings = isset($this->invoice->company->e_invoice->Invoice) ? $this->e->decode('Peppol', json_encode($this->invoice->company->e_invoice->Invoice), 'json') : null;

        return $this;

    }
    
    /**
     * getInvoice
     *
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice
     */
    public function getInvoice(): \InvoiceNinja\EInvoice\Models\Peppol\Invoice
    {
        //@todo - need to process this and remove null values
        return $this->p_invoice;

    }
    
    /**
     * toXml
     *
     * @return string
     */
    public function toXml(): string
    {
        $e = new EInvoice();
        $xml = $e->encode($this->p_invoice, 'xml');

        $prefix = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">';

        $suffix = '</Invoice>';
    
        $xml = str_ireplace(['\n','<?xml version="1.0"?>'], ['', $prefix], $xml);

        $xml .= $suffix;

        nlog($xml);

        return $xml;
        
    }
    
    /**
     * toJson
     *
     * @return string
     */
    public function toJson(): string
    {
        $e = new EInvoice();
        $json =  $e->encode($this->p_invoice, 'json');

        return $json;

    }
    
    /**
     * toObject
     *
     * @return mixed
     */
    public function toObject(): mixed
    {

        $invoice = new \stdClass;
        $invoice->Invoice = json_decode($this->toJson());
        return $invoice;

    }
    
    /**
     * toArray
     *
     * @return array
     */
    public function toArray(): array
    {
        return ['Invoice' => json_decode($this->toJson(), true)];
    }
    

    private function setOrderReference(): self
    {

        $this->p_invoice->BuyerReference = $this->invoice->po_number ?? '';

        if (strlen($this->invoice->po_number ?? '') > 1) {
            $order_reference = new OrderReference();
            $id = new ID();
            $id->value = $this->invoice->po_number;

            $order_reference->ID = $id;

            $this->p_invoice->OrderReference = $order_reference;

           
        }

        return $this;

    }

    private function getAllowanceCharges(): array
    {
        $allowances = [];

        //invoice discounts
        if($this->invoice->discount > 0){

            // Add Allowance Charge to Price
            $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
            $allowanceCharge->ChargeIndicator = 'false'; // false = discount
            $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
            $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->Amount->amount = (string)$this->calc->getTotalDiscount();
            $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
            $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->BaseAmount->amount = (string) $this->calc->getSubTotal();

            // Add percentage if available
            if ($this->invoice->discount > 0 && !$this->invoice->is_amount_discount) {
                $mfn = new \InvoiceNinja\EInvoice\Models\Peppol\NumericType\MultiplierFactorNumeric();
                $mfn->value = (string) ($this->invoice->discount / 100);
                $allowanceCharge->MultiplierFactorNumeric = $mfn; // Convert percentage to decimal
            }

            $allowances[] = $allowanceCharge;
        }

        //invoice surcharges (@todo React - need to turn back on surcharge taxes and use the first tax....)

        if($this->invoice->custom_surcharge1 > 0){

            // Add Allowance Charge to Price
            $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
            // $allowanceCharge->ChargeIndicator = true; // false = discount
            $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
            $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->Amount->amount = (string)$this->invoice->custom_surcharge1;
            $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
            $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->BaseAmount->amount = (string) $this->calc->getSubTotal();

            $allowances[] = $allowanceCharge;

        }

        if ($this->invoice->custom_surcharge2 > 0) {

            // Add Allowance Charge to Price
            $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
            // $allowanceCharge->ChargeIndicator = true; // false = discount
            $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
            $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->Amount->amount = (string)$this->invoice->custom_surcharge2;
            $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
            $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->BaseAmount->amount = (string) $this->calc->getSubTotal();

            $allowances[] = $allowanceCharge;

        }

        if ($this->invoice->custom_surcharge3 > 0) {

            // Add Allowance Charge to Price
            $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
            // $allowanceCharge->ChargeIndicator = true; // false = discount
            $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
            $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->Amount->amount = (string)$this->invoice->custom_surcharge3;
            $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
            $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->BaseAmount->amount = (string) $this->calc->getSubTotal();

            $allowances[] = $allowanceCharge;

        }

        if ($this->invoice->custom_surcharge4 > 0) {

            // Add Allowance Charge to Price
            $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
            // $allowanceCharge->ChargeIndicator = true; // false = discount
            $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
            $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->Amount->amount = (string)$this->invoice->custom_surcharge4;
            $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
            $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->BaseAmount->amount = (string) $this->calc->getSubTotal();

            $allowances[] = $allowanceCharge;

        }

        return $allowances;

    }

    /**
     * getLegalMonetaryTotal
     *
     * @return LegalMonetaryTotal
     */
    private function getLegalMonetaryTotal(): LegalMonetaryTotal
    {
        $taxable = $this->getTaxable();

        $lmt = new LegalMonetaryTotal();

        $lea = new LineExtensionAmount();
        $lea->currencyID = $this->invoice->client->currency()->code;
        $lea->amount = $this->invoice->uses_inclusive_taxes ? round($this->invoice->amount - $this->invoice->total_taxes, 2) : $taxable;
        $lmt->LineExtensionAmount = $lea;

        $tea = new TaxExclusiveAmount();
        $tea->currencyID = $this->invoice->client->currency()->code;
        $tea->amount = $this->invoice->uses_inclusive_taxes ? round($this->invoice->amount - $this->invoice->total_taxes, 2) : $taxable;
        $lmt->TaxExclusiveAmount = $tea;

        $tia = new TaxInclusiveAmount();
        $tia->currencyID = $this->invoice->client->currency()->code;
        $tia->amount = $this->invoice->amount;
        $lmt->TaxInclusiveAmount = $tia;

        $pa = new PayableAmount();
        $pa->currencyID = $this->invoice->client->currency()->code;
        $pa->amount = $this->invoice->amount;
        $lmt->PayableAmount = $pa;

        return $lmt;
    }
    
    /**
     * getTaxType
     *
     * @param  string $tax_id
     * @return string
     */
    private function getTaxType(string $tax_id = ''): string
    {
        $tax_type = null;
        
        switch ($tax_id) {
            case Product::PRODUCT_TYPE_SERVICE:
            case Product::PRODUCT_TYPE_DIGITAL:
            case Product::PRODUCT_TYPE_PHYSICAL:
            case Product::PRODUCT_TYPE_SHIPPING:
            case Product::PRODUCT_TYPE_REDUCED_TAX:
                $tax_type = 'S';
                break;
            case Product::PRODUCT_TYPE_EXEMPT:
                $tax_type =  'E';
                break;
            case Product::PRODUCT_TYPE_ZERO_RATED:
                $tax_type = 'Z';
                break;
            case Product::PRODUCT_TYPE_REVERSE_TAX:
                $tax_type = 'AE';
                break;
        }

        $eu_states = ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "EL", "GR", "HU", "IE", "IT", "LV", "LT", "LU", "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "ES-CE", "ES-ML", "ES-CN", "SE", "IS", "LI", "NO", "CH"];
        
        if (empty($tax_type)) {
            if ((in_array($this->company->country()->iso_3166_2, $eu_states) && in_array($this->invoice->client->country->iso_3166_2, $eu_states)) && $this->invoice->company->country()->iso_3166_2 != $this->invoice->client->country->iso_3166_2) {
                $tax_type = 'K'; //EEA Exempt
            } elseif (!in_array($this->invoice->client->country->iso_3166_2, $eu_states)) {
                $tax_type = 'G'; //Free export item, VAT not charged
            } else {
                $tax_type = 'S'; //Standard rate
            }
        }

        if(in_array($this->invoice->client->country->iso_3166_2, ["ES-CE", "ES-ML", "ES-CN"]) && $tax_type == 'S') {
            
            if ($this->invoice->client->country->iso_3166_2 == "ES-CN") {
                $tax_type = 'L'; //Canary Islands general indirect tax
            } elseif (in_array($this->invoice->client->country->iso_3166_2, ["ES-CE", "ES-ML"])) {
                $tax_type = 'M'; //Tax for production, services and importation in Ceuta and Melilla
            }

        }

        return $tax_type;
    }
   
    private function getInvoiceLines(): array
    {
        $lines = [];

        foreach($this->invoice->line_items as $key => $item) {

            $_item = new Item();
            $_item->Name = $item->product_key;
            $_item->Description = $item->notes;


            if($item->tax_rate1 > 0)
            {
            $ctc = new ClassifiedTaxCategory();
            $ctc->ID = new ID();
            $ctc->ID->value = $this->getTaxType($item->tax_id);
            $ctc->Percent = $item->tax_rate1;

            $ts = new TaxScheme();
            $id = new ID();
            $id->value = $this->standardizeTaxSchemeId($item->tax_name1);
            $ts->ID = $id;
            $ctc->TaxScheme = $ts;

            $_item->ClassifiedTaxCategory[] = $ctc;
            }

            if ($item->tax_rate2 > 0) {
                $ctc = new ClassifiedTaxCategory();
                $ctc->ID = new ID();
                $ctc->ID->value = $this->getTaxType($item->tax_id);
                $ctc->Percent = $item->tax_rate2;

                                
                $ts = new TaxScheme();
                $id = new ID();
                $id->value = $this->standardizeTaxSchemeId($item->tax_name2);
                $ts->ID = $id;
                $ctc->TaxScheme = $ts;

                $_item->ClassifiedTaxCategory[] = $ctc;
            }

            if ($item->tax_rate3 > 0) {
                $ctc = new ClassifiedTaxCategory();
                $ctc->ID = new ID();
                $ctc->ID->value = $this->getTaxType($item->tax_id);
                $ctc->Percent = $item->tax_rate3;

                $ts = new TaxScheme();
                $id = new ID();
                $id->value = $this->standardizeTaxSchemeId($item->tax_name3);
                $ts->ID = $id;
                $ctc->TaxScheme = $ts;

                $_item->ClassifiedTaxCategory[] = $ctc;
            }

            $line = new InvoiceLine();
            
            $id = new ID();
            $id->value = (string) ($key+1);
            $line->ID = $id;

            $iq = new \InvoiceNinja\EInvoice\Models\Peppol\QuantityType\InvoicedQuantity();
            $iq->amount = $item->quantity;
            $iq->unitCode = $item->unit_code ?? 'C62';
            $line->InvoicedQuantity = $iq;

            $lea = new LineExtensionAmount();
            $lea->currencyID = $this->invoice->client->currency()->code;
            $lea->amount = $this->invoice->uses_inclusive_taxes ? $item->line_total - $this->calcInclusiveLineTax($item->tax_rate1, $item->line_total) : $item->line_total;
            $line->LineExtensionAmount = $lea;
            $line->Item = $_item;

            $item_taxes = $this->getItemTaxes($item);

            // if(count($item_taxes) > 0) {
            //     $line->TaxTotal = $item_taxes;
            // }

            // $price = new Price();
            // $pa = new PriceAmount();
            // $pa->currencyID = $this->invoice->client->currency()->code;
            // $pa->amount = (string) ($this->costWithDiscount($item) - ($this->invoice->uses_inclusive_taxes ? ($this->calcInclusiveLineTax($item->tax_rate1, $item->line_total) / $item->quantity) : 0));
            // $price->PriceAmount = $pa;

            // $line->Price = $price;

            // Handle Price and Discounts
            if ($item->discount > 0) {
                // Base Price (before discount)
                $basePrice = new Price();
                $basePriceAmount = new PriceAmount();
                $basePriceAmount->currencyID = $this->invoice->client->currency()->code;
                $basePriceAmount->amount = (string)($item->cost - $this->calculateDiscountAmount($item));
                $basePrice->PriceAmount = $basePriceAmount;

                // Add Allowance Charge to Price
                $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
                $allowanceCharge->ChargeIndicator = 'false'; // false = discount
                $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
                $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
                $allowanceCharge->Amount->amount = (string)$this->calculateDiscountAmount($item);
                $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
                $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
                $allowanceCharge->BaseAmount->amount = (string)$item->cost;

                // Add percentage if available
                if ($item->discount > 0 && !$item->is_amount_discount) {
                    $mfn = new \InvoiceNinja\EInvoice\Models\Peppol\NumericType\MultiplierFactorNumeric();
                    $mfn->value = (string) ($item->discount / 100);
                    $allowanceCharge->MultiplierFactorNumeric = $mfn; // Convert percentage to decimal
                }

                $basePrice->AllowanceCharge[] = $allowanceCharge;
                $line->Price = $basePrice;

            } else {
                // No discount case
                $price = new Price();
                $pa = new PriceAmount();
                $pa->currencyID = $this->invoice->client->currency()->code;
                $pa->amount = (string) ($this->costWithDiscount($item) - ($this->invoice->uses_inclusive_taxes
                    ? ($this->calcInclusiveLineTax($item->tax_rate1, $item->line_total) / $item->quantity)
                    : 0));
                $price->PriceAmount = $pa;
                $line->Price = $price;
            }

            $lines[] = $line;
        }

        return $lines;
    }


    private function calculateDiscountAmount($item): float
    {
        if ($item->is_amount_discount) {
            return $item->discount / $item->quantity; // Per unit discount amount
        }
        
        return ($item->cost / $item->quantity) * ($item->discount / 100);
    }

    
    /**
     * costWithDiscount
     *
     * @param  mixed $item
     * @return float
     */
    private function costWithDiscount($item): float
    {
        $cost = $item->cost;

        if ($item->discount != 0) {
            if ($this->invoice->is_amount_discount) {
                $cost -= $item->discount / $item->quantity;
            } else {
                $cost -= $cost * $item->discount / 100;
            }
        }

        return $cost;
    }
    
    /**
     * zeroTaxAmount
     *
     * @return array
     */
    // private function zeroTaxAmount(): array
    // {
    //     $blank_tax = [];

    //     $tax_amount = new TaxAmount();
    //     $tax_amount->currencyID = $this->invoice->client->currency()->code;
    //     $tax_amount->amount = '0';
    //     $tax_subtotal = new TaxSubtotal();
    //     $tax_subtotal->TaxAmount = $tax_amount;

    //     $taxable_amount = new TaxableAmount();
    //     $taxable_amount->currencyID = $this->invoice->client->currency()->code;
    //     $taxable_amount->amount = '0';
    //     $tax_subtotal->TaxableAmount = $taxable_amount;

    //     $tc = new TaxCategory();
    //     $id = new ID();
    //     $id->value = 'Z';
    //     $tc->ID = $id;
    //     $tc->Percent = '0';
    //     $ts = new TaxScheme();
        
    //     $id = new ID();
    //     $id->value = '0';
    //     $ts->ID = $id;
    //     $tc->TaxScheme = $ts;
    //     $tax_subtotal->TaxCategory = $tc;

    //     $tax_total = new TaxTotal();
    //     $tax_total->TaxAmount = $tax_amount;
    //     $tax_total->TaxSubtotal[] = $tax_subtotal;
    //     $blank_tax[] = $tax_total;


    //     return $blank_tax;
    // }
    
    /**
     * getItemTaxes
     *
     * @param  object $item
     * @return array
     */
    private function getItemTaxes(object $item): array
    {
        $item_taxes = [];

        if(strlen($item->tax_name1 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;
            $tax_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->calcInclusiveLineTax($item->tax_rate1, $item->line_total) : $this->calcAmountLineTax($item->tax_rate1, $item->line_total);
            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $this->invoice->uses_inclusive_taxes ? $item->line_total - $tax_amount->amount : $item->line_total;
            $tax_subtotal->TaxableAmount = $taxable_amount;
            
            $tc = new TaxCategory();
            
            $id = new ID();
            $id->value = $this->getTaxType($item->tax_id);
            
            $tc->ID = $id;
            $tc->Percent = $item->tax_rate1;
            $ts = new TaxScheme();

            $id = new ID();
            $id->value = $this->standardizeTaxSchemeId($item->tax_name1);

            $jurisdiction = $this->getJurisdiction();
            $ts->JurisdictionRegionAddress[] = $jurisdiction;

            $ts->ID = $id;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;

            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal[] = $tax_subtotal;

            $this->tax_map[] = [
                'taxableAmount' => $taxable_amount->amount,
                'taxAmount' => $tax_amount->amount,
                'percentage' => $item->tax_rate1,
            ];

            $item_taxes[] = $tax_total;

        }


        if(strlen($item->tax_name2 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;

            $tax_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->calcInclusiveLineTax($item->tax_rate2, $item->line_total) : $this->calcAmountLineTax($item->tax_rate2, $item->line_total);

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $item->line_total;
            $tax_subtotal->TaxableAmount = $taxable_amount;


            $tc = new TaxCategory();
            
            $id = new ID();
            $id->value = $this->getTaxType($item->tax_id);

            $tc->ID = $id;
            $tc->Percent = $item->tax_rate2;
            $ts = new TaxScheme();

            $id = new ID();
            $id->value = $this->standardizeTaxSchemeId($item->tax_name2);
            
            $jurisdiction = $this->getJurisdiction();
            $ts->JurisdictionRegionAddress[] = $jurisdiction;

            $ts->ID = $id;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;


            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal[] = $tax_subtotal;

            
            $this->tax_map[] = [
                'taxableAmount' => $taxable_amount->amount,
                'taxAmount' => $tax_amount->amount,
                'percentage' => $item->tax_rate1,
            ];

            $item_taxes[] = $tax_total;

        }


        if(strlen($item->tax_name3 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;

            $tax_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->calcInclusiveLineTax($item->tax_rate3, $item->line_total) : $this->calcAmountLineTax($item->tax_rate3, $item->line_total);

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $item->line_total;
            $tax_subtotal->TaxableAmount = $taxable_amount;


            $tc = new TaxCategory();

            $id = new ID();
            $id->value = $this->getTaxType($item->tax_id);

            $tc->ID = $id;
            $tc->Percent = $item->tax_rate3;
            $ts = new TaxScheme();

            $id = new ID();
            $id->value = $this->standardizeTaxSchemeId($item->tax_name3);

            $jurisdiction = $this->getJurisdiction();
            $ts->JurisdictionRegionAddress[] = $jurisdiction;

            $ts->ID = $id;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;

            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal[] = $tax_subtotal;


            $this->tax_map[] = [
                'taxableAmount' => $taxable_amount->amount,
                'taxAmount' => $tax_amount->amount,
                'percentage' => $item->tax_rate1,
            ];

            $item_taxes[] = $tax_total;


        }

        return $item_taxes;
    }
    
    /**
     * getAccountingSupplierParty
     *
     * @return AccountingSupplierParty
     */
    private function getAccountingSupplierParty(): AccountingSupplierParty
    {

        $asp = new AccountingSupplierParty();

        $party = new Party();
        $party_name = new PartyName();
        $party_name->Name = $this->invoice->company->present()->name();
        $party->PartyName[] = $party_name;

        if (strlen($this->company->settings->vat_number ?? '') > 1) {

            $pi = new PartyIdentification();

            $vatID = new ID();

            if ($scheme = $this->resolveTaxScheme()) {
                $vatID->schemeID = $scheme;
            }

            $vatID->value = $this->company->settings->vat_number; //todo if we are cross border - switch to the supplier local vat number                                                                                                                     ->vat_number; //todo if we are cross border - switch to the supplier local vat number
            $pi->ID = $vatID;

            $party->PartyIdentification[] = $pi;

            $pts = new \InvoiceNinja\EInvoice\Models\Peppol\PartyTaxSchemeType\PartyTaxScheme();

            $companyID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CompanyID();
            $companyID->value = $this->invoice->company->settings->vat_number;
            $pts->CompanyID = $companyID;

            $ts = new TaxScheme();
            $ts->ID = $vatID;
            $pts->TaxScheme = $ts;

            
            //@todo if we have an exact GLN/routing number we should update this, otherwise Storecove will proxy and update on transit
            $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\EndpointID();
            $id->value = $this->company->settings->vat_number;
            $id->schemeID = $this->resolveScheme();

            $party->EndpointID = $id;
            $party->PartyTaxScheme[] = $pts;

        }

        $address = new Address();
        $address->CityName = $this->invoice->company->settings->city;
        $address->StreetName = $this->invoice->company->settings->address1;
        // $address->BuildingName = $this->invoice->company->settings->address2;
        $address->PostalZone = $this->invoice->company->settings->postal_code;
        // $address->CountrySubentity = $this->invoice->company->settings->state;

        $country = new Country();

        $ic = new IdentificationCode();
        $ic->value = substr($this->invoice->company->country()->iso_3166_2, 0, 2);
        $country->IdentificationCode = $ic;
        
        $address->Country = $country;

        $party->PostalAddress = $address;
        // $party->PhysicalLocation = $address;

        $contact = new Contact();
        $contact->ElectronicMail = $this->gateway->mutator->getSetting('Invoice.AccountingSupplierParty.Party.Contact') ?? $this->invoice->company->owner()->present()->email();
        $contact->Telephone = $this->gateway->mutator->getSetting('Invoice.AccountingSupplierParty.Party.Telephone') ?? $this->invoice->company->getSetting('phone');
        $contact->Name = $this->gateway->mutator->getSetting('Invoice.AccountingSupplierParty.Party.Name') ?? $this->invoice->company->owner()->present()->name();

        $party->Contact = $contact;

        $ple = new \InvoiceNinja\EInvoice\Models\Peppol\PartyLegalEntity();
        $ple->RegistrationName = $this->invoice->company->present()->name();
        $party->PartyLegalEntity[] = $ple;
        
        

        
        $asp->Party = $party;

        return $asp;
    }
    
    /**
     * resolveTaxScheme
     *
     * @return string
     */
    private function resolveTaxScheme(): string
    {
        return $this->resolveScheme();
        // return (new StorecoveRouter())->resolveTaxScheme($this->invoice->client->country->iso_3166_2, $this->invoice->client->classification);
    }
    
    /**
     * getAccountingCustomerParty
     *
     * @return AccountingCustomerParty
     */
    private function getAccountingCustomerParty(): AccountingCustomerParty
    {

        $acp = new AccountingCustomerParty();

        $party = new Party();

        if(strlen($this->invoice->client->vat_number ?? '') > 1) {

            $pi = new PartyIdentification();

            $vatID = new ID();

            if($scheme = $this->resolveTaxScheme()) {
                $vatID->schemeID = $scheme;
            }

            $vatID->value = $this->invoice->client->vat_number;
            $pi->ID = $vatID;

            $party->PartyIdentification[] = $pi;

        }

        $party_name = new PartyName();
        $party_name->Name = $this->invoice->client->present()->name();


        
        //@todo if we have an exact GLN/routing number we should update this, otherwise Storecove will proxy and update on transit
        if(strlen($this->invoice->client->routing_id ?? '') > 1)
        {
            $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\EndpointID();
            $id->value = $this->invoice->client->routing_id;
            $id->schemeID = $this->resolveScheme(true);
            $party->EndpointID = $id;
        }

        $party->PartyName[] = $party_name;

        $address = new Address();
        $address->CityName = $this->invoice->client->city;
        $address->StreetName = $this->invoice->client->address1;

        if(isset($this->invoice->client->address2) && strlen($this->invoice->client->address2) > 1)
        $address->AdditionalStreetName = $this->invoice->client->address2;

        $address->PostalZone = $this->invoice->client->postal_code;
        // $address->CountrySubentity = $this->invoice->client->state;

        $country = new Country();

        $ic = new IdentificationCode();
        $ic->value = substr($this->invoice->client->country->iso_3166_2, 0, 2);
        
        $country->IdentificationCode = $ic;
        $address->Country = $country;

        $party->PostalAddress = $address;

        // $physical_location = new PhysicalLocation();
        // $physical_location->Address = $address;

        // $party->PhysicalLocation = $physical_location;

        $contact = new Contact();

        $contact->ElectronicMail = $this->invoice->client->present()->email();

        if(isset($this->invoice->client->phone) && strlen($this->invoice->client->phone) > 2)
            $contact->Telephone = $this->invoice->client->phone;

        $party->Contact = $contact;

        $ple = new \InvoiceNinja\EInvoice\Models\Peppol\PartyLegalEntity();
        $ple->RegistrationName = $this->invoice->client->present()->name();
        $party->PartyLegalEntity[] = $ple;

        $acp->Party = $party;

        return $acp;
    }
    
    /**
     * getTaxable
     *
     * @return float
     */
    private function getTaxable(): float
    {
        $total = 0;

        foreach ($this->invoice->line_items as $item) {
            $line_total = $item->quantity * $item->cost;

            if ($item->discount != 0) {
                if ($this->invoice->is_amount_discount) {
                    $line_total -= $item->discount;
                } else {
                    $line_total -= $line_total * $item->discount / 100;
                }
            }

            $total += $line_total;
        }

        if ($this->invoice->discount > 0) {
            if ($this->invoice->is_amount_discount) {
                $total -= $this->invoice->discount;
            } else {
                $total *= (100 - $this->invoice->discount) / 100;
                $total = round($total, 2);
            }
        }

        if ($this->invoice->custom_surcharge1 && $this->invoice->custom_surcharge_tax1) {
            $total += $this->invoice->custom_surcharge1;
        }

        if ($this->invoice->custom_surcharge2 && $this->invoice->custom_surcharge_tax2) {
            $total += $this->invoice->custom_surcharge2;
        }

        if ($this->invoice->custom_surcharge3 && $this->invoice->custom_surcharge_tax3) {
            $total += $this->invoice->custom_surcharge3;
        }

        if ($this->invoice->custom_surcharge4 && $this->invoice->custom_surcharge_tax4) {
            $total += $this->invoice->custom_surcharge4;
        }

        return $total;
    }

    /////////////////  Helper Methods /////////////////////////
    

    /**
     * setInvoiceDefaults
     *
     * Stubs a default einvoice
     * @return self
     */
    public function setInvoiceDefaults(): self
    {

        // Stub new invoice with company settings.
        if($this->_company_settings)
        {
            foreach(get_object_vars($this->_company_settings) as $prop => $value){
                $this->p_invoice->{$prop} = $value;
            }
        }

        // Overwrite with any client level settings
        if($this->_client_settings)
        {
            foreach (get_object_vars($this->_client_settings) as $prop => $value) {
                $this->p_invoice->{$prop} = $value;
            }
        }

        // Plucks special overriding properties scanning the correct settings level
        $settings = [
            'AccountingCostCode' => 7,
            'AccountingCost' => 7,
            'BuyerReference' => 6,
            'AccountingSupplierParty' => 1,
            'AccountingCustomerParty' => 2,
            'PayeeParty' => 1,
            'BuyerCustomerParty' => 2,
            'SellerSupplierParty' => 1,
            'TaxRepresentativeParty' => 1,
            'Delivery' => 1,
            'DeliveryTerms' => 7,
            'PaymentMeans' => 7,
            'PaymentTerms' => 7,
        ];

        //only scans for top level props
        foreach($settings as $prop => $visibility) {

            if($prop_value = $this->gateway->mutator->getSetting($prop)) {
                $this->p_invoice->{$prop} = $prop_value;
            }

        }

        return $this;
    }

    public function setTaxBreakdown(): self
    {
        
        $tax_total = new TaxTotal();

        $taxes = collect($this->tax_map)
            ->groupBy('percentage')
            ->map(function ($group) {

                return [
                    'taxableAmount' => $group->sum('taxableAmount'),
                    'taxAmount' => $group->sum('taxAmount'),
                    'percentage' => $group->first()['percentage'],
                ];


            });

        foreach($taxes as $grouped_tax)
        {
            // Required: TaxAmount (BT-110)
            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;
            $tax_amount->amount = (string)$grouped_tax['taxAmount'];
            $tax_total->TaxAmount = $tax_amount;

            // Required: TaxSubtotal (BG-23)
            $tax_subtotal = new TaxSubtotal();

            // Required: TaxableAmount (BT-116)
            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = (string)$grouped_tax['taxableAmount'];
            $tax_subtotal->TaxableAmount = $taxable_amount;

            // Required: TaxAmount (BT-117)
            $subtotal_tax_amount = new TaxAmount();
            $subtotal_tax_amount->currencyID = $this->invoice->client->currency()->code;
            $subtotal_tax_amount->amount = (string)$grouped_tax['taxAmount'];

            $tax_subtotal->TaxAmount = $subtotal_tax_amount;

            // Required: TaxCategory (BG-23)
            $tax_category = new TaxCategory();

            // Required: TaxCategory ID (BT-118)
            $category_id = new ID();
            $category_id->value = 'S'; // Standard rate
            $tax_category->ID = $category_id;

            // Required: TaxCategory Rate (BT-119)
            $tax_category->Percent = (string)$grouped_tax['percentage'];

            // Required: TaxScheme (BG-23)
            $tax_scheme = new TaxScheme();
            $scheme_id = new ID();
            $scheme_id->value = $this->standardizeTaxSchemeId("taxname");
            $tax_scheme->ID = $scheme_id;
            $tax_category->TaxScheme = $tax_scheme;

            $tax_subtotal->TaxCategory = $tax_category;
            $tax_total->TaxSubtotal[] = $tax_subtotal;

            $this->p_invoice->TaxTotal[] = $tax_total;
        }

        return $this;
    }

    public function getJurisdiction()
    {

        //calculate nexus
        $country_code = $this->company->country()->iso_3166_2;
        $br = new BaseRule();
        $eu_countries = $br->eu_country_codes;

        if($this->invoice->client->country->iso_3166_2 == $this->company->country()->iso_3166_2){
            //Domestic Sales
            $country_code = $this->company->country()->iso_3166_2;
        }
        elseif(in_array($country_code, $eu_countries) && !in_array($this->invoice->client->country->iso_3166_2, $eu_countries)){
            //NON-EU sale
        }
        elseif(in_array($country_code, $eu_countries) && in_array($this->invoice->client->country->iso_3166_2, $eu_countries)){
            //EU Sale
            if($this->company->tax_data->regions->EU->has_sales_above_threshold || !$this->invoice->client->has_valid_vat_number){ //over threshold - tax in buyer country
                $country_code = $this->invoice->client->country->iso_3166_2;
            }
        }

        $jurisdiction = new \InvoiceNinja\EInvoice\Models\Peppol\AddressType\JurisdictionRegionAddress();
        $country = new Country();
        $ic = new IdentificationCode();
        $ic->value = $country_code;
        $country->IdentificationCode = $ic;
        $jurisdiction->Country = $country;        
        $addressTypeCode = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\AddressTypeCode();
        $addressTypeCode->value = 'JURISDICTION';  // or the appropriate code from PEPPOL spec
        $jurisdiction->AddressTypeCode = $addressTypeCode;

        return $jurisdiction;

    }


    private function standardizeTaxSchemeId(string $tax_name): string 
    {
                
        $br = new BaseRule();
        $eu_countries = $br->eu_country_codes;

        // If company is in EU, standardize to VAT
        if (in_array($this->company->country()->iso_3166_2, $eu_countries)) {
            return "VAT";
        }

        // For non-EU countries, return original or handle specifically
        return $this->standardizeTaxSchemeId($tax_name);
    }

    private function resolveScheme(bool $is_client=false): string
    {

        $vat_number = $is_client ? $this->invoice->client->vat_number : $this->company->settings->vat_number;
        $tax_number = $is_client ? $this->invoice->client->id_number : $this->company->settings->id_number;
        $country_code = $is_client ? $this->invoice->client->country->iso_3166_2 : $this->company->country()->iso_3166_2;

            switch ($country_code) {
                case 'FR': // France
                    return '0002'; // SIRENE

                case 'SE': // Sweden
                    return '0007'; // Organisationsnummer

                case 'NO': // Norway
                    return '0192'; // Enhetsregisteret

                case 'FI': // Finland
                    return '0213'; // Finnish VAT
                    // '0212', // Finnish Organization Identifier
                    // '0216', // OVT

                case 'DK': // Denmark
                    return '0184'; // DIGSTORG

                case 'IT': // Italy
                    return '0201'; // IPA
                    // '0210', // CODICE FISCALE
                    // '0211', // PARTITA IVA

                case 'NL': // Netherlands
                    return '0106'; // Chamber of Commerce
                    // '0190', // Dutch Originator's Identification Number
                
                case 'BE': // Belgium
                    return '0208'; // Enterprise Number

                case 'LU': // Luxembourg
                    return '0060'; // DUNS

                case 'ES': // Spain
                    return '0195'; // Company Registry

                case 'AT': // Austria
                    return '0088'; // GLN

                case 'DE': // Germany
                    return '0204'; // Leitweg-ID

                case 'GB': // United Kingdom
                    return '0088'; // GLN

                case 'CH': // Switzerland
                    return '0183'; // Swiss Unique Business ID

                case 'SG': // Singapore
                    return '0195'; // UEN
                    //0009 DUNS

                case 'AU': // Australia
                    return '0151'; // ABN

                case 'US': // United States
                    return '0060'; // DUNS

                case 'LT':
                    return '0200';

                case 'JP':
                    return '0221'; // Japan Registered Invoice Number

                case 'MY':
                    return '0230'; // Malaysia National e-invoicing framework

                case 'NZ':
                    return '0088';
         
                // Default to GLN for any other country
                default:
                                        
                    if (!empty($tax_number) && strlen($tax_number) === 9) {
                        return '0009'; // DUNS if 9 digits
                    }
                    return '0088'; // Global Location Number (GLN)

            }
        }
}
