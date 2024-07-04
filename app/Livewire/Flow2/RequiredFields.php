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

namespace App\Livewire\Flow2;

use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\CompanyGateway;
use App\Utils\Traits\WithSecureContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Component;

class RequiredFields extends Component
{
    use WithSecureContext;

    public ?CompanyGateway $company_gateway;

    public ?string $client_name;
    public ?string $contact_first_name;
    public ?string $contact_last_name;
    public ?string $contact_email;
    public ?string $client_phone;
    public ?string $client_address_line_1;
    public ?string $client_city;
    public ?string $client_state;
    public ?string $client_country_id;
    public ?string $client_postal_code;
    public ?string $client_shipping_address_line_1;
    public ?string $client_shipping_city;
    public ?string $client_shipping_state;
    public ?string $client_shipping_postal_code;
    public ?string $client_shipping_country_id;
    public ?string $client_custom_value1;
    public ?string $client_custom_value2;
    public ?string $client_custom_value3;
    public ?string $client_custom_value4;

    /** @var array<int, string> */
    public array $fields = [];

    private int $unfilled_fields = 0;

    private $mappings = [
        'client_name' => 'name',
        'client_website' => 'website',
        'client_phone' => 'phone',

        'client_address_line_1' => 'address1',
        'client_address_line_2' => 'address2',
        'client_city' => 'city',
        'client_state' => 'state',
        'client_postal_code' => 'postal_code',
        'client_country_id' => 'country_id',

        'client_shipping_address_line_1' => 'shipping_address1',
        'client_shipping_address_line_2' => 'shipping_address2',
        'client_shipping_city' => 'shipping_city',
        'client_shipping_state' => 'shipping_state',
        'client_shipping_postal_code' => 'shipping_postal_code',
        'client_shipping_country_id' => 'shipping_country_id',

        'client_custom_value1' => 'custom_value1',
        'client_custom_value2' => 'custom_value2',
        'client_custom_value3' => 'custom_value3',
        'client_custom_value4' => 'custom_value4',

        'contact_first_name' => 'first_name',
        'contact_last_name' => 'last_name',
        'contact_email' => 'email',
        // 'contact_phone' => 'phone',
    ];

    public $client_address_array = [
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country_id',
    ];

    protected $rules = [
        // 'client.address1' => '',
        // 'client.address2' => '',
        // 'client.city' => '',
        // 'client.state' => '',
        // 'client.postal_code' => '',
        // 'client.country_id' => '',
        // 'client.shipping_address1' => '',
        // 'client.shipping_address2' => '',
        // 'client.shipping_city' => '',
        // 'client.shipping_state' => '',
        // 'client.shipping_postal_code' => '',
        // 'client.shipping_country_id' => '',
        // 'contact.first_name' => '',
        // 'contact.last_name' => '',
        // 'contact.email' => '',
        // 'client.name' => '',
        // 'client.website' => '',
        // 'client.phone' => '',
        // 'client.custom_value1' => '',
        // 'client.custom_value2' => '',
        // 'client.custom_value3' => '',
        // 'client.custom_value4' => '',
        'client_name' => '',
        'client_website' => '',
        'client_phone' => '',
        'client_address_line_1' => '',
        'client_address_line_2' => '',
        'client_city' => '',
        'client_state' => '',
        'client_postal_code' => '',
        'client_country_id' => '',
        'client_shipping_address_line_1' => '',
        'client_shipping_address_line_2' => '',
        'client_shipping_city' => '',
        'client_shipping_state' => '',
        'client_shipping_postal_code' => '',
        'client_shipping_country_id' => '',
        'client_custom_value1' => '',
        'client_custom_value2' => '',
        'client_custom_value3' => '',
        'client_custom_value4' => '',
        'contact_first_name' => '',
        'contact_last_name' => '',
        'contact_email' => '',
    ];

    public bool $is_loading = true;

    public function mount(): void
    {
        MultiDB::setDB(
            $this->getContext()['db'],
        );

        $this->fields = $this->getContext()['fields'];

        $this->company_gateway = CompanyGateway::withTrashed()
            ->with('company')
            ->find($this->getContext()['company_gateway_id']);

        $contact = auth()->user();

        $this->client_name = $contact->client->name;
        $this->contact_first_name = $contact->first_name;
        $this->contact_last_name = $contact->last_name;
        $this->contact_email = $contact->email;
        $this->client_phone = $contact->client->phone;
        $this->client_address_line_1 = $contact->client->address1;
        $this->client_city = $contact->client->city;
        $this->client_state = $contact->client->state;
        $this->client_country_id = $contact->client->country_id;
        $this->client_postal_code = $contact->client->postal_code;
        $this->client_shipping_address_line_1 = $contact->client->shipping_address1;
        $this->client_shipping_city = $contact->client->shipping_city;
        $this->client_shipping_state = $contact->client->shipping_state;
        $this->client_shipping_postal_code = $contact->client->shipping_postal_code;
        $this->client_shipping_country_id = $contact->client->shipping_country_id;
        $this->client_custom_value1 = $contact->client->custom_value1;
        $this->client_custom_value2 = $contact->client->custom_value2;
        $this->client_custom_value3 = $contact->client->custom_value3;
        $this->client_custom_value4 = $contact->client->custom_value4;

        $this->check();

        if ($this->unfilled_fields === 0) {
            $this->dispatch('required-fields');
        }

        if ($this->unfilled_fields > 0) {
            $this->is_loading = false;
        }
    }

