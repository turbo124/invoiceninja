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
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AppleLogin
{
    public function __construct(private LoginContext $context)
    {
    }

    public function login(Request $request)
    {
        if (!$request->has('id_token')) {
            return response()->json(['message' => 'Token is missing for the apple login'], 400)
                ->header('X-App-Version', config('ninja.app_version'))
                ->header('X-Api-Version', config('ninja.minimum_client_version'));
        }

        return $this->context->completeOauthLogin($this->authenticate($request), $request, dispatchEvent: false);
    }

    public function authenticate(Request $request): User|JsonResponse
    {
        $user = Socialite::driver('apple')->userFromToken($request->input('id_token'));

        if ($user) {
            return $this->resolveUser($user, $request);
        }

        return response()
            ->json(['message' => ctrans('texts.invalid_credentials')], 401)
            ->header('X-App-Version', config('ninja.app_version'))
            ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }

    private function resolveUser($user, Request $request): User|JsonResponse
    {
        $query = [
            'oauth_user_id' => $user->id,
            'oauth_provider_id' => 'apple',
        ];

        if ($existing_user = MultiDB::hasUser($query)) {
            if (!$existing_user->account) {
                return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);
            }

            Auth::login($existing_user, false);

            return $existing_user;
        }
        // Link an existing email only when it has no conflicting provider.
        if ($existing_login_user = MultiDB::hasUser(['email' => $user->email])) {
            if (!$existing_login_user->account) {
                return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);
            }

            if ($existing_login_user->oauth_provider_id && $existing_login_user->oauth_provider_id !== 'apple') {
                return response()->json([
                    'message' => 'This email is already linked to '.$existing_login_user->oauth_provider_id.' sign-in. Please sign in with '.$existing_login_user->oauth_provider_id.'.',
                ], 400);
            }

            Auth::login($existing_login_user, false);

            $existing_login_user->update([
                'oauth_user_id' => $user->id,
                'oauth_provider_id' => 'apple',
            ]);

            return $existing_login_user;
        }

        $name = OAuth::splitName($user->name);

        $name[0] = $request->has('first_name') ? $request->input('first_name') : $name[0];
        $name[1] = $request->has('last_name') ? $request->input('last_name') : $name[1];

        if (!$user->email) {
            return response()->json(['message' => 'This signup method is not supported as no email was provided'], 403);
        }

        $new_account = [
            'first_name' => $name[0],
            'last_name' => $name[1],
            'password' => '',
            'email' => $user->email,
            'oauth_user_id' => $user->id,
            'oauth_provider_id' => 'apple',
        ];

        MultiDB::setDefaultDatabase();

        $account = (new CreateAccount($new_account, $request->getClientIp()))->handle();

        $account_user = $account->default_company->owner();
        Auth::login($account_user, false);

        return $account_user;
    }
}
