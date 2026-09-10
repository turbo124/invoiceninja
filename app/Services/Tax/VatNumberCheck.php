<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Tax;

use Illuminate\Support\Facades\Http;

class VatNumberCheck
{
    private array $response = [];

    public function __construct(protected ?string $vat_number, protected string $country_code) {}

    /**
     * @throws \RuntimeException when VIES gives no verdict, which the CheckVat job retries
     */
    public function run()
    {
        if (strlen($this->vat_number ?? '') == 0) {
            $this->response = ['valid' => false, 'error' => 'No VAT number provided'];
            return $this;
        } else {
            return $this->checkvat_number();
        }
    }

    private function checkvat_number(): self
    {
        // VIES files Greece as EL, and takes the number without its country prefix
        $country_code = $this->country_code == 'GR' ? 'EL' : $this->country_code;
        $vat_number = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $this->vat_number));
        $vat_number = preg_replace("/^({$this->country_code}|{$country_code})/", '', $vat_number);

        $response = Http::timeout(20)->post('https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number', [
            'countryCode' => $country_code,
            'vatNumber' => $vat_number,
        ]);

        // A number that is not registered comes back as "valid": false. When VIES cannot answer
        // (a member state is down, the service is busy, or it is rate limiting the caller) there
        // is no "valid" at all, and that must not be read as a "no".
        if (!is_bool($response->json('valid'))) {
            throw new \RuntimeException('VIES returned no verdict: '.($response->json('errorWrappers.0.error') ?? 'HTTP '.$response->status()));
        }

        if ($response->json('valid')) {

            $this->response = [
                'valid' => true,
                'name' => $response->json('name'),
                'address' => $response->json('address'),
            ];
        } else {
            $this->response = ['valid' => false];
        }

        return $this;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function isValid(): bool
    {
        return $this->response['valid'];
    }

    public function getName()
    {
        return $this->response['name'] ?? '';
    }

    public function getAddress()
    {
        return $this->response['address'] ?? '';
    }
}
