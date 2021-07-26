@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title'
=> ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="paytrace-client-key" content="{{ $client_key }}">
    <meta name="ctrans-cvv" content="{{ ctrans('texts.cvv') }}">
    <meta name="ctrans-card_number" content="{{ ctrans('texts.card_number') }}">
    <meta name="ctrans-expires" content="{{ ctrans('texts.expires') }}">
@endsection

@section('gateway_content')
    <form action="{{ route('client.payment_methods.store', ['method' => App\Models\GatewayType::CREDIT_CARD]) }}"
        method="post" id="server_response">
        @csrf
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="txt" id=HPF_Token name= HPF_Token hidden>
        <input type="txt" id=enc_key name= enc_key hidden>
        <input type="text" name="token" hidden>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element-single')
        <div class="w-screen items-center" id="paytrace--credit-card-container">
            <div id="pt_hpf_form"></div>
        </div>
    @endcomponent

    @component('portal.ninja2020.gateways.includes.pay_now')
        {{ ctrans('texts.add_payment_method') }}
    @endcomponent
@endsection

@section('gateway_footer')
    <script src='https://protect.paytrace.com/js/protect.min.js'></script>

    <script>
        class PayTraceCreditCard {
            constructor() {
                this.clientKey = document.querySelector('meta[name=paytrace-client-key]')?.content;
            }

            get creditCardStyles() {
                return {
                    font_color: '#111827',
                    border_color: 'rgba(210,214,220,1)',
                    label_color: '#111827',
                    label_size: '12pt',
                    background_color: 'white',
                    border_style: 'solid',
                    font_size: '15pt',
                    height: '30px',
                    width: '100%'
                }
            }

            get codeStyles() {
                return {
                    font_color: '#111827',
                    border_color: 'rgba(210,214,220,1)',
                    label_color: '#111827',
                    label_size: '12pt',
                    background_color: 'white',
                    border_style: 'solid',
                    font_size: '15pt',
                    height: '30px',
                    width: '300px',
                }
            }

            get expStyles() {
                return {
                    font_color: '#111827',
                    border_color: 'rgba(210,214,220,1)',
                    label_color: '#111827',
                    label_size: '12pt',
                    'background_color': 'white',
                    'border_style': 'solid',
                    'font_size': '15pt',
                    'height': '30px',
                    'width': '85px',
                    'type': 'dropdown'
                }
            }

            updatePayTraceLabels() {
                PTPayment.getControl("securityCode").label.text(
                    document.querySelector('meta[name=ctrans-cvv]').content,
                );

                PTPayment.getControl("creditCard").label.text(
                    document.querySelector('meta[name=ctrans-card_number]').content,
                );

                PTPayment.getControl("expiration").label.text(
                    document.querySelector('meta[name=ctrans-expires]').content,
                );
            }

            setupPayTrace() {
                return PTPayment.setup({
                    styles: {
                        code: this.codeStyles,
                        cc: this.creditCardStyles,
                        exp: this.expStyles,
                    },
                    authorization: {
                        clientKey: this.clientKey
                    },
                });
            }

            handlePaymentWithCreditCard(event) {
                event.target.parentElement.disabled = true;
                document.getElementById('errors').hidden = true;

                PTPayment.validate((errors) => {
                    if (errors.length >= 1) {
                        let errorsContainer = document.getElementById('errors');

                        errorsContainer.textContent = errors[0].description;
                        errorsContainer.hidden = false;

                        return event.target.parentElement.disabled = false;
                    }

                    this.ptInstance.process()
                        .then((response) => {
                            document.getElementById('HPF_Token').value = response.message.hpf_token;
                            document.getElementById("enc_key").value = response.message.enc_key;

                            let tokenBillingCheckbox = document.querySelector(
                                'input[name="token-billing-checkbox"]:checked'
                            );

                            if (tokenBillingCheckbox) {
                                document.querySelector('input[name="store_card"]').value =
                                    tokenBillingCheckbox.value;
                            }

                            document.getElementById("server_response").submit();
                        })
                        .catch((error) => {
                            document.getElementById('errors').textContent = JSON.stringify(error);
                            document.getElementById('errors').hidden = false;

                            console.log(error);
                        });
                });
            }

            handlePaymentWithToken(event) {
                event.target.parentElement.disabled = true;

                document.getElementById("server_response").submit();
            }

            handle() {
                this.setupPayTrace().then((instance) => {
                    this.ptInstance = instance;
                    this.updatePayTraceLabels();

                    Array
                        .from(document.getElementsByClassName('toggle-payment-with-token'))
                        .forEach((element) => element.addEventListener('click', (element) => {
                            document.getElementById('paytrace--credit-card-container').classList.add(
                                'hidden');
                            document.getElementById('save-card--container').style.display = 'none';
                            document.querySelector('input[name=token]').value = element.target.dataset
                                .token;
                        }));

                    document
                        .getElementById('toggle-payment-with-credit-card')
                        ?.addEventListener('click', (element) => {
                            document.getElementById('paytrace--credit-card-container').classList.remove(
                                'hidden');
                            document.getElementById('save-card--container').style.display = 'grid';
                            document.querySelector('input[name=token]').value = "";
                        });

                    document
                        .getElementById('pay-now')
                        .addEventListener('click', (e) => {
                            if (document.querySelector('input[name=token]').value === '') {
                                return this.handlePaymentWithCreditCard(e);
                            }

                            return this.handlePaymentWithToken(e);
                        });
                });
            }
        }

        new PayTraceCreditCard().handle();
    </script>
@endsection
