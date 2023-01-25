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

use App\Http\Requests\XeroTenant\XeroAuthRequest;
use App\Libraries\MultiDB;
use App\Models\XeroTenant;
use Illuminate\Http\Request;

class XeroAuthController extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function onboard(XeroAuthRequest $request, string $token = '')
    {

        if (! is_array($request->getTokenContent())) {
            abort(400, 'Invalid token');
        }

        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);

        $provider = new \Calcinai\OAuth2\Client\Provider\Xero([
            'clientId'          => config('services.xero.client_id'),
            'clientSecret'      => config('services.xero.client_secret'),
            'redirectUri'       => config('services.xero.redirect'),
            'state'             => $request->token
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
                'scope' => 'openid email profile accounting.transactions offline_access accounting.contacts',
                'state'             => $request->token
            ]);

            header('Location: ' . $authUrl);
            exit;

        // Check given state against previously stored one to mitigate CSRF attack
        } else {

          
        }

    }

    public function completed(XeroAuthRequest $request)
    {

        if (! is_array($request->getTokenContent())) {
            abort(400, 'Invalid token');
        }

        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);

            $provider = new \Calcinai\OAuth2\Client\Provider\Xero([
                'clientId'          => config('services.xero.client_id'),
                'clientSecret'      => config('services.xero.client_secret'),
                'redirectUri'       => config('services.xero.redirect'),
                'state'             => $request->state
            ]);

             // Try to get an access token (using the authorization code grant)
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $request->code,
                'state'             => $request->token
            ]);

            //If you added the openid/profile scopes you can access the authorizing user's identity.
            $identity = $provider->getResourceOwner($token);

            //Get the tenants that this user is authorized to access
            $tenants = $provider->getTenants($token);

            $user = $request->getContact();
            $user->xero_oauth_user_id = $identity->xero_userid;
            $user->xero_oauth_access_token = $token->getToken();
            $user->xero_oauth_refresh_token = $token->getRefreshToken();
            $user->save();

            foreach($tenants as $tenant)
            {
                $xt = XeroTenant::withTrashed()
                                ->where('account_id', $user->account_id)
                                ->where('tenant_id', $tenant->tenantId )
                                ->firstOrNew();

                $xt->tenant_id = $tenant->tenantId;
                $xt->tenant_name = $tenant->tenantName;
                $xt->tenant_type = $tenant->tenantType;
                $xt->account_id = $user->account_id;
                $xt->user_id = $user->id;
                $xt->save();
            }

            return redirect('/');

    }

    public function webhook(Request $request)
    {
        nlog($request->all());

        // $application->setConfig(['webhook' => ['signing_key' => 'xyz123']]);
        // $webhook = new Webhook($application, $request->getContent());

        $computedSignatureKey = base64_encode(
            hash_hmac($request->getContent(), config('services.xero.signing_key'), true)
        );

        if(!hash_equals($computedSignatureKey, $request->headers->get('x-xero-signature'))){

            return response()->json(null, 401);
        }

        return response()->json(null, 200);
    }

}
