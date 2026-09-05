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

use App\DataMapper\Analytics\LoginFailure;
use App\DataMapper\Analytics\LoginMeta;
use App\DataMapper\Analytics\LoginSuccess;
use App\Events\User\UserLoginFailed;
use App\Http\Requests\Login\LoginRequest;
use App\Http\Requests\Login\PrecheckLoginRequest;
use App\Libraries\MultiDB;
use App\Models\User;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use Turbo124\Beacon\Facades\LightLogs;

class PasswordLogin
{
    use ThrottlesLogins;

    // Preserve the precheck timing floor so database lookup timing does not expose accounts.
    private const PRECHECK_TIME_FLOOR_MS = 250;

    public function validate(LoginRequest $request): ?JsonResponse
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required_without:passkey_challenge_token|string',
        ]);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->loginErrorResponse('Too many login attempts, you are being throttled', 401);
        }

        return null;
    }

    public function attempt(LoginRequest $request): bool
    {
        return Auth::guard()->attempt($request->only('email', 'password'), $request->boolean('remember'));
    }

    /** Verify local TOTP for password logins; passkey logins bypass this check. */
    public function verifyTwoFactor(User $user, LoginRequest $request): ?JsonResponse
    {
        if (!$user->google_2fa_secret) {
            return null;
        }

        if (!$request->filled('one_time_password')) {
            return $this->loginErrorResponse(ctrans('texts.invalid_one_time_password'), 400);
        }

        $google2fa = new Google2FA();

        if (strlen($request->input('one_time_password')) == 0 || !$google2fa->verifyKey(decrypt($user->google_2fa_secret), $request->input('one_time_password'))) {
            return $this->loginErrorResponse(ctrans('texts.invalid_one_time_password'), 422);
        }

        return null;
    }

    /** Unknown accounts and accounts without TOTP must return the same methods. */
    public function precheck(PrecheckLoginRequest $request): JsonResponse
    {
        $started_at = microtime(true);

        $methods = ['password'];

        $user = MultiDB::hasUser([
            'email' => $request->input('email', ''),
        ]);

        if ($user && $user->google_2fa_secret) {
            $methods[] = 'totp';
        }

        $this->equalizePrecheckResponseTime($started_at);

        return response()->json([
            'methods' => $methods,
            'secret_required' => (bool) config('ninja.api_secret'),
        ], 200);
    }

    public function failedLogin(LoginRequest $request): JsonResponse
    {
        LightLogs::create(new LoginFailure())
            ->increment()
            ->batch();

        $ip = $this->resolveClientIp();

        LightLogs::create(new LoginMeta($request->email, $ip, 'failure'))->batch();

        event(new UserLoginFailed($request->email, $ip));

        $this->incrementLoginAttempts($request);

        return $this->loginErrorResponse(ctrans('texts.invalid_credentials'), 400);
    }

    public function recordSuccess(string $email): void
    {
        LightLogs::create(new LoginSuccess())
            ->increment()
            ->batch();

        LightLogs::create(new LoginMeta($email, $this->resolveClientIp(), 'success'))
            ->batch();
    }

    public function username(): string
    {
        return 'email';
    }

    private function equalizePrecheckResponseTime(float $started_at): void
    {
        $elapsed_ms = (microtime(true) - $started_at) * 1000;
        $remaining_ms = self::PRECHECK_TIME_FLOOR_MS - $elapsed_ms;

        if ($remaining_ms > 0) {
            usleep((int) ($remaining_ms * 1000));
        }
    }

    private function resolveClientIp(): string
    {
        if (request()->hasHeader('Cf-Connecting-Ip')) {
            return (string) request()->header('Cf-Connecting-Ip');
        }

        if (request()->hasHeader('X-Forwarded-For')) {
            return (string) request()->header('X-Forwarded-For');
        }

        return request()->ip() ?: ' ';
    }

    private function loginErrorResponse(string $message, int $status): JsonResponse
    {
        return response()
            ->json(['message' => $message], $status)
            ->header('X-App-Version', config('ninja.app_version'))
            ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }
}
