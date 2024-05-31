<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \Webpatser\Countries\CountriesServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('client.login'));
        $middleware->redirectUsersTo(fn () => route('client.dashboard'));

        $middleware->validateCsrfTokens(except: [
            'setup/*',
            'setup',
        ]);

        $middleware->append([
            \App\Http\Middleware\CheckForMaintenanceMode::class,
            \App\Http\Middleware\Cors::class,
        ]);

        $middleware->web([
            \App\Http\Middleware\SessionDomains::class,
            \App\Http\Middleware\QueryLogging::class,
        ]);

        $middleware->api('query_logging');

        $middleware->group('contact', [
            'throttle:60,1',
            'bindings',
            'query_logging',
        ]);

        $middleware->group('client', [
            \App\Http\Middleware\SessionDomains::class,
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\QueryLogging::class,
        ]);

        $middleware->group('shop', [
            'throttle:120,1',
            'bindings',
            'query_logging',
        ]);

        $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, \App\Http\Middleware\TrustProxies::class);

        $middleware->alias([
            'api_db' => \App\Http\Middleware\SetDb::class,
            'api_secret_check' => \App\Http\Middleware\ApiSecretCheck::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'check_client_existence' => \App\Http\Middleware\CheckClientExistence::class,
            'company_key_db' => \App\Http\Middleware\SetDbByCompanyKey::class,
            'contact_account' => \App\Http\Middleware\ContactAccount::class,
            'contact_db' => \App\Http\Middleware\ContactSetDb::class,
            'contact_key_login' => \App\Http\Middleware\ContactKeyLogin::class,
            'contact_register' => \App\Http\Middleware\ContactRegister::class,
            'contact_token_auth' => \App\Http\Middleware\ContactTokenAuth::class,
            'cors' => \App\Http\Middleware\Cors::class,
            'document_db' => \App\Http\Middleware\SetDocumentDb::class,
            'domain_db' => \App\Http\Middleware\SetDomainNameDb::class,
            'email_db' => \App\Http\Middleware\SetEmailDb::class,
            'invite_db' => \App\Http\Middleware\SetInviteDb::class,
            'locale' => \App\Http\Middleware\Locale::class,
            'password_protected' => \App\Http\Middleware\PasswordProtection::class,
            'phantom_secret' => \App\Http\Middleware\PhantomSecret::class,
            'portal_enabled' => \App\Http\Middleware\ClientPortalEnabled::class,
            'query_logging' => \App\Http\Middleware\QueryLogging::class,
            'session_domain' => \App\Http\Middleware\SessionDomains::class,
            'signed' => \App\Http\Middleware\ValidateSignature::class,
            'token_auth' => \App\Http\Middleware\TokenAuth::class,
            'url_db' => \App\Http\Middleware\UrlSetDb::class,
            'user_verified' => \App\Http\Middleware\UserVerified::class,
            'vendor_contact_key_login' => \App\Http\Middleware\VendorContactKeyLogin::class,
            'vendor_locale' => \App\Http\Middleware\VendorLocale::class,
            'verify_hash' => \App\Http\Middleware\VerifyHash::class,
            'web_db' => \App\Http\Middleware\SetWebDb::class,
        ]);

        $middleware->priority([
            EncryptCookies::class,
            StartSession::class,
            SessionDomains::class,
            Cors::class,
            SetDomainNameDb::class,
            SetDb::class,
            SetWebDb::class,
            UrlSetDb::class,
            ContactSetDb::class,
            SetEmailDb::class,
            SetInviteDb::class,
            SetDbByCompanyKey::class,
            TokenAuth::class,
            ContactTokenAuth::class,
            ContactKeyLogin::class,
            Authenticate::class,
            ContactRegister::class,
            PhantomSecret::class,
            CheckClientExistence::class,
            ClientPortalEnabled::class,
            PasswordProtection::class,
            Locale::class,
            SubstituteBindings::class,
            ContactAccount::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
