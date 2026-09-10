<div id="payment-method-preferences">
    @if($this->canChooseAutoBilling)
        <fieldset aria-labelledby="auto-billing-label-{{ $this->invoice_id }}" wire:loading.attr="disabled">
            <dl class="sm:grid px-4 py-5 sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt id="auto-billing-label-{{ $this->invoice_id }}" class="text-sm leading-5 font-medium text-gray-500">
                    {{ ctrans('texts.auto_bill_option') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{-- Livewire saves this preference independently of the gateway's payment form. --}}
                    <label class="flex items-center cursor-pointer px-2 gap-1">
                        <input type="radio" class="form-radio cursor-pointer"
                               name="auto_bill_enabled_{{ $this->invoice_id }}" value="1"
                               form="auto-billing-preference-{{ $this->invoice_id }}"
                               wire:change="setAutoBilling(true)"
                               @checked($this->invoice->auto_bill_enabled)>
                        <span class="cursor-pointer">{{ ctrans('texts.yes') }}</span>
                    </label>
                    <label class="flex items-center cursor-pointer px-2 gap-1">
                        <input type="radio" class="form-radio cursor-pointer"
                               name="auto_bill_enabled_{{ $this->invoice_id }}" value="0"
                               form="auto-billing-preference-{{ $this->invoice_id }}"
                               wire:change="setAutoBilling(false)"
                               @checked(! $this->invoice->auto_bill_enabled)>
                        <span class="cursor-pointer">{{ ctrans('texts.no') }}</span>
                    </label>
                </dd>
            </dl>
        </fieldset>
    @endif

    @if($this->token_billing_policy !== null)
        {{-- Preserve the gateway script's token/new-card visibility across Livewire updates. --}}
        <div id="save-card--container" wire:ignore.self>
            @include('portal.ninja2020.gateways.includes.save_payment_method', [
                'token_billing_policy' => $this->token_billing_policy,
                'force_save_card' => $this->invoice?->auto_bill === 'always' || ($this->canChooseAutoBilling && $this->invoice->auto_bill_enabled),
                'prepayment_recurring' => $this->prepayment_recurring,
            ])
        </div>
    @endif
</div>
