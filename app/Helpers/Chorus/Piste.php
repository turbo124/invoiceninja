<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Chorus;

use Http;

/**
 * Class Piste.
 */
class Piste
{
    private string $oauth_sandbox_url = 'https://sandbox-oauth.piste.gouv.fr/api/oauth/token';
    private string $oauth_production_url = 'https://oauth.piste.gouv.fr/api/oauth/token';
    private string $sandbox_url = 'https://sandbox-api.piste.gouv.fr';
    private string $production_url = 'https://api.piste.gouv.fr';

    private bool $test_mode = false;

    public function __construct(private string $username, private string $password)
    {
    }

    public function setMode($testmode = true): self
    {
        $this->test_mode = $testmode;

        return $this;
    }

    private function oauthHeaders(): array
    {
        return [
            'grant_type' => 'client_credentials',
            'client_id' => config('services.chorus.client_id'),
            'client_secret' => config('services.chorus.secret'),
            'scope' => 'openid profile',
            // 'scope' => 'openid profile searchInvoiceAProcessByValidator processInvoiceAValidate telechargerGroupeFacture deposerPdfFacture searchRequestPayment searchInvoiceRecipient completerFacture consulterFactureParRecipiendaire searchInvoiceByValidator ProcessAReceivedInvoice consulterFactureParValideur consultInvoiceBySupplier searchInvoiceBySupplier recyclerFacture submitInvoice searchInvoiceProcessByRecipient searchInvoiceProcessBySupplier ProcessRejectedInvoices consulterHistoriqueFacture correctValidatorInvoice deposerFluxFacture recupererStatutsFactureVisiblesParValideur'
        ];
    }

    private function oauthUrl(): string
    {
        return $this->test_mode ? $this->oauth_sandbox_url : $this->oauth_production_url;
    }

    private function apiUrl(): string
    {
        return $this->test_mode ? $this->sandbox_url : $this->production_url;
    }

    public function getOauthAccessToken(): ?string
    {
        $response = Http::asForm()->post($this->oauthUrl(), $this->oauthHeaders());

        if($response->successful()) {
            return $response->json()['access_token'];
        }

        return null;
    }

    public function execute(string $uri, string $method = 'post', array $data = [])
    {
        $access_token = $this->getOauthAccessToken();

        nlog($access_token);
        nlog($this->username);
        nlog($this->password);
        nlog(base64_encode($this->username . ':' . $this->password));

        $r = Http::withHeaders([
            'Authorization' => 'Bearer ' . $access_token,
            'cpro-account' => base64_encode(config('services.chorus.tech_username') . ':' . config('services.chorus.tech_password')),
            // 'cpro-account' => base64_encode($this->username . ':' . $this->password),
            'Content-Type' => 'application/json;charset=utf-8',
            'Accept' => 'application/json;charset=utf-8'
        ])
        // ->$method($this->apiUrl() . '/cpro/factures/'. $uri, $data);
        ->$method($this->apiUrl() . $uri, $data);

        // nlog($r);
        nlog($this->apiUrl() . $uri);
        nlog($r->json());
        nlog($r->successful());
        // nlog($r->body());
        // nlog($r->collect());
        return $r;
    }

}
