<?php

namespace Tests\Unit\Services\Quickbooks\Connection;

use App\DataMapper\QuickbooksSettings;
use App\Services\Quickbooks\Connection\QuickbooksReconnectNotifier;
use App\Services\Quickbooks\Connection\QuickbooksSettingsRepository;
use App\Services\Quickbooks\Connection\QuickbooksTokenManager;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Mockery;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
use QuickBooksOnline\API\DataService\DataService;
use Tests\MockAccountData;
use Tests\TestCase;

class QuickbooksTokenManagerTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        config(['services.quickbooks.client_id' => 'test-client-id']);
        config(['services.quickbooks.client_secret' => 'test-client-secret']);

        Cache::flush();
        $this->makeTestData();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_refresh_if_needed_uses_company_and_database_scoped_lock(): void
    {
        $this->company->db = 'db-ninja-02';
        $this->company->save();
        $this->configureQuickbooks(accessTokenExpiresAt: time() + 3600);

        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')
            ->once()
            ->with(10, Mockery::type(\Closure::class))
            ->andReturnNull();

        Cache::shouldReceive('lock')
            ->once()
            ->with("quickbooks-token-refresh:{$this->company->id}:db-ninja-02", 30)
            ->andReturn($lock);

        $manager = $this->manager(Mockery::mock(DataService::class)->makePartial());
        $manager->refreshIfNeeded();

        $this->addToAssertionCount(1);
    }

    public function test_refresh_if_needed_refreshes_nearly_expired_access_token_and_persists_it(): void
    {
        $this->configureQuickbooks(accessTokenExpiresAt: time() + 60);

        $newToken = $this->makeToken('new-access-token', 'new-refresh-token');
        $loginHelper = Mockery::mock(OAuth2LoginHelper::class);
        $loginHelper->shouldReceive('refreshAccessTokenWithRefreshToken')
            ->once()
            ->with('test-refresh-token')
            ->andReturn($newToken);

        $sdk = Mockery::mock(DataService::class)->makePartial();
        $sdk->shouldReceive('getOAuth2LoginHelper')->once()->andReturn($loginHelper);
        $sdk->shouldReceive('updateOAuth2Token')
            ->once()
            ->with(Mockery::on(fn (OAuth2AccessToken $token): bool => $token->getAccessToken() === 'new-access-token'
                && $token->getRefreshToken() === 'new-refresh-token'
                && $token->getRealmID() === 'test-realm'))
            ->andReturnSelf();

        $this->manager($sdk)->refreshIfNeeded();

        $quickbooks = $this->company->fresh()->quickbooks;

        $this->assertSame('new-access-token', $quickbooks->accessTokenKey);
        $this->assertSame('new-refresh-token', $quickbooks->refresh_token);
        $this->assertSame('test-realm', $quickbooks->realmID);
    }

    public function test_expired_refresh_token_marks_reconnect_required(): void
    {
        $this->configureQuickbooks(
            accessTokenExpiresAt: time() - 60,
            refreshTokenExpiresAt: time() - 1
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Quickbooks refresh token expired');

        try {
            $this->manager(Mockery::mock(DataService::class)->makePartial())->refreshIfNeeded(true);
        } finally {
            $this->assertTrue($this->company->fresh()->quickbooks->requires_reconnect);
        }
    }

    public function test_invalid_grant_marks_reconnect_required(): void
    {
        $this->configureQuickbooks(accessTokenExpiresAt: time() - 60);

        $loginHelper = Mockery::mock(OAuth2LoginHelper::class);
        $loginHelper->shouldReceive('refreshAccessTokenWithRefreshToken')
            ->once()
            ->with('test-refresh-token')
            ->andThrow(new \RuntimeException('invalid_grant'));

        $sdk = Mockery::mock(DataService::class)->makePartial();
        $sdk->shouldReceive('getOAuth2LoginHelper')->once()->andReturn($loginHelper);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_grant');

        try {
            $this->manager($sdk)->refreshIfNeeded(true);
        } finally {
            $this->assertTrue($this->company->fresh()->quickbooks->requires_reconnect);
        }
    }

    private function manager(DataService $sdk): QuickbooksTokenManager
    {
        return new QuickbooksTokenManager(
            $this->company,
            $sdk,
            new QuickbooksSettingsRepository($this->company),
            Mockery::mock(QuickbooksReconnectNotifier::class)->shouldIgnoreMissing()
        );
    }

    private function configureQuickbooks(int $accessTokenExpiresAt, ?int $refreshTokenExpiresAt = null): void
    {
        $this->company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'realmID' => 'test-realm',
            'accessTokenExpiresAt' => $accessTokenExpiresAt,
            'refreshTokenExpiresAt' => $refreshTokenExpiresAt ?? time() + 86400,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
            'companyName' => 'Test Company',
            'settings' => [],
        ]);
        $this->company->save();
    }

    private function makeToken(string $accessToken, string $refreshToken): OAuth2AccessToken
    {
        $token = new OAuth2AccessToken(
            'test-client-id',
            'test-client-secret',
            $accessToken,
            $refreshToken,
            3600,
            8726400
        );

        $token->setAccessTokenExpiresAt(now()->addHour()->timestamp);
        $token->setRefreshTokenExpiresAt(now()->addDays(100)->timestamp);
        $token->setRealmID('test-realm');
        $token->setBaseURL('https://sandbox-quickbooks.api.intuit.com');

        return $token;
    }
}
