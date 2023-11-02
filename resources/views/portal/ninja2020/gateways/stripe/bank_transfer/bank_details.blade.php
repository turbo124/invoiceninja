
<div class="mt-4 overflow-hidden bg-white shadow sm:rounded-lg">
    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
        <h3 class="text-lg font-medium leading-6 text-gray-900">
            {{ ctrans('texts.bank_transfer') }}
        </h3>
    </div>
    <div class="container mx-auto">
        <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-1 sm:gap-4 sm:px-6">

        @if(isset($bank_details['currency']) && $bank_details['currency'] == 'gbp')

            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.sort') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['sort_code'] }}
            </dd>
            
            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.account_number') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['account_number'] }}
            </dd>

            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.account_name') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['account_holder_name'] }}
            </dd>

            
            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.reference') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['reference'] }}
            </dd>


            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.balance_due') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['amount'] }}
            </dd>

            <dt class="text-sm font-medium leading-5 text-gray-500">
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ ctrans('texts.stripe_direct_debit_details') }}
            </dd>
            
            @elseif(isset($bank_details['currency']) && $bank_details['currency'] == 'mxn')
            
            <dt class="text-sm font-medium leading-5 text-gray-500">
                Clabe
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['sort_code'] }}
            </dd>

            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.account_number') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['account_number'] }}
            </dd>

            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.account_name') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['account_holder_name'] }}
            </dd>


            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.reference') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['reference'] }}
            </dd>


            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.balance_due') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['amount'] }}
            </dd>

            <dt class="text-sm font-medium leading-5 text-gray-500">
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ ctrans('texts.stripe_direct_debit_details') }}
            </dd>
            
            @elseif(isset($bank_details['currency']) && $bank_details['currency'] == 'jpy')
            
            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.account_number') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['account_number'] }}
            </dd>

            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.account_name') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['account_holder_name'] }}
            </dd>

            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.account_type') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['account_type'] }}
            </dd>

            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.bank_name') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['bank_name'] }}
            </dd>

            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.bank_code') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['bank_code'] }}
            </dd>

            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.branch_name') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['branch_name'] }}
            </dd>

            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.branch_code') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['branch_code'] }}
            </dd>

            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.reference') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['reference'] }}
            </dd>


            <dt class="text-sm font-medium leading-5 text-gray-500">
                {{ ctrans('texts.balance_due') }}
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ $bank_details['amount'] }}
            </dd>

            <dt class="text-sm font-medium leading-5 text-gray-500">
            </dt>
            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                {{ ctrans('texts.stripe_direct_debit_details') }}
            </dd>

        @elseif(isset($bank_details['currency']) && $bank_details['currency'] == 'eur')

            <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.account_name') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['account_holder_name'] }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.account_number') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['account_number'] }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.bic') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['sort_code'] }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.reference') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['reference'] }}
                </dd>


                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.balance_due') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['amount'] }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ ctrans('texts.stripe_direct_debit_details') }}
                </dd>

        @elseif(isset($bank_details['aba_details']) || isset($bank_details['swift_details']))

            <div class="grid grid-cols-2 gap-4">
            @isset($bank_details['aba_details'])
            <div class="whitespace-nowrap">
            <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.bank_name') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['aba_details']['bank_name'] }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.account_number') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['aba_details']['account_number'] }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.routing_number') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['aba_details']['routing_number'] }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.reference') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['aba_details']['reference'] }}
                </dd>


                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.balance_due') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['aba_details']['amount'] }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ ctrans('texts.stripe_direct_debit_details') }}
                </dd>
            </div>
            @endisset

            @isset($bank_details['swift_details'])
            <div class="whitespace-nowrap">
            <dt class="text-sm font-medium leading-5 text-gray-500 whitespace-nowrap">
                    {{ ctrans('texts.bank_name') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2 whitespace-nowrap">
                    {{ $bank_details['swift_details']['bank_name'] }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.account_number') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['swift_details']['account_number'] }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.swift_code') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['swift_details']['swift_code'] }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.reference') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['swift_details']['reference'] }}
                </dd>


                <dt class="text-sm font-medium leading-5 text-gray-500">
                    {{ ctrans('texts.balance_due') }}
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ $bank_details['swift_details']['amount'] }}
                </dd>

                <dt class="text-sm font-medium leading-5 text-gray-500">
                </dt>
                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                    {{ ctrans('texts.stripe_direct_debit_details') }}
                </dd>
            </div>
            @endisset
            </div>
            @endif
                
            </div>
        </div>
</div>