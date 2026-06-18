<?php

namespace Tests\Unit\Services\Quickbooks\Connection;

use App\DataMapper\QuickbooksSettings;
use App\Services\Quickbooks\Connection\QuickbooksSettingsRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;
use Tests\MockAccountData;
use Tests\TestCase;

class QuickbooksSettingsRepositoryTest extends TestCase
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

    public function test_save_oauth_token_preserves_existing_realm_and_base_url_when_refresh_response_omits_them(): void
    {
        $this->company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'old-access-token',
            'refresh_token' => 'old-refresh-token',
            'realmID' => 'existing-realm',
            'accessTokenExpiresAt' => time() - 60,
            'refreshTokenExpiresAt' => time() + 86400,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
            'requires_reconnect' => true,
            'settings' => [],
        ]);
        $this->company->save();

        $repository = new QuickbooksSettingsRepository($this->company);
        $repository->saveOAuthToken($this->makeToken('new-access-token', 'new-refresh-token'));

        $quickbooks = $this->company->fresh()->quickbooks;

        $this->assertSame('new-access-token', $quickbooks->accessTokenKey);
        $this->assertSame('new-refresh-token', $quickbooks->refresh_token);
        $this->assertSame('existing-realm', $quickbooks->realmID);
        $this->assertSame('https://sandbox-quickbooks.api.intuit.com', $quickbooks->baseURL);
        $this->assertFalse($quickbooks->requires_reconnect);
    }

    public function test_mark_requires_reconnect_updates_existing_connection_state(): void
    {
        $this->company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'access-token',
            'refresh_token' => 'refresh-token',
            'realmID' => 'realm',
            'accessTokenExpiresAt' => time() - 60,
            'refreshTokenExpiresAt' => time() - 1,
            'settings' => [],
        ]);
        $this->company->save();

        (new QuickbooksSettingsRepository($this->company))->markRequiresReconnect();

        $this->assertTrue($this->company->fresh()->quickbooks->requires_reconnect);
    }

    public function test_clear_removes_quickbooks_connection(): void
    {
        $this->company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'access-token',
            'refresh_token' => 'refresh-token',
            'realmID' => 'realm',
            'settings' => [],
        ]);
        $this->company->save();

        (new QuickbooksSettingsRepository($this->company))->clear();

        $this->assertNull($this->company->fresh()->getRawOriginal('quickbooks'));
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

        return $token;
    }
}
