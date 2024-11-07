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
use App\Models\User;
use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\CompanyToken;
use App\Models\ClientContact;
use App\DataMapper\InvoiceItem;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Services\EDocument\Standards\Peppol;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\EDocument\Standards\Validation\Peppol\EntityLevel;

class EInvoiceValidationTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        
        parent::setUp();

        // $this->markTestSkipped('company model issues');
        $this->makeTestData();

    }

//     public function testEinvoiceValidationEndpointInvoice()
//     {
               
//         $company = Company::factory()->create([
//             'account_id' => $this->account->id,
//             'legal_entity_id' => 123432
//         ]);

// $user = User::factory()->create([
//     'account_id' => $this->account->id,
//     'confirmation_code' => '123',
//     'email' =>  $this->faker->safeEmail(),
// ]);

// $cu = CompanyUserFactory::create($user->id, $company->id, $this->account->id);
// $cu->is_owner = true;
// $cu->is_admin = true;
// $cu->is_locked = false;
// $cu->permissions = '["view_client"]';
// $cu->save();

// $different_company_token = \Illuminate\Support\Str::random(64);

// $company_token = new CompanyToken();
// $company_token->user_id = $user->id;
// $company_token->company_id = $company->id;
// $company_token->account_id = $this->account->id;
// $company_token->name = 'test token';
// $company_token->token = $different_company_token;
// $company_token->is_system = true;
// $company_token->save();

// $data = [
//     'action' => 'archive',
//     'ids' => [
//         $this->client->id
//     ]
// ];


// $c = Client::factory()->create([
//     'company_id' => $company->id,
//     'user_id' => $user->id
// ]);


// ClientContact::factory()->create([
//                 'user_id' => $user->id,
//                 'client_id' => $c->id,
//                 'company_id' => $company->id,
//                 'is_primary' => 1,
//             ]);


// $i = Invoice::factory()->create([
//     'company_id' => $company->id,
//     'user_id' => $user->id,
//     'client_id' => $c->id
// ]);

//         $data =[
//             'entity' => 'invoices',
//             'entity_id' => $i->hashed_id,
//         ];

//         $response = $this->withHeaders([
//                 'X-API-SECRET' => config('ninja.api_secret'),
//                 'X-API-TOKEN' => $different_company_token,
//             ])->postJson('/api/v1/einvoice/validateEntity', $data);

//         $response->assertStatus(200);

//     }

//     public function testEinvoiceValidationEndpoint()
//     {

//         $this->company->legal_entity_id = 123432;
//         $this->company->save();

//         $data =[
//             'entity' => 'companies',
//             'entity_id' => $this->company->hashed_id,
//         ];

//         $response = $this->withHeaders([
//                 'X-API-SECRET' => config('ninja.api_secret'),
//                 'X-API-TOKEN' => $this->token,
//             ])->postJson('/api/v1/einvoice/validateEntity', $data);

//         $response->assertStatus(200);

//         $arr = $response->json();

//     }

    public function testInvalidCompanySettings()
    {
        
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $el = new EntityLevel();
        $validation = $el->checkCompany($company);

        $this->assertFalse($validation['passes']);

    }

    public function testValidBusinessCompanySettings()
    {
        
        $settings = CompanySettings::defaults();
        $settings->address1 = '10 Wallaby Way';
        $settings->city = 'Sydney';
        $settings->state = 'NSW';
        $settings->postal_code = '2113';
        $settings->country_id = '1';
        $settings->vat_number = 'ABN321231232';
        $settings->classification = 'business';

        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'legal_entity_id' => 123231,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $el = new EntityLevel();
        $validation = $el->checkCompany($company);

        $this->assertTrue($validation['passes']);

    }


    public function testInValidBusinessCompanySettingsNoVat()
    {
        
        $settings = CompanySettings::defaults();
        $settings->address1 = '10 Wallaby Way';
        $settings->city = 'Sydney';
        $settings->state = 'NSW';
        $settings->postal_code = '2113';
        $settings->country_id = '1';
        $settings->vat_number = '';
        $settings->classification = 'business';

        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'legal_entity_id' => 123231,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $el = new EntityLevel();
        $validation = $el->checkCompany($company);

        $this->assertFalse($validation['passes']);

    }

    public function testValidIndividualCompanySettingsNoVat()
    {
        
        $settings = CompanySettings::defaults();
        $settings->address1 = '10 Wallaby Way';
        $settings->city = 'Sydney';
        $settings->state = 'NSW';
        $settings->postal_code = '2113';
        $settings->country_id = '1';
        $settings->vat_number = '';
        $settings->id_number ='adfadf';
        $settings->classification = 'individual';

        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'legal_entity_id' => 123231,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $el = new EntityLevel();
        $validation = $el->checkCompany($company);

        $this->assertTrue($validation['passes']);

    }

    public function testInValidBusinessCompanySettingsNoLegalEntity()
    {
        
        $settings = CompanySettings::defaults();
        $settings->address1 = '10 Wallaby Way';
        $settings->city = 'Sydney';
        $settings->state = 'NSW';
        $settings->postal_code = '2113';
        $settings->country_id = '1';
        $settings->vat_number = '';
        $settings->classification = 'business';

        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $el = new EntityLevel();
        $validation = $el->checkCompany($company);

        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettings()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'business',
            'vat_number' => '',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettingsNoCountry()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => null,
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettingsMissingAddress()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => null,
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettingsMissingAddressOnlyCountry()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => 1,
            'address1' => '',
            'address2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettingsMissingAddressOnlyCountryAndAddress1()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => 1,
            'address1' => '10 Wallaby Way',
            'address2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettingsMissingAddressOnlyCountryAndAddress1AndCity()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => 1,
            'address1' => '10 Wallaby Way',
            'address2' => '',
            'city' => 'Sydney',
            'state' => '',
            'postal_code' => '',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);


        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettingsMissingAddressOnlyCountryAndAddress1AndCityAndState()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => 1,
            'address1' => '10 Wallaby Way',
            'address2' => '',
            'city' => 'Sydney',
            'state' => 'NSW',
            'postal_code' => '',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);


        $this->assertFalse($validation['passes']);

    }

    public function testValidIndividualClient()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => 1,
            'address1' => '10 Wallaby Way',
            'address2' => '',
            'city' => 'Sydney',
            'state' => 'NSW',
            'postal_code' => '2113',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertTrue($validation['passes']);

    }

    public function testValidBusinessClient()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'business',
            'vat_number' => 'DE123456789',
            'country_id' => 1,
            'address1' => '10 Wallaby Way',
            'address2' => '',
            'city' => 'Sydney',
            'state' => 'NSW',
            'postal_code' => '2113',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertTrue($validation['passes']);

    }

    public function testInValidBusinessClientNoVat()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'business',
            'vat_number' => '',
            'country_id' => 1,
            'address1' => '10 Wallaby Way',
            'address2' => '',
            'city' => 'Sydney',
            'state' => 'NSW',
            'postal_code' => '2113',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertEquals(0, strlen($client->vat_number));

        $this->assertFalse($validation['passes']);

    }
}