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
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 * @covers App\Http\Controllers\InvoiceController
 */
class ClientDeletedInvoiceCreationTest extends TestCase
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

    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // $this->account->forceDelete();
    }

    public function testClientedDeletedAttemptingToCreateInvoice()
    {
        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude',
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/invoices/', $data)
            ->assertStatus(200);

        $this->client->is_deleted = true;
        $this->client->save();

        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude2',
        ];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/', $data)
        ->assertStatus(422);
    }
}
