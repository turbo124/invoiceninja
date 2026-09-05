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

use App\Http\Requests\Login\LoginRequest;
use App\Libraries\MultiDB;
use App\Services\Auth\Passkeys\PasskeyService;
use Illuminate\Support\Facades\Auth;

class PasskeyLogin
{
    /**
     * Null means this is not a passkey attempt; false means verification failed.
     * A failed passkey attempt must not fall back to password authentication.
     */
    public function attempt(LoginRequest $request): ?bool
    {
        if ($request->filled('password') || !$request->filled('passkey_challenge_token')) {
            return null;
        }

        $passkeyPayload = $request->input('passkey_authentication');

        if (!is_array($passkeyPayload)) {
            return null;
        }

        $user = MultiDB::hasUser(['email' => $request->input('email'), 'is_deleted' => 0, 'deleted_at' => null]);

        if (!$user) {
            return false;
        }

        try {
            $passkeyService = app(PasskeyService::class);
            $passkeyUser = $passkeyService->authenticate($user, (string) $request->input('passkey_challenge_token'), $passkeyPayload);
            Auth::login($passkeyUser, false);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
