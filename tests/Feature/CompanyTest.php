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

use App\DataMapper\CompanySettings;
use App\Http\Middleware\PasswordProtection;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\TaxRate;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\CompanyController
 */
class CompanyTest extends TestCase
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
            \Illuminate\Routing\Middleware\ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

        $this->withoutExceptionHandling();
    }


    protected function tearDown(): void
    {
        parent::tearDown();
        //$this->account->forceDelete();
    }
    public function testEnsureStrReplace()
    {
        $x = '**********';

        $new_string = str_replace("*", "", $x);

        $this->assertEquals(0, strlen($new_string));
    }

    public function testCompanyTaxInit()
    {
        TaxRate::query()->delete();

        $settings = $this->company->settings;
        $settings->country_id = '40';
        $this->company->saveSettings($settings, $this->company);

        $this->company->service()->localizeCompany($this->user);

        $this->assertEquals(1, TaxRate::count());
    }

    public function testCompanyLogoInline()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get("/api/v1/companies/{$this->company->hashed_id}/logo");

        $response->assertStatus(200);
        $response->streamedContent();

    }

    public function testUpdateCompanyPropertyInvoiceTaskHours()
    {
        $company_update = [
            'invoice_task_hours' => true
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $company_update);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertTrue($arr['data']['invoice_task_hours']);


        $company_update = [
            'invoice_task_hours' => false
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $company_update)
            ->assertStatus(200);


        $arr = $response->json();

        $this->assertFalse($arr['data']['invoice_task_hours']);
    }

    public function testCompanyList()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        // $cc = Company::first();
        // $cc->delete();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/companies');

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post(
            '/api/v1/companies?include=company',
            [
                'name' => 'A New Company',
                'logo' => UploadedFile::fake()->image('avatar.jpg'),
            ]
        )
        ->assertStatus(200)->decodeResponseJson();

        $company = Company::find($this->decodePrimaryKey($response['data'][0]['company']['id']));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post(
            '/api/v1/companies/',
            [
                'name' => 'A New Company',
                'company_logo' => UploadedFile::fake()->create('avatar.pdf', 100),
            ]
        )
        ->assertStatus(302);

        //  Log::error($company);

        $this->token = CompanyToken::whereCompanyId($company->id)->first()->token;

        $company_update = [
            'name' => 'CHANGE NAME',
            //   'logo' => UploadedFile::fake()->image('avatar.jpg')
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/companies/'.$this->encodePrimaryKey($company->id), $company_update)
            ->assertStatus(200);

        $settings = CompanySettings::defaults();
        $settings->custom_value1 = 'test';
        $settings->invoice_design_id = '2';
        $settings->quote_design_id = '1';

        $company->settings = $settings;

        // nlog($company->toArray());

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/companies/'.$this->encodePrimaryKey($company->id), $company->toArray())
        ->assertStatus(200)->decodeResponseJson();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/companies/'.$this->encodePrimaryKey($company->id))
        ->assertStatus(200)->decodeResponseJson();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->delete('/api/v1/companies/'.$this->encodePrimaryKey($company->id))
        ->assertStatus(200);
    }
}
