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

namespace App\Services\Auth\Login;

use App\Jobs\Account\CreateAccount;
use App\Libraries\MultiDB;
use App\Libraries\OAuth\OAuth;
use App\Libraries\OAuth\Providers\Google;
use App\Models\Account;
use App\Models\User;
use Google_Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;

class GoogleLogin
{
    public function __construct(private Google $google, private LoginContext $context)
    {
    }

    public function login(Request $request)
    {
        return $this->context->completeOauthLogin($this->authenticate($request), $request);
    }

    public function handleRedirect(Request $request)
    {
        if ($error = $this->context->prepareProviderRequest($request)) {
            return $error;
        }

        if ($request->has('code')) {
            return $this->callback($request);
        }

        return Socialite::driver('google')
            ->with(['access_type' => 'offline', 'prompt' => 'consent select_account', 'redirect_uri' => config('ninja.app_url') . '/auth/google'])
            ->scopes(['https://www.googleapis.com/auth/gmail.send', 'email', 'profile', 'openid'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        $socialite_user = Socialite::driver('google')->user();

        $oauth_user_token = '';

        if ($socialite_user->refreshToken) {
            $client = new Google_Client();
            $client->setClientId(config('ninja.auth.google.client_id'));
            $client->setClientSecret(config('ninja.auth.google.client_secret'));
            $client->fetchAccessTokenWithRefreshToken($socialite_user->refreshToken);
            $oauth_user_token = $client->getAccessToken();
        }

        if ($user = OAuth::handleAuth($socialite_user, 'google')) {
            nlog('found user and updating their user record');
            $name = OAuth::splitName($socialite_user->getName());

            $update_user = [
                'first_name' => $name[0],
                'last_name' => $name[1],
                'email' => $socialite_user->getEmail(),
                'oauth_user_id' => $socialite_user->getId(),
                'oauth_provider_id' => 'google',
            ];

            $user->update($update_user);
            $user->oauth_user_token = $oauth_user_token;
            $user->oauth_user_refresh_token = $socialite_user->refreshToken;
            $user->save();

        } else {
            nlog('user not found for oauth');
        }

        Cache::pull("react_redir:" . auth()->user()?->account?->key);

        $redirect_url = config('ninja.react_url') . "/#/settings/user_details/connect";

        return redirect($redirect_url);
    }

    /**
     * Existing users are authenticated here; new accounts are completed by LoginContext.
     *
     * @return User|Account|JsonResponse
     */
    public function authenticate(Request $request)
    {
        $user = false;

        $google = $this->google;

        if ($request->has('id_token')) {
            $user = $google->getTokenResponse($request->input('id_token'));
        } elseif ($request->has('access_token')) {
            $user = $google->harvestUser($request->input('access_token'));
        } else {
            return response()->json(['message' => 'Illegal request'], 403);
        }

        if (is_array($user)) {
            $query = [
                'oauth_user_id' => $google->harvestSubField($user),
                'oauth_provider_id' => 'google',
            ];

            if ($existing_user = MultiDB::hasUser($query)) {
                if (!$existing_user->account) {
                    return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);
                }

                Auth::login($existing_user, false);

                return $existing_user;
            }

            if (MultiDB::hasUser(['email' => $google->harvestEmail($user), 'oauth_provider_id' => null])) {
                return response()->json(['message' => 'Please use your email and password to login.'], 400);
            }

            if (($linked_user = MultiDB::hasUser(['email' => $google->harvestEmail($user)])) && $linked_user->oauth_provider_id && $linked_user->oauth_provider_id !== 'google') {
                return response()->json(['message' => 'This email is already linked to '.$linked_user->oauth_provider_id.' sign-in. Please sign in with '.$linked_user->oauth_provider_id.'.'], 400);
            }

        }

        if ($user) {
            //check the user doesn't already exist in some form
            if ($existing_login_user = MultiDB::hasUser(['email' => $google->harvestEmail($user), 'oauth_provider_id' => 'google'])) {
                if (!$existing_login_user->account) {
                    return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);
                }

                Auth::login($existing_login_user, false);

                $existing_login_user->update([
                    'oauth_user_id' => $google->harvestSubField($user),
                    'oauth_provider_id' => 'google',
                ]);

                return $existing_login_user;
            }

            if ($request->has('create') && $request->input('create') == 'true') {
                //user not found anywhere - lets sign them up.
                $name = OAuth::splitName($google->harvestName($user));

                $new_account = [
                    'first_name' => $name[0],
                    'last_name' => $name[1],
                    'password' => '',
                    'email' => $google->harvestEmail($user),
                    'oauth_user_id' => $google->harvestSubField($user),
                    'oauth_provider_id' => 'google',
                ];

                MultiDB::setDefaultDatabase();

                return (new CreateAccount($new_account, $request->getClientIp()))->handle();
            }

            return response()->json(['message' => 'User not found. If you believe this is an error, please send an email to contact@invoiceninja.com'], 400);
        }

        return response()
            ->json(['message' => ctrans('texts.invalid_credentials')], 401)
            ->header('X-App-Version', config('ninja.app_version'))
            ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }
}
