<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Integration\Einvoice\Storecove;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use Tests\MockAccountData;
use Illuminate\Support\Str;
use App\Models\ClientContact;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Services\EDocument\Standards\Peppol;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use InvoiceNinja\EInvoice\Models\Peppol\Invoice as PeppolInvoice;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use App\Services\EDocument\Gateway\Storecove\PeppolToStorecoveNormalizer;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use App\Services\EDocument\Gateway\Storecove\Models\Invoice as StorecoveInvoice;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Illuminate\Routing\Middleware\ThrottleRequests;

class StorecoveIngestTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private int $routing_id = 0;

    private string $test_invoice = '{"legal_entity_id":290868,"direction":"in","guid":"3f0981f1-5105-4970-81f2-6b7482ad27d7","document":{"document_type":"invoice","source":"peppol","invoice":{"accounting_cost":"23089","accounting_currency_taxable_amount":null,"accounting_currency_tax_amount":null,"accounting_currency_tax_amount_currency":null,"accounting_currency_exchange_rate":null,"accounting_supplier_party":{"party":{"company_name":"Test 0106 identifier Storecove","registration_name":"Test 0106 identifier Storecove","address":{"street1":"Address 34","street2":null,"city":"Holst","zip":"2324 DF","county":null,"country":"NL"},"contact":{"email":"sender@company.com","first_name":"Jony","last_name":"Ponski","phone":"088-333333333"}},"public_identifiers":[{"scheme":"NL:KVK","id":"012345677"},{"scheme":"NL:VAT","id":"NL000000000B45"}]},"allowance_charges":[{"amount_excluding_tax":11.2,"base_amount_excluding_tax":null,"reason":"late payment","taxes_duties_fees":[{"category":"standard","country":"NL","percentage":21.0,"amount":null,"type":"VAT"}]},{"amount_excluding_tax":-1.0,"base_amount_excluding_tax":null,"reason":"bonus","taxes_duties_fees":[{"category":"standard","country":"NL","percentage":21.0,"amount":null,"type":"VAT"}]}],"amount_including_tax":27.27,"attachments":[],"delivery":{"actual_delivery_date":"2024-10-29","quantity":null,"delivery_location":{"id":"871690930000478611","scheme_id":"0088","location_name":null,"address":{"street1":"line1","street2":"line2","city":"CITY","zip":"3423423","county":"CA","country":"US"}},"delivery_party":null,"shipment":{"allowance_charges":[],"origin_address":{"country":null},"shipping_marks":null}},"delivery_terms":{"delivery_location_id":null,"incoterms":null,"special_terms":null},"document_currency_code":"USD","due_date":"2024-11-29","invoice_lines":[{"accounting_cost":"23089","additional_item_properties":[{"name":"UtilityConsumptionPoint","value":"871690930000222221"},{"name":"UtilityConsumptionPointAddress","value":"VE HAZERSWOUDE-XXXXX"}],"allowance_charges":[{"amount_excluding_tax":-0.25,"base_amount_excluding_tax":0.0,"reason":"special discount"},{"amount_excluding_tax":-0.75,"base_amount_excluding_tax":0.0,"reason":"even more special discount"}],"amount_excluding_tax":2.67,"amount_including_tax":null,"base_quantity":2.5,"description":"Supply","invoice_period":"2024-09-30 - 2024-10-30","item_price":0.1433773,"line_id":"1","name":"Supply peak","note":"Only half the story...","quantity":63.992,"quantity_unit_code":"KWH","references":[{"document_description":null,"document_id":"BBBBBBBB","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"item_commodity_code","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"CCCCCCCC","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":"ZZZ","document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"item_classification_code","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"buyer reference or purchase order reference is recommended","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_purchase_order","document_type_code":null,"document_uuid":null,"line_id":"1","issue_date":null},{"document_description":null,"document_id":"8718868597083","document_id_scheme_id":"0088","document_id_scheme_agency_id":"9","document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_standard_item_identification","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"E_DVK_PKlik_KVP_LP","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_sellers_item_identification","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"9 008 115","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_buyers_item_identification","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null}],"taxes_duties_fees":[{"category":"standard","country":"NL","percentage":21.0,"amount":null,"type":"VAT"}]},{"accounting_cost":"23089","additional_item_properties":[{"name":"UtilityConsumptionPoint","value":"871690930000222221"},{"name":"UtilityConsumptionPointAddress","value":"VE HAZERSWOUDE-XXXXX"}],"allowance_charges":[{"amount_excluding_tax":-0.25,"base_amount_excluding_tax":0.0,"reason":"special discount"},{"amount_excluding_tax":-0.75,"base_amount_excluding_tax":0.0,"reason":"even more special discount"}],"amount_excluding_tax":9.67,"amount_including_tax":null,"base_quantity":2.78951212,"description":"Supply","invoice_period":"2024-09-30 - 2024-10-30","item_price":2.30944245,"line_id":"2","name":"Supply peak","note":"Only half the story...","quantity":12.888,"quantity_unit_code":"K6","references":[{"document_description":null,"document_id":"BBBBBBBB","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"item_commodity_code","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"CCCCCCCC","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":"ZZZ","document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"item_classification_code","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"buyer reference or purchase order reference is recommended","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_purchase_order","document_type_code":null,"document_uuid":null,"line_id":"1","issue_date":null},{"document_description":null,"document_id":"8718868597083","document_id_scheme_id":"0088","document_id_scheme_agency_id":"9","document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_standard_item_identification","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"E_DVK_PKlik_KVP_LP","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_sellers_item_identification","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"9 008 115","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"line_buyers_item_identification","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null}],"taxes_duties_fees":[{"category":"standard","country":"NL","percentage":21.0,"amount":null,"type":"VAT"}]}],"invoice_number":"2024-10-30T23:20:29-2e8c0274","invoice_period":"2024-09-30 - 2024-10-30","issue_date":"2024-10-30","issue_reasons":[],"issue_time":null,"note":"This is the invoice note. Senders can enter free text. This may not be read by the receiver, so it is discouraged to use this for electronic invoicing.","payable_rounding_amount":0.02,"payment_means_array":[{"account":"NL50RABO0162432445","amount":null,"branche_code":null,"code":"credit_transfer","holder":null,"network":null,"payment_id":"44556677"}],"payment_terms":{"note":"For payment terms, only a note is supported by Peppol currently."},"prepaid_amount":1.0,"references":[{"document_description":null,"document_id":"buyer reference or purchase order reference is recommended","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"purchase_order","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"buyer reference or purchase order reference is recommended","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"buyer_reference","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"PreviousInvoiceNumber123456","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"billing","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null},{"document_description":null,"document_id":"contract123","document_id_scheme_id":null,"document_id_scheme_agency_id":null,"document_id_scheme_version_id":null,"document_id_list_id":null,"document_id_list_agency_id":null,"document_id_list_version_id":null,"document_type":"contract","document_type_code":null,"document_uuid":null,"line_id":null,"issue_date":null}],"self_billing_mode":false,"sub_type":"invoice","system_generated_primary_image":false,"tax_point_date":"2024-10-30","tax_subtotals":[{"category":"standard","country":"NL","percentage":21.0,"taxable_amount":22.54,"tax_amount":4.73,"type":"VAT"}],"tax_system":"tax_line_percentages","time_zone":null,"ubl_extensions":[]}}}  ';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (config('ninja.testvars.travis') !== false || !config('ninja.storecove_api_key')) {
            $this->markTestSkipped("do not run in CI");
        }
                
        $this->withoutMiddleware(
            ThrottleRequests::class
        );

    }

     public function testIngestStorecoveDocument()
    {

        $s = new Storecove();
        
        $x = json_decode($this->test_invoice, true);

        $doc = $x['document']['invoice'];

        $x = json_encode($doc);
        
          $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

          // Create a proper PropertyInfoExtractor
          $phpDocExtractor = new PhpDocExtractor();
          $reflectionExtractor = new ReflectionExtractor();

          $propertyInfo = new PropertyInfoExtractor(
              // List of extractors for type info
              [$reflectionExtractor, $phpDocExtractor],
              // List of extractors for descriptions
              [$phpDocExtractor],
              // List of extractors for access info
              [$reflectionExtractor],
              // List of extractors for mutation info
              [$reflectionExtractor],
              // List of extractors for initialization info
              [$reflectionExtractor]
          );

          $normalizers = [
              new DateTimeNormalizer(),
              new ArrayDenormalizer(),
              new ObjectNormalizer(
                  $classMetadataFactory,
                  null,
                  null,
                  $propertyInfo
              )
          ];

          $context = [
              DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
              AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
              AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => false,
              AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE => true,  // Add this
          ];

          $encoders = [new JsonEncoder()];


          $serializer = new Serializer($normalizers, $encoders);

          $context = [
              DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
              AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
              AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => false,  // Enforce types
          ];

          $storecove_invoice = $serializer->deserialize(
              $x,
              StorecoveInvoice::class,
              'json',
              $context
          );

          $this->assertInstanceOf(StorecoveInvoice::class, $storecove_invoice);

          $this->assertEquals(27.27, $storecove_invoice->getAmountIncludingTax());


            $tax_totals = [];

            foreach ($storecove_invoice->getTaxSubtotals() as $tdf) {
                $type = $tdf->getType(); // Direct property access

                if (!isset($tax_totals[$type])) {
                    $tax_totals[$type] = [
                        'tax_amount' => $tdf->getTaxAmount(),
                        'category' => $tdf->getCategory(),
                        'country' => $tdf->getCountry() ,
                        'percentage' => $tdf->getPercentage()
                    ];
                }

            }

            $totals = collect($tax_totals);
            $this->assertEquals(4.73, $totals->sum('tax_amount'));

    }


}