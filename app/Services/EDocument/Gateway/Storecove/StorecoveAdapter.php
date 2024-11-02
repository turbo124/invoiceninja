<?php

namespace App\Services\EDocument\Gateway\Storecove;

use App\DataMapper\Tax\BaseRule;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use App\Services\EDocument\Gateway\Storecove\Models\Invoice;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use InvoiceNinja\EInvoice\Models\Peppol\Invoice as PeppolInvoice;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use App\Services\EDocument\Gateway\Transformers\StorecoveTransformer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use App\Services\EDocument\Gateway\Storecove\PeppolToStorecoveNormalizer;
use App\Services\EDocument\Standards\Peppol;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class StorecoveAdapter
{

    public function __construct(public Storecove $storecove){}

    private Invoice $storecove_invoice;

    private array $errors = [];

    private bool $valid_document = true;
    
    private $ninja_invoice;

    private string $nexus;

    /**
     * transform
     *
     * @param  \App\Models\Invoice $invoice
     * @return self
     */
    public function transform($invoice): self
    {
        $this->ninja_invoice = $invoice;

        $serializer = $this->getSerializer();

        $p = (new Peppol($invoice))->run()->toXml();

        nlog($p);

$context = [
         DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
         AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
      ];


$e = new \InvoiceNinja\EInvoice\EInvoice();
$peppolInvoice = $e->decode('Peppol', $p, 'xml');

$parent = \App\Services\EDocument\Gateway\Storecove\Models\Invoice::class;
$peppolInvoice = $data = $e->encode($peppolInvoice, 'json', $context);
$this->storecove_invoice = $serializer->deserialize($peppolInvoice, $parent, 'json', $context);
// $s_invoice = $serializer->encode($invoice, 'json', $context);
// $arr = json_decode($s_invoice, true);
// $data = $this->removeEmptyValues($arr);


        $this->buildNexus();
       
        // @phpstan-ignore-next-line
        // $this->storecove_invoice = $serializer->deserialize($data, Invoice::class, 'json', $context);

        return $this;

    }

    public function decorate(): self
    {
        //set all taxmap countries - resolve the taxing country
        $lines = $this->storecove_invoice->getInvoiceLines();

        foreach($lines as $line)
        {
            foreach($line->taxes_duties_fees as &$tax)
            {
                $tax->country = $this->nexus;
                
                if(property_exists($tax,'category'))
                    $tax->category = $this->tranformTaxCode($tax->category);
            }
            unset($tax);
        }

        $this->storecove_invoice->setInvoiceLines($lines);

        $tax_subtotals = $this->storecove_invoice->getTaxSubtotals();

        foreach($tax_subtotals as &$tax)
        {
            $tax->country = $this->nexus;
            
            if (property_exists($tax, 'category')) 
                $tax->category = $this->tranformTaxCode($tax->category);

        }
        unset($tax);

        $this->storecove_invoice->setTaxSubtotals($tax_subtotals);
        //configure identifiers

        //update payment means codes to storecove equivalents
        $payment_means = $this->storecove_invoice->getPaymentMeansArray();

        foreach($payment_means as &$pm)
        {
            $pm->code = $this->transformPaymentMeansCode($pm->code);
        }
        
        $this->storecove_invoice->setPaymentMeansArray($payment_means);

        $this->storecove_invoice->setTaxSystem('tax_line_percentages');
        //set additional identifier if required (ie de => FR with FR vat)
        return $this;
    }

    public function validate(): self
    {
        // $this->valid_document
        return $this;
    }

    public function getInvoice(): Invoice
    {
        return $this->storecove_invoice;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function addError(string $error): self
    {
        $this->errors[] = $error;
        return $this;
    }

    private function getSerializer()
    {
                
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        // list of PropertyListExtractorInterface (any iterable)
        $typeExtractors = [$reflectionExtractor,$phpDocExtractor];
        // list of PropertyDescriptionExtractorInterface (any iterable)
        $descriptionExtractors = [$phpDocExtractor];
        // list of PropertyAccessExtractorInterface (any iterable)
        $propertyInitializableExtractors = [$reflectionExtractor];
        $propertyInfo = new PropertyInfoExtractor(
            $propertyInitializableExtractors,
            $descriptionExtractors,
            $typeExtractors,
        );
        $xml_encoder = new XmlEncoder(['xml_format_output' => true, 'remove_empty_tags' => true,]);
        $json_encoder = new JsonEncoder();

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $metadataAwareNameConverter = new MetadataAwareNameConverter($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());

        $normalizer = new ObjectNormalizer($classMetadataFactory, $metadataAwareNameConverter, null, $propertyInfo);

        $normalizers = [new DateTimeNormalizer(), $normalizer,  new ArrayDenormalizer()];
        $encoders = [$xml_encoder, $json_encoder];
        $serializer = new Serializer($normalizers, $encoders);

        return $serializer;
    }

    public function getDocument(): mixed
    {
        $serializer = $this->getSerializer();

        $context = [
          DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
          AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ];

        $s_invoice = $serializer->encode($this->storecove_invoice, 'json', $context);

        $s_invoice = json_decode($s_invoice, true);

        $s_invoice = $this->removeEmptyValues($s_invoice);

        $data = [
            'errors' => $this->getErrors(),
            'document' => $s_invoice,
        ];

        return $data;

    }

    private function removeEmptyValues(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeEmptyValues($value);
                if (empty($array[$key])) {
                    unset($array[$key]);
                }
            } elseif ($value === null || $value === '') {
                unset($array[$key]);
            }
        }
        // nlog($array);
        return $array;
    }

    private function buildNexus(): self
    {

        //Calculate nexus
        $company_country_code = $this->ninja_invoice->company->country()->iso_3166_2;
        $client_country_code = $this->ninja_invoice->client->country->iso_3166_2;
        $br = new BaseRule();
        $eu_countries = $br->eu_country_codes;

        if ($client_country_code == $company_country_code) {
            //Domestic Sales
            $this->nexus = $company_country_code;
        } elseif (in_array($company_country_code, $eu_countries) && !in_array($client_country_code, $eu_countries)) {
            //NON-EU Sale
            $this->nexus = $company_country_code;
        } elseif (in_array($company_country_code, $eu_countries) && in_array($client_country_code, $eu_countries)) {
            
            //EU Sale
            
            // Invalid VAT number = seller country nexus
            if(!$this->ninja_invoice->client->has_valid_vat_number)
                $this->nexus = $company_country_code;
            else if ($this->ninja_invoice->company->tax_data->regions->EU->has_sales_above_threshold && isset($this->ninja_invoice->company->tax_data->regions->EU->subregions->{$client_country_code}->vat_number)) { //over threshold - tax in buyer country
                $this->nexus = $client_country_code;
            }

            //If we reach here? We are in an invalid state!
            $this->nexus = $company_country_code;
            $this->addError("Tax Nexus is client country ({$client_country_code}) - however VAT number not present for this region. Document not sent!");
        }

        return $this;
    }

    private function tranformTaxCode(string $code): ?string
    {
        return match($code){
            'S' => 'standard',
            'Z' => 'zero_rated',
            'E' => 'exempt',
            'AE' => 'reverse_charge',
            'K' => 'intra_community',
            'G' => 'export',
            'O' => 'outside_scope',
            'L' => 'cgst',
            'I' => 'igst',
            'SS' => 'sgst',
            'B' => 'deemed_supply',
            'SR' => 'srca_s',
            'SC' => 'srca_c',
            'NR' => 'not_registered',
            default => null
        };
    }

    private function transformPaymentMeansCode(?string $code): string
    {
        return match($code){
            '30' => 'credit_transfer',
            '58' => 'sepa_credit_transfer',
            '31' => 'debit_transfer',
            '49' => 'direct_debit',
            '59' => 'sepa_direct_debit',
            '48' => 'card',         // Generic card payment
            '54' => 'bank_card',    
            '55' => 'credit_card',
            '57' => 'standing_agreement',
            '10' => 'cash',
            '20' => 'bank_cheque',
            '21' => 'cashiers_cheque',
            '97' => 'aunz_npp',
            '98' => 'aunz_npp_payid',
            '99' => 'aunz_npp_payto',
            '71' => 'aunz_bpay',
            '72' => 'aunz_postbillpay',
            '73' => 'aunz_uri',
            '50' => 'se_bankgiro',
            '51' => 'se_plusgiro',
            '74' => 'sg_giro',
            '75' => 'sg_card',
            '76' => 'sg_paynow',
            '77' => 'it_mav',
            '78' => 'it_pagopa',
            '42' => 'nl_ga_beneficiary',
            '43' => 'nl_ga_gaccount',
            '1'  => 'undefined',    // Instrument not defined
            default => 'undefined',
        };

    }
}