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

export async function get3dsToken() {
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

    try {
        // Return the fetch promise to handle it externally
        const response = await fetch(
            document.querySelector('meta[name=store_route]').content,
            {
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
            }
        );

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
        throw error; //
    }
}

export async function process3ds() {
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

            return document.getElementById('server-response').submit();
        }

        const canvas = new cba.Canvas3ds(
            '#widget-3dsecure',
            resource._3ds.token
        );

        canvas.on('chargeAuthSuccess', function (data) {
            document.querySelector('input[name="browser_details"]').value =
                null;

            document.querySelector('input[name="charge"]').value =
                JSON.stringify(data);

            document.getElementById('server-response').submit();
        });

        canvas.on('chargeAuthReject', function (data) {
            document.getElementById(
                'errors'
            ).textContent = `Sorry, your transaction could not be processed...`;
            document.getElementById('errors').hidden = false;
        });

        canvas.load();

        let widget = document.getElementById('widget');

        widget.classList.add('hidden');
    } catch (error) {
        console.error('Error fetching 3DS Token:', error);

        document.getElementById(
            'errors'
        ).textContent = `Sorry, your transaction could not be processed...\n\n${error}`;
        document.getElementById('errors').hidden = false;
    }
}

export function authorize() {
    const publicKey = document.querySelector('meta[name=public_key]');
    const gatewayId = document.querySelector('meta[name=gateway_id]');
    const environment = document.querySelector('meta[name=environment]');

    const widget = new cba.HtmlWidget(
        '#widget',
        publicKey?.content,
        gatewayId?.content
    );

    widget.setEnv(environment.content);
    widget.useAutoResize();
    // widget.interceptSubmitForm('#server-response');
    widget.onFinishInsert('input[name="gateway_response"]', 'payment_source');
    widget.load();

    function handleTrigger() {
        let payNow = document.getElementById('pay-now');

        payNow.disabled = widget.isInvalidForm();
    }

    widget.trigger('tab', handleTrigger);
    widget.trigger('submit_form', handleTrigger);
    widget.trigger('tab', handleTrigger);

    widget.on('submit', async function (data) {
        document.getElementById('errors').hidden = true;
    });

    widget.on('finish', async function (data) {
        document.getElementById('errors').hidden = true;

        process3ds();
    });

    const first = document.querySelector('input[name="payment-type"]');

    if (first) {
        first.click();
    }
}

instant() ? authorize() : wait('#foo').then(authorize);
