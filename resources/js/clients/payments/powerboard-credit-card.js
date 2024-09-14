/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { instant, wait } from '../wait';

function setup() {
    const publicKey = document.querySelector('meta[name=public_key]');
    const gatewayId = document.querySelector('meta[name=gateway_id]');
    const env = document.querySelector('meta[name=environment]');

    const widget = new cba.HtmlWidget(
        '#widget',
        publicKey?.content,
        gatewayId?.content
    );

    widget.setEnv(env?.content);
    widget.useAutoResize();
    widget.interceptSubmitForm('#stepone');
    widget.onFinishInsert(
        '#server-response input[name="gateway_response"]',
        'payment_source'
    );

    widget.setFormFields(['card_name*']);
    widget.reload();

    let payNow = document.getElementById('pay-now');

    payNow.disabled = false;
    payNow.querySelector('svg').classList.add('hidden');
    payNow.querySelector('span').classList.remove('hidden');

    document.querySelector(
        '#server-response input[name="gateway_response"]'
    ).value = '';

    return widget;
}

function reload() {
    document.querySelector('#widget')?.replaceChildren();
    document.querySelector('#widget')?.classList.remove('hidden');

    document.querySelector('#widget-3dsecure')?.replaceChildren();
}

function pay() {
    reload();

    const widget = setup();

    widget.on('finish', () => {
        document.getElementById('errors').hidden = true;

        process3ds();
    });

    widget.on('submit', function (data) {
        document.getElementById('errors').hidden = true;
    });

    let payNow = document.getElementById('pay-now');

    payNow.addEventListener('click', () => {
        const div = document.getElementById('widget');

        widget.getValidationState();

        if (!widget.isValidForm() && div.offsetParent !== null) {
            payNow.disabled = false;
            payNow.querySelector('svg').classList.add('hidden');
            payNow.querySelector('span').classList.remove('hidden');

            return;
        }

        payNow.disabled = true;
        payNow.querySelector('svg').classList.remove('hidden');
        payNow.querySelector('span').classList.add('hidden');

        let storeCard = document.querySelector(
            'input[name=token-billing-checkbox]:checked'
        );

        if (storeCard) {
            document.getElementById('store_card').value = storeCard.value;
        }

        if (div.offsetParent !== null)
            document.getElementById('stepone_submit').click();
        else document.getElementById('server-response').submit();
    });

    const first = document.querySelector('input[name="payment-type"]');

    if (first) {
        first.click();
    }

    document
        .getElementById('toggle-payment-with-credit-card')
        .addEventListener('click', (element) => {
            let widget = document.getElementById('widget');

            widget.classList.remove('hidden');

            document.getElementById('save-card--container').style.display =
                'grid';
            document.querySelector('input[name=token]').value = '';

            document
                .querySelector('#powerboard-payment-container')
                ?.classList.remove('hidden');
        });

    Array.from(
        document.getElementsByClassName('toggle-payment-with-token')
    ).forEach((element) =>
        element.addEventListener('click', (element) => {
            document.getElementById('widget').classList.add('hidden');
            document.getElementById('save-card--container').style.display =
                'none';
            document.querySelector('input[name=token]').value =
                element.target.dataset.token;

            document
                .querySelector('#powerboard-payment-container')
                ?.classList.add('hidden');
        })
    );
}

async function process3ds() {
    try {
        const resource = await get3dsToken();

        if (resource.status === 'not_authenticated') {
            throw new Error(
                'There was an issue authenticating this payment method.'
            );
        }

        if (resource.status === 'authentication_not_supported') {
            document.querySelector('input[name="browser_details"]').value =
                null;

            document.querySelector('input[name="charge"]').value =
                JSON.stringify(resource);

            let storeCard = document.querySelector(
                'input[name=token-billing-checkbox]:checked'
            );

            if (storeCard) {
                document.getElementById('store_card').value = storeCard.value;
            }

            return document.getElementById('server-response').submit();
        }

        const canvas = new cba.Canvas3ds(
            '#widget-3dsecure',
            resource._3ds.token
        );
        canvas.load();

        let widget = document.getElementById('widget');
        widget.classList.add('hidden');

        canvas.on('chargeAuthSuccess', function (data) {
            document.querySelector('input[name="browser_details"]').value =
                null;

            document.querySelector('input[name="charge"]').value =
                JSON.stringify(data);

            let storeCard = document.querySelector(
                'input[name=token-billing-checkbox]:checked'
            );

            if (storeCard) {
                document.getElementById('store_card').value = storeCard.value;
            }

            document.getElementById('server-response').submit();
        });

        canvas.on('chargeAuthReject', function (data) {
            document.getElementById(
                'errors'
            ).textContent = `Sorry, your transaction could not be processed...`;
            document.getElementById('errors').hidden = false;

            pay();
        });

        canvas.load();
    } catch (error) {
        console.error('Error fetching 3DS Token:', error);

        document.getElementById(
            'errors'
        ).textContent = `Sorry, your transaction could not be processed...\n\n${error}`;
        document.getElementById('errors').hidden = false;

        pay();
    }
}

async function get3dsToken() {
    const browserDetails = {
        name: navigator.userAgent.substring(0, 100), // The full user agent string, which contains the browser name and version
        java_enabled: navigator.javaEnabled() ? 'true' : 'false', // Indicates if Java is enabled in the browser
        language: navigator.language || navigator.userLanguage, // The browser language
        screen_height: window.screen.height.toString(), // Screen height in pixels
        screen_width: window.screen.width.toString(), // Screen width in pixels
        time_zone: (new Date().getTimezoneOffset() * -1).toString(), // Timezone offset in minutes (negative for behind UTC)
        color_depth: window.screen.colorDepth.toString(), // Color depth in bits per pixel
    };

    document.querySelector('input[name="browser_details"]').value =
        JSON.stringify(browserDetails);

    const formData = JSON.stringify(
        Object.fromEntries(
            new FormData(document.getElementById('server-response'))
        )
    );

    const paymentsRoute = document.querySelector('meta[name=payments_route]');

    try {
        // Return the fetch promise to handle it externally
        const response = await fetch(paymentsRoute.content, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'X-CSRF-Token': document.querySelector(
                    'meta[name="csrf-token"]'
                ).content,
            },
            body: formData,
        });

        if (!response.ok) {
            return await response.json().then((errorData) => {
                throw new Error(errorData.message ?? 'Unknown error.');
            });

            // const text = await response.text();
            // throw new Error(`Network response was not ok: ${response.statusText}. Response text: ${text}`);
        }

        return await response.json();
    } catch (error) {
        document.getElementById(
            'errors'
        ).textContent = `Sorry, your transaction could not be processed...\n\n${error.message}`;
        document.getElementById('errors').hidden = false;

        console.error('Fetch error:', error); // Log error for debugging
        pay();
        // throw error; //
    }
}

instant() ? pay() : wait('#powerboard-credit-card-payment').then(() => pay());
