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
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ZugferdTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();


        if (config('ninja.testvars.travis')) {
            $this->markTestSkipped("do not run in CI");
        }
                
        $this->withoutMiddleware(
            ThrottleRequests::class
        );


        $this->makeTestData();

    }


     private function setupTestData(array $params = []): array
    {
        
        $settings = CompanySettings::defaults();
        $settings->vat_number = $params['company_vat'] ?? 'DE123456789';
        $settings->id_number = $params['company_id_number'] ?? '';
        $settings->classification = $params['company_classification'] ?? 'business';
        $settings->country_id = Country::where('iso_3166_2', 'DE')->first()->id;
        $settings->email = $this->faker->safeEmail();
        $settings->e_invoice_type = 'XInvoice_3_0';
        $settings->currency_id = '3';
        $settings->name = 'Test Company';
        $settings->address1 = 'Line 1 of address of the seller';
        // $settings->address2 = 'Line 2 of address of the seller';
        $settings->city = 'Hamburg';
        // $settings->state = 'Berlin';
        $settings->postal_code = 'X123433';

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = $params['over_threshold'] ?? false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->seller_subregion = $params['company_country'] ?? 'DE';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new \InvoiceNinja\EInvoice\Models\Peppol\BranchType\FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX"; //BIC

        $pfa = new \InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = 'DE89370400440532013000';
        $pfa->ID = $id;
        $pfa->Name = 'PFA-NAME';

        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new \InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;

        $pmc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $pmc->value = '30';

        $pm->PaymentMeansCode = $pmc;

        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $this->company->settings = $settings;
        $this->company->tax_data = $tax_data;
        $this->company->calculate_taxes = true;
        $this->company->legal_entity_id = 290868;
        $this->company->e_invoice = $stub;
        $this->company->save();
        $company = $this->company;

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => Country::where('iso_3166_2', $params['client_country'] ?? 'FR')->first()->id,
            'vat_number' => $params['client_vat'] ?? '',
            'classification' => $params['classification'] ?? 'individual',
            'has_valid_vat_number' => $params['has_valid_vat'] ?? false,
            'name' => 'Test Client',
            'is_tax_exempt' => $params['is_tax_exempt'] ?? false,
            'id_number' => $params['client_id_number'] ?? '',
        ]);

        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' =>$client->company_id,
            'user_id' => $client->user_id,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail()
        ]);

        $invoice = \App\Models\Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'due_date' => now()->addDays(2)->format('Y-m-d'),
            'uses_inclusive_taxes' => false,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
        ]);

        $items = $invoice->line_items;
        foreach($items as &$item)
        {
          $item->tax_name2 = '';
          $item->tax_rate2 = 0;
          $item->tax_name3 = '';
          $item->tax_rate3 = 0;
          $item->uses_inclusive_taxes = false;
        }
        unset($item);

        $invoice->line_items = array_values($items);
        $invoice = $invoice->calc()->getInvoice();

        return compact('company', 'client', 'invoice');
    }


    public function testZugFerdValidation()
    {

        $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';

        // $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/FACTUR-X_MINIMUM.xslt';

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$zug_16931]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();
        
        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }


    public function testZugFerdValidationWithInclusiveTaxes()
    {

        $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';

        // $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/FACTUR-X_MINIMUM.xslt';

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$zug_16931]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();
        
        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }


    public function testZugFerdValidationWithInclusiveTaxesAndTotalAmountDiscount()
    {

        $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';

        // $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/FACTUR-X_MINIMUM.xslt';

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->discount=20;
        $invoice->is_amount_discount = true;

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$zug_16931]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();
        
        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }


    public function testZugFerdValidationWithInclusiveTaxesAndTotalPercentDiscount()
    {

        $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';

        // $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/FACTUR-X_MINIMUM.xslt';

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->discount=20;
        $invoice->is_amount_discount = false;
        
        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$zug_16931]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();
        
        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }

    public function testZugFerdValidationWithInclusiveTaxesAndTotalPercentDiscountOnLineItemsAlso()
    {

        $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';

        // $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/FACTUR-X_MINIMUM.xslt';

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->discount=20;
        $invoice->is_amount_discount = false;
        
        $items = $invoice->line_items;

        foreach($items as &$item){
            $item->discount=10;
            $item->is_amount_discount = false;
        }
        unset($item);

        $invoice->line_items = $items;

        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$zug_16931]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();
        
        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }


    public function testZugFerdValidationWithInclusiveTaxesAndTotalAmountDiscountOnLineItemsAlso()
    {

        $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';

        $scenario = [
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'over_threshold' => true,
            'legal_entity_id' => 290868,
        ];

        $data = $this->setupTestData($scenario);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->discount=20;
        $invoice->is_amount_discount = true;
        
        $items = $invoice->line_items;

        foreach($items as &$item){
            $item->discount=5;
            $item->is_amount_discount = true;
        }
        unset($item);

        $invoice->line_items = $items;
        
        $xml = $invoice->service()->getEInvoice();

        $validator = new \App\Services\EDocument\Standards\Validation\XsltDocumentValidator($xml);
        $validator->setStyleSheets([$zug_16931]);
        $validator->setXsd('/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();
        
        if (count($validator->getErrors()) > 0) {
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors());

    }
}