    public function check(): void
    {
        $_contact = auth()->user();

        foreach ($this->fields as $index => $field) {
            $_field = $this->mappings[$field['name']];

            if (Str::startsWith($field['name'], 'client_')) {
                if (empty($_contact->client->{$_field})
                   || is_null($_contact->client->{$_field})
                ) {
                    // $this->show_form = true;
                    $this->unfilled_fields++;
                } else {
                    $this->fields[$index]['filled'] = true;
                }
            }

            if (Str::startsWith($field['name'], 'contact_')) {
                if (empty($_contact->{$_field}) || is_null($_contact->{$_field}) || str_contains($_contact->{$_field}, '@example.com')) {
                    $this->unfilled_fields++;
                } else {
                    $this->fields[$index]['filled'] = true;
                }
            }
        }

        // @todo: Double check if this is still supported in flow2.

        // if ($this->unfilled_fields === 0 && (!$this->company_gateway->always_show_required_fields || $this->is_subscription)) {
        //     $this->dispatch(
        //         'passed-required-fields-check',
        //         client_postal_code: $_contact->client->postal_code
        //     );
        // }
    }

    
    public function handleSubmit(array $data): bool
    {
        MultiDB::setDb($this->getContext()['db']);

        $contact = auth()->user();

        $rules = [];

        collect($this->fields)->map(function ($field) use (&$rules) {
            if (! array_key_exists('filled', $field)) {
                $rules[$field['name']] = array_key_exists('validation_rules', $field)
                    ? $field['validation_rules']
                    : 'required';
            }
        });

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            session()->flash('validation_errors', $validator->getMessageBag()->getMessages());

            return false;
        }

        if ($this->updateClientDetails($data)) {
            $this->dispatch('required-fields');

            //if stripe is enabled, we want to update the customer at this point.

            return true;
        }

        // TODO: Throw an exception about not being able to update the profile.
        return false;
    }

    public function updateClientDetails(array $data): bool
    {
        $client = [];
        $contact = [];

        MultiDB::setDb($this->getContext()['db']);

        $_contact = auth()->user();

        foreach ($data as $field => $value) {
            if (Str::startsWith($field, 'client_')) {
                $client[$this->mappings[$field]] = $value;
            }

            if (Str::startsWith($field, 'contact_')) {
                $contact[$this->mappings[$field]] = $value;
            }
        }


        $_contact->first_name = $this->contact_first_name;
        $_contact->last_name = $this->contact_last_name;
        $_contact->client->name = $this->client_name;
        $_contact->email = $this->contact_email;
        $_contact->client->phone = $this->client_phone;
        $_contact->client->address1 = $this->client_address_line_1;
        $_contact->client->city  = $this->client_city;
        $_contact->client->state = $this->client_state;
        $_contact->client->country_id = $this->client_country_id;
        $_contact->client->postal_code = $this->client_postal_code;
        $_contact->client->shipping_address1 = $this->client_shipping_address_line_1;
        $_contact->client->shipping_city = $this->client_shipping_city;
        $_contact->client->shipping_state = $this->client_shipping_state;
        $_contact->client->shipping_postal_code = $this->client_shipping_postal_code;
        $_contact->client->shipping_country_id = $this->client_shipping_country_id;
        $_contact->client->custom_value1 = $this->client_custom_value1;
        $_contact->client->custom_value2 = $this->client_custom_value2;
        $_contact->client->custom_value3 = $this->client_custom_value3;
        $_contact->client->custom_value4 = $this->client_custom_value4;
        $_contact->push();


        $_contact
            ->fill($contact)
            ->push();

        $_contact->client
            ->fill($client)
            ->push();

        if ($_contact) {
            /** @var \App\Models\CompanyGateway $cg */
            $cg = CompanyGateway::find($this->getContext()['company_gateway_id']);

            if ($cg && $cg->update_details) {
                $payment_gateway = $cg->driver($_contact->client)->init();

                if (method_exists($payment_gateway, "updateCustomer")) {
                    $payment_gateway->updateCustomer();
                }
            }

            return true;
        }

        return false;
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return render('flow2.required-fields', [
            'contact' => $this->getContext()['contact'],
        ]);
    }
}
