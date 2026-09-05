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

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Login\LoginRequest;
use App\Http\Requests\Login\PrecheckLoginRequest;
use App\Models\CompanyUser;
use App\Services\Auth\Login\AppleLogin;
use App\Services\Auth\Login\GoogleLogin;
use App\Services\Auth\Login\LoginContext;
use App\Services\Auth\Login\MicrosoftLogin;
use App\Services\Auth\Login\OidcLogin;
use App\Services\Auth\Login\PasskeyLogin;
use App\Services\Auth\Login\PasswordLogin;
use App\Transformers\AuthenticatedCompanyUserTransformer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends BaseController
{
    protected $entity_type = CompanyUser::class;

    protected $entity_transformer = AuthenticatedCompanyUserTransformer::class;

    public function __construct(private LoginContext $loginContext)
    {
        parent::__construct();
    }

    public function apiLogin(LoginRequest $request, PasswordLogin $passwordLogin, PasskeyLogin $passkeyLogin)
    {
        $this->forced_includes = ['company_users'];

        if ($error = $passwordLogin->validate($request)) {
            return $error;
        }

        $passkeyResult = $passkeyLogin->attempt($request);
        $authenticated = $passkeyResult ?? $passwordLogin->attempt($request);

        if (!$authenticated) {
            return $passwordLogin->failedLogin($request);
        }

        $passwordLogin->recordSuccess($request->email);
        $user = Auth::guard()->user();

        // Successful passkey verification bypasses local TOTP.
        if ($passkeyResult !== true && $error = $passwordLogin->verifyTwoFactor($user, $request)) {
            return $error;
        }

        return $this->loginResponse($this->loginContext->completeLocalLogin($user, $request));
    }

    public function oauthApiLogin(Request $request)
    {
        $result = match ($request->input('provider')) {
            'google' => app(GoogleLogin::class)->login($request),
            'microsoft' => app(MicrosoftLogin::class)->login($request),
            'apple' => app(AppleLogin::class)->login($request),
            default => response()->json(['message' => 'Provider not supported'], 400)
                ->header('X-App-Version', config('ninja.app_version'))
                ->header('X-Api-Version', config('ninja.minimum_client_version')),
        };

        return $this->loginResponse($result);
    }

    public function redirectToProvider(string $provider, Request $request)
    {
        return $this->browserProvider($provider)->handleRedirect($request);
    }

    public function oidcConfig(OidcLogin $oidcLogin): JsonResponse
    {
        return $oidcLogin->config();
    }

    public function oidcExchange(Request $request, OidcLogin $oidcLogin): JsonResponse
    {
        return $oidcLogin->exchange($request);
    }

    public function precheck(PrecheckLoginRequest $request, PasswordLogin $passwordLogin): JsonResponse
    {
        return $passwordLogin->precheck($request);
    }

    public function refreshReact(Request $request)
    {
        $result = $this->loginContext->refresh($request, react: true);

        return $result instanceof Builder ? $this->refreshReactResponse($result) : $result;
    }

    public function refresh(Request $request)
    {
        $result = $this->loginContext->refresh($request);

        return $result instanceof Builder ? $this->refreshResponse($result) : $result;
    }

    private function browserProvider(string $provider): GoogleLogin|MicrosoftLogin|OidcLogin
    {
        return match ($provider) {
            'google' => app(GoogleLogin::class),
            'microsoft' => app(MicrosoftLogin::class),
            'oidc' => app(OidcLogin::class),
            default => abort(400, 'Invalid provider'),
        };
    }

    private function loginResponse($result)
    {
        return $result instanceof Builder ? $this->timeConstrainedResponse($result) : $result;
    }
}
