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

namespace App\Services\EDocument\Gateway\Storecove;

use App\Utils\Ninja;
use App\Models\Company;
use Illuminate\Support\Facades\Http;

class StorecoveProxy
{
    public Company $company;

    public function __construct(public Storecove $storecove)
    {
    }
    
    public function setCompany(Company $company): self
    {
        $this->company = $company;
        return $this;
    }

    public function getLegalEntity(int $legal_entity_id): array
    {
        if(Ninja::isHosted()){
            $response = $this->storecove->getLegalEntity($legal_entity_id);

            if(is_array($response))
                return $response;

            return $this->handleResponseError($response);
        }

        $uri = '/api/einvoice/peppol/legal_entity';
        $payload = ['legal_entity_id' => $legal_entity_id];

        return $this->remoteRequest($uri, $payload);
    }

    private function handleResponseError($response): array
    {
                
        $error = [
            'status' => 'error',
            'message' => 'Unknown error occurred',
            'code' => $response->status() ?? 500,
        ];

        if ($response->json()) {
            $body = $response->json();

            $error['message'] = $body['error'] ?? $body['message'] ?? $response->body();

            if (isset($body['errors']) && is_array($body['errors'])) {
                $error['errors'] = $body['errors'];
            }
        }

        if ($response->status() === 401) {
            $error['message'] = 'Authentication failed';
        }

        if ($response->status() === 403) {
            $error['message'] = 'Access forbidden';
        }

        if ($response->status() === 404) {
            $error['message'] = 'Resource not found';
        }

        nlog(['Storecove API Error' => [
            'status' => $response->status(),
            'body' => $response->body(),
            'error' => $error
        ]]);

        return $error;

    }

    private function remoteRequest(string $uri, array $payload =[]): array
    {

        $response = Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders($this->getHeaders())
            ->post($uri, $payload);

        if($response->successful())
            return $response->json();

        return $this->handleResponseError($response);
    }

    private function getHeaders(): array
    {
        
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-EInvoice-Token' => $this->company->account->e_invoicing_token,
            "X-Requested-With" => "XMLHttpRequest",
        ];

    }
}