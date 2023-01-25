<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Xero;

use App\Libraries\MultiDB;
use App\Models\Invoice as NinjaInvoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use \XeroPHP\Application;
use \XeroPHP\Models\Accounting\Contact;

class XeroCreateInvoiceListener implements ShouldQueue
{
    
    protected Application $xero;

    protected NinjaInvoice $invoice;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        MultiDB::setDb($event->company->db);

        if(!$event->company->xero_sync_invoices || !$event->company->xero_tenant()->exists())
            return;

        $tenant = $event->company->xero_tenant->tenant_id;
        $user = $event->company->xero_tenant->user;
        $this->invoice = $event->invoice;
        $xero_contact = $this->findOrCreateContact();

        $provider = new \Calcinai\OAuth2\Client\Provider\Xero([
            'clientId'          => config('services.xero.client_id'),
            'clientSecret'      => config('services.xero.client_secret'),
        ]);

        $access_token = $provider->getAccessToken('refresh_token', [
            'refresh_token' => $user->xero_oauth_refresh_token
        ]);

        $user->xero_oauth_refresh_token = $access_token->getRefreshToken();
        $user->save();

        $this->xero = new Application($access_token, $tenant);

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

    }

    private function findOrCreateContact(): Contact
    {
        $contact = false;
        //see if we have the GUID
        if(strlen($this->invoice->client->id_number) > 5){
            $contact = $this->xero->loadByGUID(Contact::class, $this->invoice->client->id_number);
        }
        else {

            //search by email
            $primary_contact = $this->invoice->client->primary_contact()->first();

            if($primary_contact && strlen($primary_contact->email) > 5)

            $contacts = $this->xero->load(Contact::class)
                ->where('EmailAddress', $primary_contact->email)
                ->execute();
            
            if(count($contacts) >= 1)
                $contact = $contacts[0];
            else  {

                //final try, search by VAT_NUMBER / ABN Number
                if(strlen($this->invoice->client->vat_number) > 5){

                    $contacts = $this->xero->load(Contact::class)
                        ->where('TaxNumber', $this->invoice->client->vat_number)
                        ->execute();

                        if(count($contacts) >= 1)
                            $contact = $contacts[0];
                }

            }



        }


        return $contact;
    }
}
