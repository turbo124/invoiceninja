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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;
use League\OAuth2\Client\Token\AccessToken as AccessToken;
use \XeroPHP\Models\Accounting\Invoice;
use \XeroPHP\Models\Accounting\Contact;
use \XeroPHP\Models\Accounting\LineItem;
use \XeroPHP\Models\Accounting\Account;
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
    }

    public function testRefreshToken()
    {

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

        $invoices = $xero->load(\XeroPHP\Models\Accounting\Invoice::class)->execute();

        //nlog($invoices);

        foreach($invoices as $invoice){

            nlog($invoice);
            // nlog($invoice->getAccountType());

        }

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


        $invoice = $xero->loadByGUID(Invoice::class, '15a233a2-737f-4fc4-a9f7-a44416fe705c');
        $invoice->LineItems->removeAll();
//         $items = $invoice->getLineItems();

// nlog($items);

//         foreach($items as $item)
//         {
//             $item->setLineItemID('');
//             $item->save($item, true);
//         }

        // $invoice = $xero->loadByGUID(Invoice::class, '15a233a2-737f-4fc4-a9f7-a44416fe705c');

        $lineItem = new LineItem();
        $lineItem->setDescription("something that is updated")
                 ->setQuantity(3)
                 ->setAccountCode(200)
                 ->setUnitAmount(300)
                 ->setTaxType("OUTPUT");

        $invoice->addLineItem($lineItem);
        $invoice = $xero->save($invoice, true);

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
