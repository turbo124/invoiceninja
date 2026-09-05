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
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Microsoft\Graph\Model;

class MicrosoftLogin
{
    public function __construct(private LoginContext $context)
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

        return Socialite::driver('microsoft')
            ->with(['response_type' => 'code', 'redirect_uri' => config('ninja.app_url') . '/auth/microsoft'])
            ->scopes(['email', 'Mail.Send', 'offline_access', 'profile', 'User.Read openid'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $socialite_user = Socialite::driver('microsoft')->user();

            $oauth_user_token = $socialite_user->accessTokenResponseBody['access_token'];

            $oauth_expiry = now()->addSeconds($socialite_user->accessTokenResponseBody['expires_in']) ?: now()->addSeconds(300);

            if ($user = OAuth::handleAuth($socialite_user, 'microsoft')) {
                nlog('found user and updating their user record');
                $name = OAuth::splitName($socialite_user->getName());

                $update_user = [
                    'first_name' => $name[0],
                    'last_name' => $name[1],
                    'email' => $socialite_user->getEmail(),
                    'oauth_user_id' => $socialite_user->getId(),
                    'oauth_provider_id' => 'microsoft',
                    'oauth_user_token_expiry' => $oauth_expiry,
                ];

                $user->update($update_user);
                $user->oauth_user_refresh_token = $socialite_user->accessTokenResponseBody['refresh_token'];
                $user->oauth_user_token = $oauth_user_token;
                $user->save();

            } else {
                nlog('user not found for oauth');
            }
        } catch (\Throwable $e) {
            nlog("Error in handleMicrosoftProviderCallback: " . $e->getMessage());
        }

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
        if ($request->has('accessToken')) {
            $accessToken = $request->input('accessToken');
        } elseif ($request->has('access_token')) {
            $accessToken = $request->input('access_token');
        } else {
            return response()->json(['message' => 'Invalid response from oauth server, no access token in response.'], 400);
        }

        $expectedClientId = config('services.microsoft.client_id');

        if ($expectedClientId) {
            $parts = explode('.', $accessToken);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                $tokenClientId = $payload['appid'] ?? $payload['azp'] ?? null;

                if ($tokenClientId !== $expectedClientId) {
                    return response()->json(['message' => 'Invalid Microsoft token: audience mismatch.'], 403);
                }
            }
        }

        $graph = new \Microsoft\Graph\Graph();
        $graph->setAccessToken($accessToken);

        $user = $graph->createRequest('GET', '/me')
            ->setReturnType(Model\User::class)
            ->execute();

        if ($user) {
            $email = $user->getUserPrincipalName() ?? false;

            $query = [
                'oauth_user_id' => $user->getId(),
                'oauth_provider_id' => 'microsoft',
            ];

            if ($existing_user = MultiDB::hasUser($query)) {
                if (!$existing_user->account) {
                    return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);
                }

                Auth::login($existing_user, false);

                return $existing_user;
            }

            if (MultiDB::hasUser(['email' => $email, 'oauth_provider_id' => null])) {
                return response()->json(['message' => 'User exists, but never authenticated with OAuth, please use your email and password to login.'], 400);
            }

            if (($linked_user = MultiDB::hasUser(['email' => $email])) && $linked_user->oauth_provider_id && $linked_user->oauth_provider_id !== 'microsoft') {
                return response()->json(['message' => 'This email is already linked to '.$linked_user->oauth_provider_id.' sign-in. Please sign in with '.$linked_user->oauth_provider_id.'.'], 400);
            }

            // Signup!
            if ($request->has('create') && $request->input('create') == 'true') {
                $new_account = [
                    'first_name' => $user->getGivenName() ?: '',
                    'last_name' => $user->getSurname() ?: '',
                    'password' => '',
                    'email' => $email,
                    'oauth_user_id' => $user->getId(),
                    'oauth_provider_id' => 'microsoft',
                ];

                MultiDB::setDefaultDatabase();

                return (new CreateAccount($new_account, $request->getClientIp()))->handle();
            }

            return response()->json(['message' => 'User not found. If you believe this is an error, please send an email to contact@invoiceninja.com'], 400);
        }

        return response()->json(['message' => 'Unable to authenticate this user'], 400);
    }
}
