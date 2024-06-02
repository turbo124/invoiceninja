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

use App\Http\Middleware\PasswordProtection;
use App\Models\CompanyToken;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\TokenController
 */
class CompanyTokenApiTest extends TestCase
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

        $this->account->forceDelete();
    }

    public function testCompanyTokenListFilter()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get('/api/v1/tokens?filter=xx');

        $response->assertStatus(200);
    }

    public function testCompanyTokenList()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get('/api/v1/tokens');

        $response->assertStatus(200);
    }

    public function testCompanyTokenPost()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $data = [
            'name' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tokens', $data);

        $response->assertStatus(200);
    }

    public function testCompanyTokenPut()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = CompanyToken::whereCompanyId($this->company->id)->first();

        $data = [
            'name' => 'newname',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/tokens/'.$this->encodePrimaryKey($company_token->id), $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals('newname', $arr['data']['name']);
    }

    public function testCompanyTokenGet()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = CompanyToken::whereCompanyId($this->company->id)->first();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tokens/'.$this->encodePrimaryKey($company_token->id));

        $response->assertStatus(200);
    }

    public function testCompanyTokenNotArchived()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = CompanyToken::whereCompanyId($this->company->id)->first();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tokens/'.$this->encodePrimaryKey($company_token->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }
}
