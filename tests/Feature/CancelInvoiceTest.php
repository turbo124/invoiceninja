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

use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Services\Invoice\HandleCancellation
 */
class CancelInvoiceTest extends TestCase
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

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

        $this->withoutExceptionHandling();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->company->account->forceDelete();
    }


    public function testCancelInvoice()
    {
        $this->assertTrue($this->invoice->invoiceCancellable($this->invoice));

        $client_balance = $this->client->balance;
        $invoice_balance = $this->invoice->balance;

        $this->assertEquals(Invoice::STATUS_SENT, $this->invoice->status_id);

        $this->invoice->fresh()->service()->handleCancellation()->save();

        $this->assertEquals(0, $this->invoice->fresh()->balance);
        $this->assertEquals($this->client->fresh()->balance, ($client_balance - $invoice_balance));
        $this->assertNotEquals($client_balance, $this->client->fresh()->balance);
        $this->assertEquals(Invoice::STATUS_CANCELLED, $this->invoice->fresh()->status_id);
    }
}
