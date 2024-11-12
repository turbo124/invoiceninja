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

namespace App\Http\Controllers;

use App\Utils\Ninja;
use Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Requests\EInvoice\Peppol\StoreEntityRequest;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Http\Requests\EInvoice\Peppol\DisconnectRequest;
use App\Http\Requests\EInvoice\Peppol\AddTaxIdentifierRequest;
use App\Http\Requests\EInvoice\Peppol\ShowEntityRequest;
use App\Http\Requests\EInvoice\Peppol\UpdateEntityRequest;

class EInvoicePeppolController extends BaseController
{
    /**
     * Returns the legal entity ID
     * 
     * @param  ShowEntityRequest $request
     * @return JsonResponse
     */
    public function show(ShowEntityRequest $request, Storecove $storecove): JsonResponse
    {
        $company = auth()->user()->company();

        $response_array = $storecove->proxy->setCompany($company)->getLegalEntity($company->legal_entity_id);

        return response()->json($response_array, $response_array['code'] ?? 200);
    }

    /**
     * Create a legal entity id, response will be
     * the same as show()
     *
     * @param  StoreEntityRequest $request
     * @param  Storecove $storecove
     * @return Response
     */
    public function setup(StoreEntityRequest $request, Storecove $storecove): Response|JsonResponse
    {
        /**
         * @var \App\Models\Company
         */
        $company = auth()->user()->company();

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (Ninja::isSelfHost()) {
            $headers['X-EInvoice-Token'] = $company->account->e_invoicing_token;
        }

        if (Ninja::isHosted()) {
            $headers['X-EInvoice-Secret'] = config('ninja.hosted_einvoice_secret');
        }

        $response = Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders($headers)
            ->post('/api/einvoice/peppol/setup', data: [
                ...$request->validated(),
                'classification' => $request->classification ?? $company->settings->classification,
                'vat_number' => $request->vat_number ?? $company->settings->vat_number,
                'id_number' => $request->id_number ?? $company->settings->id_number,
            ]);

        if ($response->successful()) {
            $company->legal_entity_id = $response->json('legal_entity_id');

            $tax_data = $company->tax_data;

            $tax_data->acts_as_sender = $request->acts_as_sender;
            $tax_data->acts_as_receiver = $request->acts_as_receiver;

            $settings = $company->settings;

            $settings->name = $request->party_name;
            $settings->country_id = (string) $request->country_id;
            $settings->address1 = $request->line1;
            $settings->address2 = $request->line2;
            $settings->city = $request->city;
            $settings->state = $request->county;
            $settings->postal_code = $request->zip;

            $settings->e_invoice_type = 'PEPPOL';
            $settings->vat_number = $request->vat_number ?? $company->settings->vat_number;
            $settings->id_number = $request->id_number ?? $company->settings->id_number;
            $settings->classification = $request->classification ?? $company->settings->classification;
            $settings->enable_e_invoice = true;

            $company->tax_data = $tax_data;
            $company->settings = $settings;

            $company->save();

            return response()->noContent();
        }

        if ($response->status() === 422) {
            return response()->json($response->json(), 422);
        }

        return response()->noContent(status: 500);
    }

    /**
     * Update legal properties such as acting as sender or receiver.
     * 
     * @param \App\Http\Requests\EInvoice\Peppol\UpdateEntityRequest $request
     * @return JsonResponse|mixed|Response
     */
    public function updateLegalEntity(UpdateEntityRequest $request)
    {
        $company = auth()->user()->company();

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (Ninja::isSelfHost()) {
            $headers['X-EInvoice-Token'] = $company->account->e_invoicing_token;
        }

        if (Ninja::isHosted()) {
            $headers['X-EInvoice-Secret'] = config('ninja.hosted_einvoice_secret');
        }

        $response = Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders($headers)
            ->put('/api/einvoice/peppol/update', data: [
                ...$request->validated(),
                'legal_entity_id' => $company->legal_entity_id,
            ]);

        if ($response->successful()) {
            $tax_data = $company->tax_data;

            $tax_data->acts_as_sender = $request->acts_as_sender;
            $tax_data->acts_as_receiver = $request->acts_as_receiver;

            $company->tax_data = $tax_data;

            $company->save();

            return response()->noContent();
        }

        return response()->json(['message' => 'Error updating identifier'], 422);
    }

    /**
     * Removed the legal identity from the Peppol network
     *
     * @param  DisconnectRequest $request
     * @return \Illuminate\Http\Response
     */
    public function disconnect(DisconnectRequest $request): \Illuminate\Http\Response
    {
        /**
         * @var \App\Models\Company $company
         */
        $company = auth()->user()->company();

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (Ninja::isSelfHost()) {
            $headers['X-EInvoice-Token'] = $company->account->e_invoicing_token;
        }

        if (Ninja::isHosted()) {
            $headers['X-EInvoice-Secret'] = config('ninja.hosted_einvoice_secret');
        }

        nlog($headers);

        $response = Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders($headers)
            ->post('/api/einvoice/peppol/disconnect', data: [
                'company_key' => $company->company_key,
                'legal_entity_id' => $company->legal_entity_id,
            ]);

        if ($response->successful()) {
            $company->legal_entity_id = null;
            $company->tax_data = $this->unsetVatNumbers($company->tax_data);

            $settings = $company->settings;
            $settings->e_invoice_type = 'EN16931';

            $company->settings = $settings;

            $company->save();

            return response()->noContent();
        }

        return response()->noContent(status: 500);
    }

    /**
     * Add an additional tax identifier to
     * an existing legal entity id
     * 
     * Response will be the same as show()
     *
     * @param  AddTaxIdentifierRequest $request
     * @param  Storecove $storecove
     * @return \Illuminate\Http\JsonResponse
     */
    public function addAdditionalTaxIdentifier(AddTaxIdentifierRequest $request, Storecove $storecove): \Illuminate\Http\JsonResponse
    {
        
        $company = auth()->user()->company();
        $tax_data = $company->tax_data;

        $additional_vat = $tax_data->regions->EU->subregions->{$request->country}->vat_number ?? null;

        if (!is_null($additional_vat) && !empty($additional_vat)) {
            return response()->json(['message' => 'Identifier already exists for this region.'], 400);
        }

        $scheme = $storecove->router->resolveRouting($request->country, $company->settings->classification);

        $storecove->addAdditionalTaxIdentifier($company->legal_entity_id, $request->vat_number, $scheme);

        $tax_data->regions->EU->subregions->{$request->country}->vat_number = $request->vat_number;
        $company->tax_data = $tax_data;
        $company->save();

        return response()->json(['message' => 'ok'], 200);

    }



    private function unsetVatNumbers(mixed $taxData): mixed
    {
        if (isset($taxData->regions->EU->subregions)) {
            foreach ($taxData->regions->EU->subregions as $country => $data) {
                if (isset($data->vat_number)) {
                    $newData = new \stdClass();
                    if (is_object($data)) {
                        $dataArray = get_object_vars($data);
                        foreach ($dataArray as $key => $value) {
                            if ($key !== 'vat_number') {
                                $newData->$key = $value;
                            }
                        }
                    }
                    $taxData->regions->EU->subregions->$country = $newData;
                }
            }
        }

        return $taxData;
    }
}
