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
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 * @covers  App\Models\Presenters\ClientPresenter
 */
class ClientPresenterTest extends TestCase
{
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

    public function testCompanyName()
    {
        $settings = $this->client->company->settings;

        $settings->name = 'test';
        $this->client->company->settings = $settings;
        $this->client->company->save();

        $this->client->getSetting('name');

        $merged_settings = $this->client->getMergedSettings();

        $name = $this->client->present()->company_name();

        $this->assertEquals('test', $merged_settings->name);
        $this->assertEquals('test', $name);
    }

    public function testCompanyAddress()
    {
        $this->assertNotNull($this->client->present()->company_address());
    }
}
