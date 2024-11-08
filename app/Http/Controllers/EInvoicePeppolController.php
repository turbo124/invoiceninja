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
     * 
     * [
     *       "id" => 290868,
     *       "party_name" => "Untitled Company",
     *       "line1" => "Address 1",
     *       "line2" => "Address 2",
     *       "zip" => "Postal Code",
     *       "city" => "City",
     *       "county" => "State",
     *       "country" => "DE",
     *       "tenant_id" => "EbRYYRWO7oUJE3G3jVa4Xddf6gHGI6kD",
     *       "public" => true,
     *       "acts_as_sender" => true,
     *       "acts_as_receiver" => true,
     *       "tax_registered" => true,
     *       "peppol_identifiers" => [
     *       [
     *           "superscheme" => "iso6523-actorid-upis",
     *           "scheme" => "DE:VAT",
     *           "identifier" => "DE923356489",
     *           "networks" => [
     *           "peppol",
     *           ],
     *           "corppass_enabled" => false,
     *       ],
     *       ],
     *       "administrations" => [],
     *       "advertisements" => [
     *       "invoice",
     *       ],
     *       "smart_inbox" => "a4p2q0@receive.storecove.com",
     *       "api_keys" => [],
     *       "additional_tax_identifiers" => [
     *       [
     *           "id" => 264566,
     *           "legal_entity_id" => 290868,
     *           "country" => null,
     *           "county" => null,
     *           "identifier" => "ATU73769157",
     *           "superscheme" => "iso6523-actorid-upis",
     *           "scheme" => "AT:VAT",
     *       ],
     *       ],
     *   ]
     * 
     * 
     * @param  ShowEntityRequest $request
     * @return JsonResponse
     */
    public function show(ShowEntityRequest $request): JsonResponse
    {
        $company = auth()->user()->company();

        $response = Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post('/api/einvoice/peppol/legal_entity', data: [
                'legal_entity_id' => $company->legal_entity_id,
                'e_invoicing_token' => $company->account->e_invoicing_token,
            ]);

        return response()->json($response->json(), 200);
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

        $response = Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post('/api/einvoice/peppol/setup', data: [
                ...$request->validated(),
                'classification' => $request->classification ?? $company->settings->classification,
                'vat_number' => $request->vat_number ?? $company->settings->vat_number,
                'id_number' => $request->id_number ?? $company->settings->id_number,
                'e_invoicing_token' => $company->account->e_invoicing_token,
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

        nlog($response->json());

        if ($response->status() === 422) {
            return response()->json($response->json(), 422);
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

        $storecove->addAdditionalTaxIdentifier($company->legal_entity_id, $request->identifier, $scheme);

        $tax_data->regions->EU->subregions->{$request->country}->vat_number = $request->identifier;
        $company->tax_data = $tax_data;
        $company->save();

        return response()->json(['message' => 'ok'], 200);

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

        $response = Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->put('/api/einvoice/peppol/update', data: [
                ...$request->validated(),
                'legal_entity_id' => $company->legal_entity_id,
                'e_invoicing_token' => $company->account->e_invoicing_token,
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

        $response = Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post('/api/einvoice/peppol/disconnect', data: [
                'company_key' => $company->company_key,
                'legal_entity_id' => $company->legal_entity_id,
                'e_invoicing_token' => $company->account->e_invoicing_token,
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
