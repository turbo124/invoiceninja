<?php

namespace Tests\Feature;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\InvoiceFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\InvoiceController
 */
class InvoiceTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();
    }

    public function testInvoiceList()
    {
        factory(\App\Models\Client::class, 1)->create(['user_id' => $this->user->id, 'company_id' => $this->company->id])->each(function ($c) {
            factory(\App\Models\ClientContact::class, 1)->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
            ]);

            factory(\App\Models\ClientContact::class, 1)->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
            ]);
        });

        $client = Client::all()->first();

        factory(\App\Models\Invoice::class, 1)->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/invoices');

        $response->assertStatus(200);
    }

    public function testInvoiceRESTEndPoints()
    {
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id).'/edit');

        $response->assertStatus(200);

        $invoice_update = [
            'tax_name1' => 'dippy',
        ];

        $this->assertNotNull($this->invoice);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id), $invoice_update)
            ->assertStatus(200);
    }

    public function testPostNewInvoice()
    {
        $invoice = [
            'status_id' => 1,
            'number' => 'dfdfd',
            'discount' => 0,
            'is_amount_discount' => 1,
            'po_number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'client_id' => $this->encodePrimaryKey($this->client->id),
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/invoices/', $invoice)
            ->assertStatus(200);

        //test that the same request should produce a validation error due
        //to duplicate number being used.
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/invoices/', $invoice)
            ->assertStatus(302);
    }

    public function testDeleteInvoice()
    {
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->delete('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id));

        $response->assertStatus(200);
    }

    public function testUniqueNumberValidation()
    {
        /* stub a invoice in the DB that we will use to test against later */
        $invoice = factory(\App\Models\Invoice::class)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'number' => 'test',
        ]);

        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $data)
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('dude', $arr['data']['number']);

        /*test validation fires*/
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'test',
        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/invoices/'.$arr['data']['id'], $data)
            ->assertStatus(302);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            info('inside update invoice validator');
            info($message);
            $this->assertNotNull($message);
        }

        $data = [
                'client_id' => $this->client->hashed_id,
                'number' => 'style',
            ];

        /* test number passed validation*/
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/invoices/'.$arr['data']['id'], $data)
            ->assertStatus(200);

        $data = [
                'client_id' => $this->client->hashed_id,
                'number' => 'style',
            ];

        /* Make sure we can UPDATE using the same number*/
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/invoices/'.$arr['data']['id'], $data)
            ->assertStatus(200);
    }
}
