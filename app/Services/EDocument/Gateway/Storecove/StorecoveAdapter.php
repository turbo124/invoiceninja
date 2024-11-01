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

        $this->buildNexus();

        $context = [
           DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
           AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ];

        $serializer = $this->getSerializer();

        // @phpstan-ignore-next-line
        $this->storecove_invoice = $serializer->deserialize($invoice->e_invoice, Invoice::class, 'json', $context);

        return $this;

    }

    public function decorate(): self
    {
        //set all taxmap countries - resolve the taxing country

        //configure identifiers

        //set additional identifier if required (ie de => FR with FR vat)
    }

    public function validate(): self
    {
        return $this->valid_document;
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
            //NON-EU sale
            $this->nexus = $company_country_code;
        } elseif (in_array($company_country_code, $eu_countries) && in_array($client_country_code, $eu_countries)) {
            
            //EU Sale
            
            // Invalid VAT number = seller country nexus
            if(!$this->ninja_invoice->client->has_valid_vat_number)
                $this->nexus = $company_country_code;
            else if ($this->ninja_invoice->company->tax_data->regions->EU->has_sales_above_threshold && isset($this->ninja_invoice->company->tax_data->regions->EU->subregions->{$client_country_code}->vat_number)) { //over threshold - tax in buyer country
                $country_code = $client_country_code;
            }
        }


        //IF EU -> EU && Buyer Country != Seller Country && has_sales_above_threshold === true
        //

        return $this;
    }
}