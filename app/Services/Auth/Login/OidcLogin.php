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

use App\Events\User\UserLoggedIn;
use App\Libraries\MultiDB;
use App\Libraries\OAuth\OAuth;
use App\Models\User;
use App\Utils\Ninja;
use App\Utils\TruthSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class OidcLogin
{
    public function __construct(private LoginContext $context)
    {
    }

    public function handleRedirect(Request $request)
    {
        abort_unless(config('services.oidc.well_known'), 404, 'OIDC provider is not configured');

        if ($error = $this->context->prepareProviderRequest($request)) {
            return $error;
        }

        if ($request->has('code')) {
            return $this->callback($request);
        }

        return Socialite::driver('oidc')
            ->with(['response_type' => 'code', 'redirect_uri' => config('services.oidc.redirect')])
            ->scopes(array_values(array_filter(explode(' ', (string) config('services.oidc.scopes', 'openid profile email')))))
            ->redirect();
    }

    public function callback(Request $request)
    {
        $socialite_user = $this->authenticate();

        if ($socialite_user instanceof JsonResponse) {
            return $socialite_user;
        }

        $user = $this->resolveUser($socialite_user);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        Auth::login($user, false);

        $cu = $this->context->hydrate($user, $request);

        if ($cu->count() == 0) {
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
        }

        if (Ninja::isHosted() && !$cu->first()->is_owner && !$user->account->isEnterprisePaidClient()) { //@phpstan-ignore-line
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);
        }

        $name = OAuth::splitName($socialite_user->getName() ?? '');
        $user->update([
            'first_name' => $user->first_name ?: $name[0],
            'last_name' => $user->last_name ?: $name[1],
        ]);

        $company_token = app(TruthSource::class)->getCompanyToken();

        if (!$company_token) {
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
        }

        event(new UserLoggedIn($user, $company_token->company, Ninja::eventVars($user->id)));

        // Never place the CompanyToken in the redirect URL — it would land
        // in browser history, Referer headers, and any HTTP access log
        // between the IdP and the SPA. Instead stash the token behind a
        // one-shot random exchange code with a 60s TTL, and hand the SPA
        // only the code; the SPA calls POST /api/v1/oidc/exchange to swap
        // it for the real token exactly once.
        $exchange_code = Str::random(64);
        Cache::put('oidc.exchange.' . $exchange_code, $company_token->token, now()->addSeconds(60));

        return redirect(config('ninja.react_url') . '/oauth-callback?code=' . $exchange_code);
    }

    public function config(): JsonResponse
    {
        return response()->json([
            'oidc_enabled' => !empty(config('services.oidc.client_id')) && !empty(config('services.oidc.well_known')),
            'oidc_provider_label' => config('services.oidc.provider_label', 'OIDC'),
        ]);
    }

    public function exchange(Request $request): JsonResponse
    {
        $code = (string) $request->input('code', '');

        // Fixed-length guard rejects trivially short guesses without giving
        // any information about which specific codes are live.
        if (strlen($code) !== 64) {
            return response()->json(['message' => 'Invalid OIDC exchange code.'], 400);
        }

        $token = Cache::pull('oidc.exchange.' . $code);

        if (!$token) {
            return response()->json(['message' => 'OIDC exchange code is expired or already used.'], 400);
        }

        return response()->json(['token' => $token]);
    }

    public function authenticate(): SocialiteUser|JsonResponse
    {
        try {
            /** @var \Laravel\Socialite\Two\User $socialite_user */
            $socialite_user = Socialite::driver('oidc')->user();
        } catch (\Throwable $e) {
            nlog('OIDC callback failed: ' . $e->getMessage());
            return response()->json(['message' => 'OIDC sign-in failed.'], 400);
        }

        if (!$socialite_user || !$socialite_user->getId()) {
            return response()->json(['message' => 'OIDC sign-in failed: missing subject identifier.'], 400);
        }

        return $socialite_user;
    }

    public function resolveUser(SocialiteUser $socialite_user): User|JsonResponse
    {
        $user = MultiDB::hasUser([
            'oauth_user_id' => $socialite_user->getId(),
            'oauth_provider_id' => 'oidc',
        ]);

        // Fall back to a one-time email link for accounts provisioned
        // outside of OIDC. Only link when:
        //  * the IdP asserts email_verified: true — otherwise an attacker
        //    who can register an unverified account at the IdP under a
        //    victim's address could take over the matching local account;
        //  * the local account has no other OAuth provider attached, to
        //    avoid silently hijacking a google/microsoft linkage.
        $raw = $socialite_user->getRaw();
        $email_verified = ($raw['email_verified'] ?? false) === true;

        if (!$user && $email_verified && $socialite_user->getEmail()) {
            $email_user = MultiDB::hasUser(['email' => $socialite_user->getEmail()]);

            if ($email_user && (!$email_user->oauth_provider_id || $email_user->oauth_provider_id === 'oidc')) {
                $email_user->update([
                    'oauth_user_id' => $socialite_user->getId(),
                    'oauth_provider_id' => 'oidc',
                ]);
                $user = $email_user;
            }
        }

        if (!$user) {
            return response()->json(['message' => 'No Invoice Ninja account is linked to this OIDC identity. Ask an administrator to invite you first.'], 400);
        }

        if (!$user->account) {
            return response()->json(['message' => 'User exists but is not attached to any company.'], 400);
        }

        return $user;
    }
}
