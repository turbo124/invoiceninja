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

namespace Tests\Feature\Xero;

use App\Models\User;
use App\Models\XeroTenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use League\OAuth2\Client\Token\AccessToken as AccessToken;
use Tests\MockAccountData;
use Tests\TestCase;
use \XeroPHP\Models\Accounting\Account;
use \XeroPHP\Models\Accounting\Contact;
use \XeroPHP\Models\Accounting\Invoice;
use \XeroPHP\Models\Accounting\LineItem;
use \XeroPHP\Models\Accounting\TaxRate;

/**
 * @test
 * @covers App\Http\Controllers\XeroTenantController
 */
class XeroApiTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();
    
    }

    public function testShowXeroTenant()
    {
    
        $xt = new XeroTenant;
        $xt->tenant_id = rand(1,10000000);
        $xt->tenant_name = $this->faker->words(2,true);
        $xt->tenant_type = 'ORGANISATION';
        $xt->account_id = $this->account->id;
        $xt->company_id = $this->company->id;
        $xt->user_id = $this->user->id;
        $xt->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/xero_tenants/'.$xt->hashed_id);

        $response->assertStatus(200);


        $data = [
            'tenant_name' => "SHINY",
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/xero_tenants/'.$xt->hashed_id, $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('SHINY', $arr['data']['tenant_name']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/xero_tenants/'.$xt->hashed_id);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertTrue($arr['data']['is_deleted']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/xero_tenants/bulk', ['ids' => [$xt->hashed_id], 'action' => 'restore']);

        $response->assertStatus(200);

        $arr = $response->json();


        $this->assertFalse($arr['data'][0]['is_deleted']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/xero_tenants/bulk', ['ids' => [$xt->hashed_id], 'action' => 'archive']);

        $arr = $response->json();

        $this->assertNotEquals(0, $arr['data'][0]['archived_at']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/xero_tenants/bulk', ['ids' => [$xt->hashed_id], 'action' => 'delete']);

        $arr = $response->json();

        $this->assertNotEquals(0, $arr['data'][0]['archived_at']);
        $this->assertTrue($arr['data'][0]['is_deleted']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/xero_tenants/bulk', ['ids' => [$xt->hashed_id], 'action' => 'restore']);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
        $this->assertFalse($arr['data'][0]['is_deleted']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/xero_tenants/link/'.$xt->hashed_id.'/');

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEmpty($arr['data']['company_id']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/xero_tenants/link/'.$xt->hashed_id.'/'.$this->company->company_key);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertNotEmpty($arr['data']['company_id']);


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/xero_tenants/link/'.$xt->hashed_id.'/');

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEmpty($arr['data']['company_id']);

    }


    public function testRefreshToken()
    {
        // $this->markTestSkipped('not now');

        $user = User::where('email','small@example.com')->first();

        $provider = new \Calcinai\OAuth2\Client\Provider\Xero([
            'clientId'          => config('services.xero.client_id'),
            'clientSecret'      => config('services.xero.client_secret'),
        ]);

        $newAccessToken = $provider->getAccessToken('refresh_token', [
            'refresh_token' => $user->xero_oauth_refresh_token
        ]);

        $this->assertNotNull($newAccessToken);
        $this->assertInstanceOf(AccessToken::class, $newAccessToken);

        $this->assertNotEquals($user->xero_oauth_refresh_token, $newAccessToken->getRefreshToken());

        $user->xero_oauth_refresh_token = $newAccessToken->getRefreshToken();
        $user->save();

        $xero = new \XeroPHP\Application($newAccessToken, 'b9967359-c7b9-4626-a2a0-96a96ce75ce3');

        $contacts = $xero->load(\XeroPHP\Models\Accounting\Contact::class)->execute();

        $this->assertNotNull($contacts);
        // nlog($contacts);
        // 
        $contactId = 'c0b4b5a3-3223-4345-b9ad-1266ab7a23fa';
        $contact = $xero->loadByGUID(Contact::class, $contactId);
        $contact->setEmailAddress('david@invoiceninja.com');
        $contact->save();

        $contacts = $xero->load(Contact::class)
            ->where('EmailAddress', 'david@invoiceninja.com')
            ->execute();
        
        nlog(count($contacts));

        foreach($contacts as $contact)
        {
            nlog($contact->toStringArray());
        }


        // $invoices = $xero->load(\XeroPHP\Models\Accounting\Invoice::class)->execute();

        //nlog($invoices);


        // $invoice = new Invoice($xero);
        // $invoice->setType(Invoice::INVOICE_TYPE_ACCREC);
        // $invoice->setStatus(Invoice::INVOICE_STATUS_AUTHORISED);
        // $invoice->setContact($contact);
        // $invoice->setDueDate(now()->toDateTime());
        // $invoice->setDate(now()->toDateTime());
        // $invoice->setCurrencyCode('AUD');

        // $lineItem = new LineItem();
        // $lineItem->setDescription("test")
        //          ->setQuantity(1)
        //          ->setAccountCode(200)
        //          ->setUnitAmount(100)
        //          ->setTaxType("OUTPUT");

        // $invoice->addLineItem($lineItem);

        // $invoice = $xero->save($invoice, true);


        // $invoice = $xero->loadByGUID(Invoice::class, '15a233a2-737f-4fc4-a9f7-a44416fe705c');
        // $invoice->LineItems->removeAll();
//         $items = $invoice->getLineItems();

// nlog($items);

//         foreach($items as $item)
//         {
//             $item->setLineItemID('');
//             $item->save($item, true);
//         }

        // $invoice = $xero->loadByGUID(Invoice::class, '15a233a2-737f-4fc4-a9f7-a44416fe705c');

        // $lineItem = new LineItem();
        // $lineItem->setDescription("Here we go!!")
        //          ->setQuantity(3)
        //          ->setAccountCode(200)
        //          ->setUnitAmount(300)
        //          ->setTaxType("OUTPUT");

        // $invoice->addLineItem($lineItem);
        // $invoice->setInvoiceNumber("Ninja-0001");
        // $invoice = $xero->save($invoice, true);

    // $lineItem = new RepeatingInvoice\LineItem();
    // $lineItem->setDescription('Testing repeating invoices')
    //     ->setQuantity(1)
    //     ->setAccountCode('200')
    //     ->setUnitAmount(200);//auto calculates subtotal and VAT 20%

    // $invoice->addLineItem($lineItem);

    // // dd($invoice);//DATA HERE LOOK OK

    // //save the invoice and submit to Xero
    // $invoice = $xero->save($invoice, true);
    
    // dd($invoice);// null nothing returned and no error


    }

}
