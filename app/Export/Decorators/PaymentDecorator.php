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

namespace App\Export\Decorators;

use App\Models\Payment;

class PaymentDecorator extends BaseDecorator implements DecoratorInterface{

    public function transform(): string
    {
nlog($this->key);
        if (method_exists($this, $this->key)) {
            return $this->{$this->key}();
        }

        if(!$this->entity instanceof Payment){
            $special_aggregate = $this->subLoop();
        }

        if($special_aggregate)
            return $special_aggregate;

        return $this->entity->payments()->first() ? $this->entity->payments()->first()->{$this->key} ?? '' : '';
        
    }

    private function subLoop()
    {
        return match($this->key){
            'amount' => $value = $this->entity->payments()->exists() ? $this->entity->payments()->withoutTrashed()->sum('paymentables.amount') : ctrans('texts.unpaid'),
            'refunded' => $value = $this->entity->payments()->exists() ? $this->entity->payments()->withoutTrashed()->sum('paymentables.refunded') : '',
            'applied' => $value = $this->entity->payments()->withoutTrashed()->sum('paymentables.amount') ?? 0 - $this->entity->payments()->withoutTrashed()->sum('paymentables.refunded') ?? 0,
            'amount' => $value = $this->entity->payments()->withoutTrashed()->first()->amount ?? '',
            'method' => $value = $this->entity->payments()->withoutTrashed()->first() ? $this->entity->payments()->withoutTrashed()->first()->translatedType() : '',
            'currency' => $value = $this->entity->payments()->withoutTrashed()->first() ? $this->entity->payments()->withoutTrashed()->first()->currency->code ?? '' : '',
            'status' => $value = $this->entity->payments()->withoutTrashed()->first() ? $this->entity->payments()->withoutTrashed()->first()->stringStatus($this->entity->payments()->withoutTrashed()->first()->status_id) : '',
            default => $value = false,
        };
        
        return $value;

    }

    private function status_id()
    {
        return $this->entity->stringStatus($this->entity->status_id);
    }

    private function status()
    {
        return $this->status_id();
    }

    private function vendor()
    {
        return $this->vendor_id();
    }

    private function vendor_id()
    {
        return $this->entity->vendor ? $this->entity->vendor->name : '';
    }

    private function project_id()
    {
        return $this->entity->project ? $this->entity->project->name : '';
    }

    private function project()
    {
        return $this->project_id();
    }

    private function currency()
    {
        return $this->entity->currency ? $this->entity->currency->code : '';
    }

    private function currency_id()
    {
        return $this->currency();
    }

    private function exchange_currency()
    {
        return $this->entity->exchange_currency ? $this->entity->exchange_currency->code : '';
    }

    private function exchange_currency_id()
    {
        return $this->exchange_currency();
    }

    private function client()
    {
        return $this->entity->client->present()->name();
    }

    private function client_id()
    {
        return $this->client();
    }

    private function gateway_type_id()
    {
        return $this->entity->gateway_type ? $this->entity->gateway_type->name : 'Unknown Type';
    }

    private function gateway()
    {
        return $this->gateway_type_id();
    }

    private function assigned_user()
    {
        return $this->entity->assigned_user ? $this->entity->assigned_user->present()->name() : '';
    }

    private function assigned_user_id()
    {
        return $this->assigned_user();
    }

    private function user()
    {
        return $this->entity->user ? $this->entity->user->present()->name() : '';
    }

    private function user_id()
    {
        return $this->user();
    }

    private function type_id()
    {
        return $this->entity->translatedType();
    }

    private function type()
    {
        return $this->type_id();
    }
   
    private function method()
    {
        return $this->type_id();
    }


}