@extends('layouts.ninja')
@section('meta_title', ctrans('texts.new_bank_account'))

@push('head')

<link href="https://unpkg.com/nordigen-bank-ui@1.5.2/package/src/selector.min.css" rel="stylesheet" />

@endpush

@section('body')

<div id="institution-content-wrapper"></div>

@endsection

@push('footer')

<script type='text/javascript' src='https://unpkg.com/nordigen-bank-ui@1.5.2/package/src/selector.min.js'></script>

<script>
    // Pass your redirect link after user has been authorized in institution
    const config = {
        // Redirect URL that is being used when modal is being closed.
        redirectUrl: "{{ $redirectUrl }}" || new URL("", window.location.origin).href,
        // Text that will be displayed on the left side under the logo. Text is limited to 100 characters, and rest will be truncated. @turbo124 replace with a translated version like ctrans()
        text: "{{ ($account ?? false) && !$account->isPaid() ? 'Invoice Ninja' : (isset($company) && !is_null($company) ? $company->name : 'Invoice Ninja') }} {{ ctrans('texts.nordigen_handler_subtitle', [], $lang ?? 'en') }}",
        // Logo URL that will be shown below the modal form.
        logoUrl: "{{ ($account ?? false) && !$account->isPaid() ? asset('images/invoiceninja-black-logo-2.png') : (isset($company) && !is_null($company) ? $company->present()->logo() : asset('images/invoiceninja-black-logo-2.png')) }}",
        // Will display country list with corresponding institutions. When `countryFilter` is set to `false`, only list of institutions will be shown.
        countryFilter: true,
        // style configs
        styles: {
            // Primary
            // Link to google font
            fontFamily: new URL("assets/fonts/Roboto-Regular.ttf", window.location.origin).href,
            fontSize: '15',
            backgroundColor: '#F2F2F2',
            textColor: '#222',
            headingColor: '#222',
            linkColor: '#8d9090',
            // Modal
            modalTextColor: '#1B2021',
            modalBackgroundColor: '#fff',
            // Button
            buttonColor: '#3A53EE',
            buttonTextColor: '#fff'
        }
    };

    const failedReason = "{{ $failed_reason ?? '' }}".trim(),
        institutions = @json($institutions ?? []);

    new institutionSelector(institutions, 'institution-modal-content', config);

    if (!failedReason) {
        const observer = new MutationObserver((event) => {
            const institutionButtons = document.querySelectorAll('.ob-list-institution > a');

            Array.from(institutionButtons).forEach((button) => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();

                    const clone = button.parentElement.cloneNode(true),
                        container = document.querySelector('.institution-container'),
                        institutionId = button.getAttribute('data-institution'),
                        institution = institutions.find(i => i.id == institutionId),
                        max_history = parseInt(institution.transaction_total_days),
                        url = new URL(window.location.href);

                    container.innerHTML = '';
                    _changeHeading('Select your transaction history');

                    clone.classList.replace('ob-list-institution', 'ob-history-option');
                    url.searchParams.set('institution_id', institutionId);

                    for (let i = 30; i <= max_history; i += 30) {
                        const option = clone.cloneNode(true);

                        url.searchParams.set('tx_days', i);

                        option.querySelector('span').innerText = `${i} days`;
                        option.querySelector('a').href = url.href;
                        container.append(option);
                    }

                    if (max_history % 30 !== 0) {
                        url.searchParams.set('tx_days', max_history);

                        clone.querySelector('span').innerText = `${max_history} days`;
                        clone.querySelector('a').href = url.href;
                        container.append(clone);
                    }
                });
            });
        });

        observer.observe(document.querySelector('.institution-container'), {
            childList: true,
        });
    } else {
        document.getElementsByClassName("institution-search-container")[0].remove();
        document.getElementsByClassName("institution-container")[0].remove();

        const heading = document.querySelectorAll('h2')[0];
        const wrapper = document.getElementById("institution-modal-content");
        const contents = document.createElement("div");
        contents.id = "failed-container";
        contents.className = "mt-2";
        contents.style["font-size"] = "80%";
        contents.style["opacity"] = "80%";

        let restartFlow = false; // return, restart, refresh
        heading.innerHTML = "{{ ctrans('texts.nordigen_handler_error_heading_unknown', [], $lang ?? 'en') }}";
        contents.innerHTML = "{{ ctrans('texts.nordigen_handler_error_contents_unknown', [], $lang ?? 'en') }} " + failedReason;

        switch (failedReason) {
            // Connect Screen Errors
            case "token-invalid":
                heading.innerHTML = "{{ ctrans('texts.nordigen_handler_error_heading_token_invalid', [], $lang ?? 'en') }}";
                contents.innerHTML = "{{ ctrans('texts.nordigen_handler_error_contents_token_invalid', [], $lang ?? 'en') }}";
                break;
            case "account-config-invalid":
                heading.innerHTML = "{{ ctrans('texts.nordigen_handler_error_heading_account_config_invalid', [], $lang ?? 'en') }}";
                contents.innerHTML = "{{ ctrans('texts.nordigen_handler_error_contents_account_config_invalid', [], $lang ?? 'en') }}";
                break;
            case "not-available":
                heading.innerHTML = "{{ ctrans('texts.nordigen_handler_error_heading_not_available', [], $lang ?? 'en') }}";
                contents.innerHTML = "{{ ctrans('texts.nordigen_handler_error_contents_not_available', [], $lang ?? 'en') }}";
                break;
            case "institution-invalid":
                restartFlow = true;
                heading.innerHTML = "{{ ctrans('texts.nordigen_handler_error_heading_institution_invalid', [], $lang ?? 'en') }}";
                contents.innerHTML = "{{ ctrans('texts.nordigen_handler_error_contents_institution_invalid', [], $lang ?? 'en') }}";
                break;
            // Confirm Screen Errors
            case "ref-invalid":
                heading.innerHTML = "{{ ctrans('texts.nordigen_handler_error_heading_ref_invalid', [], $lang ?? 'en') }}";
                contents.innerHTML = "{{ ctrans('texts.nordigen_handler_error_contents_ref_invalid', [], $lang ?? 'en') }}";
                break;
            case "requisition-not-found":
                heading.innerHTML = "{{ ctrans('texts.nordigen_handler_error_heading_not_found', [], $lang ?? 'en') }}";
                contents.innerHTML = "{{ ctrans('texts.nordigen_handler_error_contents_not_found', [], $lang ?? 'en') }}";
                break;
            case "requisition-invalid-status":
                heading.innerHTML = "{{ ctrans('texts.nordigen_handler_error_heading_requisition_invalid_status', [], $lang ?? 'en') }}";
                contents.innerHTML = "{{ ctrans('texts.nordigen_handler_error_contents_requisition_invalid_status', [], $lang ?? 'en') }}";
                break;
            case "requisition-no-accounts":
                heading.innerHTML = "{{ ctrans('texts.nordigen_handler_error_heading_requisition_no_accounts', [], $lang ?? 'en') }}";
                contents.innerHTML = "{{ ctrans('texts.nordigen_handler_error_contents_requisition_no_accounts', [], $lang ?? 'en') }}";
                break;
            case "unknown":
                break;
            default:
                console.warn('Invalid or missing failed_reason code: ' + failedReason);
                break;
        }
        wrapper.appendChild(contents);

        const restartUrl = new URL(window.location.pathname, window.location.origin); // no searchParams
        const returnButton = document.createElement('div');
        returnButton.className = "mt-4";
        returnButton.innerHTML = `<a class="button button-primary bg-blue-600 my-4" href="${restartFlow ? restartUrl.href : config.redirectUrl}">${restartFlow ? "{{ ctrans('texts.nordigen_handler_restart', [], $lang ?? 'en') }}" : "{{ ctrans('texts.nordigen_handler_return', [], $lang ?? 'en') }}"}</a>`
        wrapper.appendChild(returnButton);
    }
</script>

@endpush
