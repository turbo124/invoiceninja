<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Xero\XeroAuthRequest;

class XeroAuthController extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function __invoke(XeroAuthRequest $request)
    {

        $provider = new \Calcinai\OAuth2\Client\Provider\Xero([
            'clientId'          => config('services.xero.client_id'),
            'clientSecret'      => config('services.xero.client_secret'),
            'redirectUri'       => config('services.xero.redirect'),
        ]);

        if (!$request->has('code')) {

            // If we don't have an authorization code then get one
            // Additional scopes may be required depending on your application
            // additional common scopes are:
            // Add/edit contacts: accounting.contacts
            // Add/edit attachments accounting.attachments
            // Refresh tokens for non-interactive re-authorisation: offline_access
            // See all Xero Scopes https://developer.xero.com/documentation/guides/oauth2/scopes/
            $authUrl = $provider->getAuthorizationUrl([
                'scope' => 'openid email profile accounting.transactions offline_access accounting.contacts'
            ]);

            header('Location: ' . $authUrl);
            exit;

        // Check given state against previously stored one to mitigate CSRF attack
        } else {

            // Try to get an access token (using the authorization code grant)
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->code
            ]);

            nlog($token);

            //If you added the openid/profile scopes you can access the authorizing user's identity.
            $identity = $provider->getResourceOwner($token);
            nlog($identity);

            //Get the tenants that this user is authorized to access
            $tenants = $provider->getTenants($token);
            nlog($tenants);
        }


    }
}
