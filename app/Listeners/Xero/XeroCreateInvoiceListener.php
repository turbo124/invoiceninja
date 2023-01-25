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
use Illuminate\Support\Carbon;
use \XeroPHP\Application;
use \XeroPHP\Models\Accounting\Contact;
use \XeroPHP\Models\Accounting\Address;
use \XeroPHP\Models\Accounting\Phone;
use \XeroPHP\Models\Accounting\Invoice;
use \XeroPHP\Models\Accounting\LineItem;

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

        // $access_token = $provider->getAccessToken('refresh_token', [
        //     'refresh_token' => $user->xero_oauth_refresh_token
        // ]);

        $access_token = $provider->getAccessToken('refresh_token', [ 
            'grant_type' => 'refresh_token', 'refresh_token' => $user->xero_oauth_refresh_token
        ]);

        $user->xero_oauth_refresh_token = $access_token->getRefreshToken();
        $user->save();

        $this->xero = new Application($access_token, $tenant);

        $invoice = new Invoice($this->xero);
        $invoice->setType(Invoice::INVOICE_TYPE_ACCREC);
        $invoice->setStatus(Invoice::INVOICE_STATUS_AUTHORISED);
        $invoice->setContact($this->findOrCreateContact());
        $invoice->setDate(Carbon::parse($this->invoice->date)->toDateTime());
        $invoice->setDueDate(Carbon::parse($this->invoice->due_date)->toDateTime());
        $invoice->setCurrencyCode($this->invoice->client->currency()->code);
        $invoice->setInvoiceNumber($this->invoice->number);

        foreach($this->invoice->line_items as $item)
        {

            $lineItem = new LineItem();
            $lineItem->setDescription($item->notes)
                     ->setQuantity($item->quantity)
                     ->setAccountCode(200)
                     ->setUnitAmount($item->cost)
                     ->setItemCode($item->product_key)
                     ->setTaxType("OUTPUT");

            $invoice->addLineItem($lineItem);
   
        }

        $invoice->save();
        // $invoice = $this->xero->save($invoice, true);

        $this->invoice->customn_value1 = $invoice->getInvoiceId();
        $this->invoice->save();
        
    }

    private function findOrCreateContact(): Contact
    {
        $contact = false;
        //see if we have the GUID
        if(strlen($this->invoice->client->id_number) > 5){
            $contact = $this->xero->loadByGUID(Contact::class, $this->invoice->client->id_number);
        }

        if(!$contact) 
        {
            //search by email
            $primary_contact = $this->invoice->client->primary_contact()->first();

            if($primary_contact && strlen($primary_contact->email) > 5)

            $contacts = $this->xero->load(Contact::class)
                ->where('EmailAddress', $primary_contact->email)
                ->execute();
            
            if(count($contacts) >= 1)
                $contact = $contacts[0];

        }

        //final try, search by VAT_NUMBER / ABN Number
        if(!$contact && strlen($this->invoice->client->vat_number) > 5) 
        {

            $contacts = $this->xero->load(Contact::class)
                ->where('TaxNumber', $this->invoice->client->vat_number)
                ->execute();

                if(count($contacts) >= 1)
                    $contact = $contacts[0];            
        
        }

        if(!$contact && strlen($this->invoice->client->name) > 2) 
        {

            $contacts = $this->xero->load(Contact::class)
                ->where('Name', $this->invoice->client->name)
                ->execute();

                if(count($contacts) >= 1)
                    $contact = $contacts[0];            
        
        }

        //create new contact here
        if(!$contact)
        {
            $address = new Address();
            $address->setAddressLine1($this->invoice->client->address1)
                    ->setAddressLine2($this->invoice->client->address2)
                    ->setCity($this->invoice->client->city)
                    ->setRegion($this->invoice->client->state)
                    ->setPostalCode($this->invoice->client->postal_code)
                    ->setCountry($this->invoice->client->country->name)
                    ->setAddressType('STREET');

            $contact = new Contact($this->xero);
            $contact->setName($this->invoice->client->present()->name())
                    ->setEmailAddress($this->invoice->client->present()->email())
                    ->setFirstName($this->client->present()->first_name())
                    ->setLastName($this->client->present()->first_name())
                    ->setCompanyNumber($this->client->vat_number)
                    ->setContactStatus('ACTIVE')
                    ->addAddress($address);

            if(strlen($this->invoice->client->shipping_address1) > 2 || strlen($this->invoice->client->shipping_address2) > 2)
            {

                $shipping_address = new Address();
                $shipping_address->setAddressLine1($this->invoice->client->shipping_address1)
                        ->setAddressLine2($this->invoice->client->shipping_address2)
                        ->setCity($this->invoice->client->shipping_city)
                        ->setRegion($this->invoice->client->shipping_state)
                        ->setPostalCode($this->invoice->client->shipping_postal_code)
                        ->setCountry($this->invoice->client->shipping_country->name)
                        ->setAddressType('DELIVERY');

                $contact->addAddress($shipping_address);
            }

            if(strlen($this->invoice->client->present()->phone() > 1))
            {
                $phone = new Phone();
                $phone->setPhoneType('DEFAULT');
                $phone->setPhoneNumber($this->client->present()->phone());
                $contact->addPhone($phone);
            }

            $contact = $this->xero->save($contact, true);
        }

        return $contact;
    }
}
