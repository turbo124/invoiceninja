<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire;

use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Component;

class SubscriptionPlanSwitch extends Component
{
    /**
     * @var \App\Models\RecurringInvoice
     */
    public $recurring_invoice;

    /**
     * @var Subscription
     */
    public $subscription;

    /**
     * @var ?float
     */
    public $amount;

    /**
     * @var Subscription
     */
    public $target;

    /**
     * @var ClientContact
     */
    public $contact;

    /**
     * @var array
     */
    public $methods = [];

    /**
     * @var string
     */
    public $total;

    public ?string $contact_first_name;

    public ?string $contact_last_name;

    public ?string $contact_email;

    public $hide_button = false;
    /**
     * @var array
     */
    public $state = [
        'payment_initialised' => false,
        'show_loading_bar' => false,
        'invoice' => null,
        'company_gateway_id' => null,
        'payment_method_id' => null,
    ];

    /**
     * @var mixed|string
     */
    public $hash;

    public $company;

    public function mount()
    {
        MultiDB::setDb($this->company->db);

        $this->total = $this->amount;

        $this->methods = $this->contact->client->service()->getPaymentMethods($this->amount);

        $this->contact_first_name = $this->contact->first_name;
        $this->contact_last_name = $this->contact->last_name;
        $this->contact_email = $this->contact->email;

        $this->state['check_rff'] = false;


        $this->hash = Str::uuid()->toString();
    }

    public function handleBeforePaymentEvents(): void
    {
        $this->state['show_loading_bar'] = true;

        // $payment_required = $this->target->service()->changePlanPaymentCheck([
        //     'recurring_invoice' => $this->recurring_invoice,
        //     'subscription' => $this->subscription,
        //     'target' => $this->target,
        //     'hash' => $this->hash,
        // ]);

        $payment_amount = $this->target->link_service()->calculateUpgradePriceV2($this->recurring_invoice, $this->target);

        if ($payment_amount > 0) {
            $this->state['invoice'] = $this->target->link_service()->createChangePlanInvoice([
                'recurring_invoice' => $this->recurring_invoice,
                'subscription' => $this->subscription,
                'target' => $this->target,
                'hash' => $this->hash,
            ]);

            Cache::put(
                $this->hash,
                [
                'subscription_id' => $this->target->hashed_id,
                'target_id' => $this->target->hashed_id,
                'recurring_invoice' => $this->recurring_invoice->hashed_id,
                'client_id' => $this->recurring_invoice->client->hashed_id,
                'invoice_id' => $this->state['invoice']->hashed_id,
                'context' => 'change_plan',
                now()->addMinutes(60), ]
            );

            $this->state['payment_initialised'] = true;
        } else {
            $this->handlePaymentNotRequired();
        }

        $this->dispatch('beforePaymentEventsCompleted');
    }

    /**
     * Middle method between selecting payment method &
     * submitting the from to the backend.
     *
     * @param $company_gateway_id
     * @param $gateway_type_id
     */
    public function handleMethodSelectingEvent($company_gateway_id, $gateway_type_id)
    {
        $this->state['company_gateway_id'] = $company_gateway_id;
        $this->state['payment_method_id'] = $gateway_type_id;

        $this->handleBeforePaymentEvents();
    }

    public function handlePaymentNotRequired()
    {
        $this->hide_button = true;

        $response = $this->target->link_service()->handleNoPaymentRequired([
            'email' => $this->contact->email,
            'quantity' => 1,
            'contact_id' => $this->contact->id,
            'client_id' => $this->contact->client_id,
            'coupon' => '',
            // $response =  $this->target->service()->createChangePlanCreditV2([
            // 'recurring_invoice' => $this->recurring_invoice,
            // 'subscription' => $this->subscription,
            // 'target' => $this->target,
            // 'hash' => $this->hash,
        ]);
        
        $this->hide_button = true;

        $this->dispatch('redirectRoute', ['route' => $response]);

    }

    public function render()
    {
        return render('components.livewire.subscription-plan-switch');
    }
}
