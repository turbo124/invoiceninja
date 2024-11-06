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
        $x = $s->getDocument('3f0981f1-5105-4970-81f2-6b7482ad27d7');
        
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

          $object = $serializer->deserialize(
              $x,
              StorecoveInvoice::class,
              'json',
              $context
          );

          $this->assertInstanceOf(StorecoveInvoice::class, $object);

    }


}