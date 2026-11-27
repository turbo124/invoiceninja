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
namespace App\Libraries\OAuth\Providers;

use Google_Client;

class Google implements ProviderInterface
{
    public function getTokenResponse($token)
    {
        // Legacy - only check the server ID token
        // $client = new Google_Client();
        // $client->setClientId(config('ninja.auth.google.client_id'));
        // return $client->verifyIdToken($token);

        $payload = (new Google_Client())->verifyIdToken($token);
        
        if (!is_array($payload)) {
            return false;
        }

        $allowed = config('ninja.auth.google.allowed_client_ids', []);
        $aud = $payload['aud'] ?? null;
        $azp = $payload['azp'] ?? $aud;
        if ($aud === null || !in_array($aud, $allowed, true) || !in_array($azp, $allowed, true)) {
            return false;
        }
        return $payload;

    }

    public function harvestEmail($payload)
    {
        return $payload['email'];
    }

    public function harvestSubField($payload)
    {
        return $payload['sub']; // user ID
    }

    public function harvestName($payload)
    {
        return $payload['name'] ?? 'unknown name';
    }

    public function harvestUser($access_token)
    {

        if (!$this->verifyAccessTokenAudience($access_token)) {
            return false;
        }
        
        $client = new Google_Client();
        $client->setClientId(config('ninja.auth.google.client_id'));
        $client->setClientSecret(config('ninja.auth.google.client_secret'));
        $client->setAccessToken($access_token);

        $oauth2 = new \Google_Service_Oauth2($client);

        try {
            $userInfo = $oauth2->userinfo->get();
        } catch (\Exception $e) {
            return false;
        }

        return [
            'email' => $userInfo['email'],
            'sub' => $userInfo['id'],
            'name' => $userInfo['name'],
        ];

    }

    public function verifyAccessTokenAudience($access_token): bool
    {
        $expected = config('ninja.auth.google.client_id');

        try {
            $info = (new \Google_Service_Oauth2(new Google_Client()))
                ->tokeninfo(['access_token' => $access_token]);
        } catch (\Exception $e) {
            return false;
        }
        
        return $info->getAudience() === $expected
            && $info->getIssuedTo() === $expected;
    }
}
