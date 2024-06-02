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

namespace Tests\Feature;

use Tests\TestCase;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use App\DataMapper\CompanySettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 * @covers App\Utils\Traits\CompanySettingsSaver
 */
class CompanySettingsTest extends TestCase
{
    use MakesHash;

    public $company;
    public $token;
    public $user;
    public $faker;
    public $bank_transaction;
    public $account;
    public $payment;
    public $invoice;
    public $expense;
    public $expense_category;
    public $vendor;
    public $bank_transaction_rule;
    public $client;
    public $quote;
    public $settings;
    public $credit;

    protected function setUp(): void
    {
        parent::setUp();


        $data = (new \Tests\TestDataProvider())->init();

        $this->company = $data->company;
        $this->token = $data->token;
        $this->user = $data->user;
        $this->bank_transaction = $data->bank_transaction;
        $this->account = $data->account;
        $this->payment = $data->payment;
        $this->invoice = $data->invoice;
        $this->expense = $data->expense;
        $this->expense_category = $data->expense_category;
        $this->vendor = $data->vendor;
        $this->bank_transaction_rule = $data->bank_transaction_rule;
        $this->client = $data->client;
        $this->quote = $data->quote;
        $this->credit = $data->credit;

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

        $this->withoutExceptionHandling();
    }

    protected function tearDown(): void
    {
        $this->account->forceDelete();
        parent::tearDown();

    }

    public function testClientNumberCantBeModified()
    {
        $settings = $this->company->settings;

        $settings->client_number_counter = 200;

        $this->company->saveSettings($settings, $this->company);


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-Token' => $this->token,
        ])->putJson('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $this->company->toArray());

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($arr['data']['settings']['timezone_id'], 1);

    }

    public function testNullValuesInSettings()
    {
        $settings = $this->company->settings;

        $settings->reset_counter_date = null;

        $this->company->saveSettings($settings, $this->company);

        $response = false;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-Token' => $this->token,
            ])->putJson('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $this->company->toArray());

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($arr['data']['settings']['reset_counter_date'], '');
    }

    public function testIntegerEdgeCases()
    {
        $settings = $this->company->settings;

        $settings->client_number_counter = 'a';
        $settings->invoice_number_counter = 1000;
        $settings->quote_number_counter = 1.2;
        $settings->credit_number_counter = 10.1;

        $this->company->saveSettings($settings, $this->company);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-Token' => $this->token,
            ])->putJson('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $this->company->toArray());

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertTrue(is_int($arr['data']['settings']['client_number_counter']));
        $this->assertTrue(is_int($arr['data']['settings']['invoice_number_counter']));
        $this->assertTrue(is_int($arr['data']['settings']['quote_number_counter']));
        $this->assertTrue(is_int($arr['data']['settings']['credit_number_counter']));
    }

    public function testFloatEdgeCases()
    {
        $settings = $this->company->settings;

        $settings->default_task_rate = 'a';
        $settings->tax_rate1 = 10.0;
        $settings->tax_rate2 = '10.0';
        $settings->tax_rate3 = '10.5';

        $this->company->saveSettings($settings, $this->company);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-Token' => $this->token,
            ])->putJson('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $this->company->toArray());

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($arr['data']['settings']['default_task_rate'], 0);
        $this->assertEquals($arr['data']['settings']['tax_rate1'], 10.0);
        $this->assertEquals($arr['data']['settings']['tax_rate2'], 10.0);
        $this->assertEquals($arr['data']['settings']['tax_rate3'], 10.5);
    }

    public function testBoolEdgeCases()
    {
        $settings = $this->company->settings;

        $settings->require_invoice_signature = true;
        $settings->require_quote_signature = true;
        $settings->show_accept_quote_terms = false;
        $settings->show_accept_invoice_terms = 'TRUE';
        $settings->enable_client_portal_tasks = 'FALSE';

        $this->company->saveSettings($settings, $this->company);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-Token' => $this->token,
            ])->putJson('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $this->company->toArray());

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertTrue($arr['data']['settings']['require_invoice_signature']);
        $this->assertTrue($arr['data']['settings']['require_quote_signature']);
        $this->assertFalse($arr['data']['settings']['show_accept_quote_terms']);
        $this->assertTrue($arr['data']['settings']['show_accept_invoice_terms']);
        $this->assertFalse($arr['data']['settings']['enable_client_portal_tasks']);
    }

    public function testCompanyNullValueMatrixPOST()
    {

        $settings = CompanySettings::defaults();
        $settings->reset_counter_date = null;

        $this->company->saveSettings($settings, $this->company);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-Token' => $this->token,
        ])->postJson('/api/v1/companies?include=company', $this->company->toArray());

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals($arr['data'][0]['company']['settings']['reset_counter_date'], '');
    }

    public function testCompanyWrongValueMatrixPOST()
    {
        $settings = CompanySettings::defaults();
        $settings->reset_counter_date = 1;

        $this->company->saveSettings($settings, $this->company);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-Token' => $this->token,
        ])->postJson('/api/v1/companies?include=company', $this->company->toArray());

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals($arr['data'][0]['company']['settings']['reset_counter_date'], '');
    }

    public function testCompanyWrong2ValueMatrixPOST()
    {
        $settings = CompanySettings::defaults();
        $settings->reset_counter_date = '1';

        $this->company->saveSettings($settings, $this->company);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-Token' => $this->token,
        ])->postJson('/api/v1/companies?include=company', $this->company->toArray());

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals($arr['data'][0]['company']['settings']['reset_counter_date'], '1');
    }

    public function testCompanyrightValueMatrixPOST()
    {
        $settings = CompanySettings::defaults();
        $settings->reset_counter_date = '1/1/2000';

        $this->company->saveSettings($settings, $this->company);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-Token' => $this->token,
        ])->postJson('/api/v1/companies?include=company', $this->company->toArray());

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals($arr['data'][0]['company']['settings']['reset_counter_date'], '1/1/2000');
    }
}
