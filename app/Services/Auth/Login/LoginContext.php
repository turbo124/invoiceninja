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
use App\Jobs\Company\CreateCompanyToken;
use App\Models\Account;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\User;
use App\Services\Company\CompanyTokenRotator;
use App\Utils\Ninja;
use App\Utils\Traits\User\LoginCache;
use App\Utils\TruthSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LoginContext
{
    use LoginCache;

    public function completeLocalLogin(User $user, Request $request): Builder|JsonResponse
    {
        if (!$user->account->default_company) {
            $account = $user->account;
            $account->default_company_id = $user->companies->first()->id;
            $account->save();
            $user = $user->fresh();
        }

        nlog("LOGIN:: {$request->email} - {$user->account_id}");

        /** @var \Illuminate\Database\Eloquent\Builder $cu */
        $cu = $this->hydrate($user, $request);

        if ($cu->count() == 0) {
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
        }

        if (Ninja::isHosted() && !$cu->first()->is_owner && !$user->account->isEnterprisePaidClient()) { //@phpstan-ignore-line
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 401);
        }

        event(new UserLoggedIn($user, $user->account->default_company, Ninja::eventVars($user->id)));

        return $cu;
    }

    /** Complete OAuth API login while preserving each method's event policy. */
    public function completeOauthLogin($result, Request $request, bool $dispatchEvent = true)
    {
        if (!$result instanceof User && !$result instanceof Account) {
            return $result;
        }

        $new_account = $result instanceof Account;
        $user = $new_account ? $result->default_company->owner() : $result;

        if ($new_account) {
            Auth::login($user, false);
        }

        $cu = $this->hydrate($user, $request);

        if ($cu->count() == 0) {
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
        }

        if (Ninja::isHosted() && !$cu->first()->is_owner && !$user->account->isEnterprisePaidClient()) { //@phpstan-ignore-line
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);
        }

        if ($dispatchEvent && !$new_account) {
            event(new UserLoggedIn($user, $user->account->default_company, Ninja::eventVars($user->id)));
        }

        return $cu;
    }

    public function hydrate(User $user, Request $request): Builder
    {
        /** @var Builder $cu */
        $cu = CompanyUser::query()->where('user_id', $user->id);

        if ($cu->count() == 0) {
            return $cu;
        }

        if (CompanyUser::query()->where('user_id', $user->id)->where('company_id', $user->account->default_company_id)->exists()) {
            $set_company = $user->account->default_company;
        } else {
            $set_company = CompanyUser::query()->where('user_id', $user->id)->first()->company;
        }

        $user->setCompany($set_company);

        $this->setLoginCache($user);

        $truth = app()->make(TruthSource::class);
        $truth->setCompanyUser($cu->first());
        $truth->setUser($user);
        $truth->setCompany($set_company);

        $cu->each(function ($cu) use ($request) {
            if (CompanyToken::query()->where('company_id', $cu->company_id)->where('user_id', $cu->user_id)->where('is_system', true)->doesntExist()) { //@phpstan-ignore-line
                (new CreateCompanyToken($cu->company, $cu->user, $request->server('HTTP_USER_AGENT')))->handle(); //@phpstan-ignore-line
            }
        });

        app(CompanyTokenRotator::class)->rotateDueTokensForUser($user);

        $truth->setCompanyToken(CompanyToken::where('user_id', $user->id)->where('company_id', $set_company->id)->where('is_system', true)->first());

        return CompanyUser::query()->where('user_id', $user->id);
    }

    public function refresh(Request $request, bool $react = false): Builder|JsonResponse
    {
        $truth = app()->make(TruthSource::class);

        if ($truth->getCompanyToken()) {
            $company_token = $truth->getCompanyToken();
        } else {
            $company_token = CompanyToken::where('token', $request->header('X-API-TOKEN'))->first();
        }

        $cu = CompanyUser::query()
            ->where('user_id', $company_token->user_id);

        if ($cu->count() == 0) {
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
        }

        $cu->each(function ($company_user) use ($request) {
            if ($company_user->tokens()->where('company_id', $company_user->company_id)->where('is_system', true)->doesntExist()) {
                (new CreateCompanyToken($company_user->company, $company_user->user, $request->server('HTTP_USER_AGENT')))->handle();
            }
        });

        if ($request->has('current_company') && $request->input('current_company') == 'true') {
            $cu->where('company_id', $company_token->company_id);

            if (!$react) {
                $cu->with([
                    'company.users.company_users' => fn ($query) => $query
                        ->where('company_id', $company_token->company_id)
                        ->without(['user', 'account']),
                ]);
            }
        }

        if (Ninja::isHosted() && !$cu->first()->is_owner && !$cu->first()->user->account->isEnterprisePaidClient()) {
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);
        }

        return $cu;
    }

    /** Preserve React connection state and stop cancelled callbacks from redirecting again. */
    public function prepareProviderRequest(Request $request): ?JsonResponse
    {
        if ($request->hasHeader('X-REACT') || $request->query('react')) {
            /**@var \App\Models\User $user */
            $user = auth()->user();
            Cache::put("react_redir:" . $user?->account->key, 'true', 300);
        }

        // The IdP redirects back with `?error=...` when the user cancels or
        // consent fails. Short-circuit here so we never fall through to
        // Socialite::redirect() again, which would bounce the browser
        // straight back to the IdP and loop.
        if ($request->has('error')) {
            nlog('OAuth provider returned error: ' . $request->query('error'));
            return response()->json(['message' => 'OAuth sign-in was cancelled or failed.'], 400);
        }

        return null;
    }
}
