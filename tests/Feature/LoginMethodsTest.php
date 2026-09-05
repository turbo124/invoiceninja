<?php

namespace Tests\Feature;

use App\DataMapper\CompanySettings;
use App\Events\User\UserLoggedIn;
use App\Libraries\OAuth\Providers\Google;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Services\Auth\Login\OidcLogin;
use App\Services\Auth\Passkeys\PasskeyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class LoginMethodsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ninja.db.multi_db_enabled' => false]);
        $this->withHeaders(['X-API-SECRET' => config('ninja.api_secret')]);
        Event::fake([UserLoggedIn::class]);
    }

    public function testPasswordLoginStillRequiresTotp(): void
    {
        $user = $this->createUser();
        $secret = (new Google2FA())->generateSecretKey();
        $user->google_2fa_secret = encrypt($secret);
        $user->save();

        $credentials = ['email' => $user->email, 'password' => 'login-test-password'];

        $this->postJson('/api/v1/login', $credentials)
            ->assertStatus(400)
            ->assertHeader('X-App-Version', config('ninja.app_version'));

        $this->postJson('/api/v1/login', $credentials + ['one_time_password' => 'invalid'])
            ->assertStatus(422);

        Event::assertNotDispatched(UserLoggedIn::class);

        $this->postJson('/api/v1/login', $credentials + [
            'one_time_password' => (new Google2FA())->getCurrentOtp($secret),
        ])->assertOk();

        Event::assertDispatchedTimes(UserLoggedIn::class, 1);
    }

    public function testPasskeyLoginBypassesLocalTotp(): void
    {
        $user = $this->createUser();
        $user->google_2fa_secret = encrypt((new Google2FA())->generateSecretKey());
        $user->save();

        $passkeys = Mockery::mock(PasskeyService::class);
        $passkeys->shouldReceive('authenticate')->once()
            ->withArgs(fn (User $candidate, string $token, array $payload) => $candidate->id === $user->id && $token === 'challenge')
            ->andReturn($user);
        $this->app->instance(PasskeyService::class, $passkeys);

        $this->postJson('/api/v1/login', $this->passkeyPayload($user))->assertOk();

        Event::assertDispatchedTimes(UserLoggedIn::class, 1);
    }

    public function testFailedPasskeyLoginDoesNotCompleteLogin(): void
    {
        $user = $this->createUser();
        $passkeys = Mockery::mock(PasskeyService::class);
        $passkeys->shouldReceive('authenticate')->once()->andThrow(new \RuntimeException('Expired challenge'));
        $this->app->instance(PasskeyService::class, $passkeys);

        $this->postJson('/api/v1/login', $this->passkeyPayload($user))->assertStatus(400);

        Event::assertNotDispatched(UserLoggedIn::class);
        $this->assertGuest();
    }

    public function testPasswordTakesPrecedenceWhenBothCredentialsArePresent(): void
    {
        $user = $this->createUser();
        $passkeys = Mockery::mock(PasskeyService::class);
        $passkeys->shouldNotReceive('authenticate');
        $this->app->instance(PasskeyService::class, $passkeys);

        $this->postJson('/api/v1/login', $this->passkeyPayload($user) + [
            'password' => 'login-test-password',
        ])->assertOk();
    }

    public function testGoogleLoginCompletesExistingIdentityAndDispatchesEvent(): void
    {
        $user = $this->createUser('google', 'google-subject');
        $this->mockGoogle($user->email, 'google-subject');

        $this->postJson('/api/v1/oauth_login', ['provider' => 'google', 'id_token' => 'google-token'])
            ->assertOk();

        $this->assertAuthenticatedAs($user);
        Event::assertDispatchedTimes(UserLoggedIn::class, 1);
    }

    public function testGoogleDoesNotLinkPasswordAccountEvenWhenSignupRequested(): void
    {
        $user = $this->createUser();
        $this->mockGoogle($user->email, 'google-subject');

        $this->postJson('/api/v1/oauth_login', [
            'provider' => 'google', 'id_token' => 'google-token', 'create' => 'true',
        ])->assertStatus(400)->assertJson(['message' => 'Please use your email and password to login.']);

        $this->assertNull($user->fresh()->oauth_provider_id);
        Event::assertNotDispatched(UserLoggedIn::class);
    }

    public function testGoogleDoesNotCreateAccountWithoutSignupFlag(): void
    {
        $this->mockGoogle('unregistered-login-method@example.com', 'unknown-subject');
        $before = Account::count();

        $this->postJson('/api/v1/oauth_login', ['provider' => 'google', 'id_token' => 'google-token'])
            ->assertStatus(400);

        $this->assertSame($before, Account::count());
    }

    public function testMicrosoftRejectsClientMismatchBeforeCallingGraph(): void
    {
        config(['services.microsoft.client_id' => 'expected-client']);
        $token = 'header.'.rtrim(strtr(base64_encode(json_encode(['appid' => 'wrong-client'])), '+/', '-_'), '=').'.signature';

        foreach (['accessToken', 'access_token'] as $field) {
            $this->postJson('/api/v1/oauth_login', ['provider' => 'microsoft', $field => $token])
                ->assertStatus(403)
                ->assertJson(['message' => 'Invalid Microsoft token: audience mismatch.']);
        }
    }

    public function testAppleLinksPasswordAccountWithoutDispatchingLoginEvent(): void
    {
        $user = $this->createUser();
        $identity = (new SocialiteUser())->map(['id' => 'apple-subject', 'email' => $user->email]);
        Socialite::shouldReceive('driver->userFromToken')->once()->with('apple-token')->andReturn($identity);

        $this->postJson('/api/v1/oauth_login', ['provider' => 'apple', 'id_token' => 'apple-token'])
            ->assertOk();

        $this->assertSame('apple', $user->fresh()->oauth_provider_id);
        $this->assertSame('apple-subject', $user->fresh()->oauth_user_id);
        Event::assertNotDispatched(UserLoggedIn::class);
    }

    public function testAppleCannotReplaceAnotherProvider(): void
    {
        $user = $this->createUser('google', 'google-subject');
        $identity = (new SocialiteUser())->map(['id' => 'apple-subject', 'email' => $user->email]);
        Socialite::shouldReceive('driver->userFromToken')->once()->andReturn($identity);

        $this->postJson('/api/v1/oauth_login', ['provider' => 'apple', 'id_token' => 'apple-token'])
            ->assertStatus(400);

        $this->assertSame('google', $user->fresh()->oauth_provider_id);
        $this->assertGuest();
    }

    public function testOidcEmailLinkRequiresStrictlyVerifiedEmail(): void
    {
        $user = $this->createUser();
        $login = app(OidcLogin::class);

        foreach ([false, 'true', 1, null] as $verified) {
            $result = $login->resolveUser($this->oidcIdentity($user->email, $verified));
            $this->assertInstanceOf(JsonResponse::class, $result);
            $this->assertSame(400, $result->getStatusCode());
            $this->assertNull($user->fresh()->oauth_provider_id);
        }

        $result = $login->resolveUser($this->oidcIdentity($user->email, true));

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->id, $result->id);
        $this->assertSame('oidc', $user->fresh()->oauth_provider_id);
    }

    public function testOidcCannotReplaceAnotherProviderOrProvisionUnknownUser(): void
    {
        $user = $this->createUser('google', 'google-subject');
        $login = app(OidcLogin::class);
        $before = Account::count();

        foreach ([$user->email, 'unknown-oidc-login@example.com'] as $email) {
            $result = $login->resolveUser($this->oidcIdentity($email, true));
            $this->assertInstanceOf(JsonResponse::class, $result);
            $this->assertSame(400, $result->getStatusCode());
        }

        $this->assertSame('google', $user->fresh()->oauth_provider_id);
        $this->assertSame($before, Account::count());
    }

    public function testOidcExistingSubjectDoesNotRequireEmailLink(): void
    {
        $user = $this->createUser('oidc', 'oidc-subject');

        $result = app(OidcLogin::class)->resolveUser($this->oidcIdentity('changed@example.com', false));

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->id, $result->id);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function testOidcCallbackReturnsExchangeCodeAndDispatchesLoginEvent(): void
    {
        $user = $this->createUser('oidc', 'oidc-subject');
        config([
            'services.oidc.well_known' => 'https://identity.example.com/.well-known/openid-configuration',
            'ninja.react_url' => 'https://app.example.com',
        ]);
        $identity = $this->oidcIdentity($user->email, true);
        Socialite::shouldReceive('driver->user')->once()->andReturn($identity);

        $response = $this->get('/auth/oidc?code=provider-code')->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://app.example.com/oauth-callback?code=', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(64, strlen($query['code']));

        Event::assertDispatchedTimes(UserLoggedIn::class, 1);
        Event::assertDispatched(UserLoggedIn::class, fn ($event) => $event->user->id === $user->id);

        $exchange = $this->postJson('/api/v1/oidc/exchange', ['code' => $query['code']])->assertOk();
        $this->assertDatabaseHas('company_tokens', [
            'user_id' => $user->id,
            'company_id' => $user->account->default_company_id,
            'token' => $exchange->json('token'),
            'is_system' => true,
        ]);
        $this->assertStringNotContainsString($exchange->json('token'), $location);
        $this->postJson('/api/v1/oidc/exchange', ['code' => $query['code']])->assertStatus(400);
    }

    public function testOidcRejectsMissingSubjectBeforeResolvingUser(): void
    {
        Socialite::shouldReceive('driver->user')->once()->andReturn((new SocialiteUser())->map(['id' => null]));

        $result = app(OidcLogin::class)->authenticate();

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(400, $result->getStatusCode());
        $this->assertSame('OIDC sign-in failed: missing subject identifier.', $result->getData(true)['message']);
    }

    public function testBrowserProvidersKeepTheirRedirectParametersAndScopes(): void
    {
        config([
            'ninja.app_url' => 'https://invoice.example.com',
            'services.oidc.well_known' => 'https://identity.example.com/discovery',
            'services.oidc.redirect' => 'https://invoice.example.com/auth/oidc',
            'services.oidc.scopes' => 'openid  profile email',
        ]);

        $providers = [
            'google' => [
                ['access_type' => 'offline', 'prompt' => 'consent select_account', 'redirect_uri' => 'https://invoice.example.com/auth/google'],
                ['https://www.googleapis.com/auth/gmail.send', 'email', 'profile', 'openid'],
            ],
            'microsoft' => [
                ['response_type' => 'code', 'redirect_uri' => 'https://invoice.example.com/auth/microsoft'],
                ['email', 'Mail.Send', 'offline_access', 'profile', 'User.Read openid'],
            ],
            'oidc' => [
                ['response_type' => 'code', 'redirect_uri' => 'https://invoice.example.com/auth/oidc'],
                ['openid', 'profile', 'email'],
            ],
        ];

        foreach ($providers as $provider => [$parameters, $scopes]) {
            $driver = Mockery::mock();
            Socialite::shouldReceive('driver')->once()->with($provider)->andReturn($driver);
            $driver->shouldReceive('with')->once()->with($parameters)->andReturnSelf();
            $driver->shouldReceive('scopes')->once()->with($scopes)->andReturnSelf();
            $driver->shouldReceive('redirect')->once()->andReturn(redirect('https://identity.example.com/authorize'));

            $this->get('/auth/'.$provider)->assertRedirect('https://identity.example.com/authorize');
        }
    }

    public function testCancelledBrowserLoginDoesNotRestartAuthorization(): void
    {
        config(['services.oidc.well_known' => 'https://identity.example.com/discovery']);
        Socialite::shouldReceive('driver')->never();

        foreach (['google', 'microsoft', 'oidc'] as $provider) {
            $this->get('/auth/'.$provider.'?error=access_denied&code=ignored')
                ->assertStatus(400)
                ->assertJson(['message' => 'OAuth sign-in was cancelled or failed.']);
        }
    }

    public function testOidcConfigurationAndDisabledRedirect(): void
    {
        config([
            'services.oidc.client_id' => 'oidc-client',
            'services.oidc.well_known' => 'https://identity.example.com/discovery',
            'services.oidc.provider_label' => 'Company SSO',
        ]);

        $this->getJson('/api/v1/oidc/config')->assertOk()->assertExactJson([
            'oidc_enabled' => true,
            'oidc_provider_label' => 'Company SSO',
        ]);

        config(['services.oidc.well_known' => null]);

        $this->getJson('/api/v1/oidc/config')->assertOk()->assertJson(['oidc_enabled' => false]);
        $this->get('/auth/oidc')->assertNotFound();
    }

    public function testGoogleConnectionCallbackUpdatesLinkedUserWithoutLoginEvent(): void
    {
        $user = $this->createUser('google', 'google-subject');
        config(['ninja.react_url' => 'https://app.example.com']);
        $identity = (new SocialiteUser())->map([
            'id' => 'google-subject', 'email' => $user->email, 'name' => 'Connected User',
        ]);
        Socialite::shouldReceive('driver->user')->once()->andReturn($identity);

        $this->get('/auth/google?code=connection-code')
            ->assertRedirect('https://app.example.com/#/settings/user_details/connect');

        $this->assertSame('Connected', $user->fresh()->first_name);
        $this->assertSame('google', $user->fresh()->oauth_provider_id);
        Event::assertNotDispatched(UserLoggedIn::class);
    }

    public function testMicrosoftConnectionCallbackStoresConnectionTokensWithoutLoginEvent(): void
    {
        $user = $this->createUser('microsoft', 'microsoft-subject');
        config(['ninja.react_url' => 'https://app.example.com']);
        $identity = (new \SocialiteProviders\Manager\OAuth2\User())->map([
            'id' => 'microsoft-subject', 'email' => $user->email, 'name' => 'Connected User',
        ])->setAccessTokenResponseBody([
            'access_token' => 'mail-access-token',
            'refresh_token' => 'mail-refresh-token',
            'expires_in' => 3600,
        ]);
        Socialite::shouldReceive('driver->user')->once()->andReturn($identity);

        $this->get('/auth/microsoft?code=connection-code')
            ->assertRedirect('https://app.example.com/#/settings/user_details/connect');

        $this->assertSame('Connected', $user->fresh()->first_name);
        $this->assertSame('mail-access-token', $user->fresh()->oauth_user_token);
        $this->assertSame('mail-refresh-token', $user->fresh()->oauth_user_refresh_token);
        Event::assertNotDispatched(UserLoggedIn::class);
    }

    public function testBothRefreshEndpointsStillReturnCurrentCompany(): void
    {
        $user = $this->createUser();
        $this->postJson('/api/v1/login', [
            'email' => $user->email, 'password' => 'login-test-password',
        ])->assertOk();
        $token = \App\Models\CompanyToken::where('user_id', $user->id)->where('is_system', true)->firstOrFail();

        $this->withHeaders(['X-API-TOKEN' => $token->token]);

        foreach (['refresh', 'refresh_react'] as $endpoint) {
            $this->postJson('/api/v1/'.$endpoint, ['current_company' => 'true'])->assertOk();
        }
    }

    private function createUser(?string $provider = null, ?string $subject = null): User
    {
        $account = Account::factory()->create();
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => 'login-method-'.Str::uuid().'@gmail.com',
            'password' => Hash::make('login-test-password'),
            'oauth_provider_id' => $provider,
            'oauth_user_id' => $subject,
        ]);
        $company = Company::factory()->create(['account_id' => $account->id]);
        $account->default_company_id = $company->id;
        $account->save();
        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => true,
            'is_admin' => true,
            'notifications' => CompanySettings::notificationDefaults(),
        ]);

        return $user;
    }

    private function mockGoogle(string $email, string $subject): void
    {
        $google = Mockery::mock(Google::class)->makePartial();
        $google->shouldReceive('getTokenResponse')->once()->with('google-token')
            ->andReturn(['email' => $email, 'sub' => $subject]);
        $this->app->instance(Google::class, $google);
    }

    private function oidcIdentity(string $email, mixed $verified): SocialiteUser
    {
        return (new SocialiteUser())->setRaw(['email_verified' => $verified])->map([
            'id' => 'oidc-subject', 'email' => $email, 'name' => 'OIDC User',
        ]);
    }

    private function passkeyPayload(User $user): array
    {
        return [
            'email' => $user->email,
            'passkey_challenge_token' => 'challenge',
            'passkey_authentication' => [
                'id' => 'credential',
                'clientDataJSON' => 'client-data',
                'authenticatorData' => 'authenticator-data',
                'signature' => 'signature',
            ],
        ];
    }
}
