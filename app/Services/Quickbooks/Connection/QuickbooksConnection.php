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

namespace App\Services\Quickbooks\Connection;

use App\Models\Company;
use App\Services\Quickbooks\SdkWrapper;
use QuickBooksOnline\API\Core\CoreConstants;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;
use QuickBooksOnline\API\DataService\DataService;

class QuickbooksConnection
{
    private QuickbooksSettingsRepository $settings;

    private QuickbooksTokenManager $tokenManager;

    private ?SdkWrapper $sdkWrapper = null;

    private ?DataService $dataService = null;

    public function __construct(private Company $company, ?DataService $dataService = null)
    {
        $this->settings = new QuickbooksSettingsRepository($company);
        $this->dataService = $dataService ?? $this->configureDataService();

        if ($this->dataService) {
            $this->tokenManager = new QuickbooksTokenManager(
                $company,
                $this->dataService,
                $this->settings,
                new QuickbooksReconnectNotifier()
            );

            if (! $this->settings->requiresReconnect() && $this->tokenManager->tokenNeedsRefresh()) {
                $this->tokenManager->refreshIfNeeded();
            }
        }
    }

    public function hasDataService(): bool
    {
        return (bool) $this->dataService;
    }

    public function dataService(): ?DataService
    {
        return $this->dataService;
    }

    public function sdk(): SdkWrapper
    {
        return $this->sdkWrapper ??= new SdkWrapper($this->dataService, $this->company, $this->tokenManager);
    }

    public function authorizationUrl(): string
    {
        return $this->dataService->getOAuth2LoginHelper()->getAuthorizationCodeURL();
    }

    public function state(): string
    {
        return $this->dataService->getOAuth2LoginHelper()->getState();
    }

    public function exchangeCodeForToken(string $code, string $realm): OAuth2AccessToken
    {
        $token = $this->dataService->getOAuth2LoginHelper()->exchangeAuthorizationCodeForToken($code, $realm);
        $this->tokenManager->setAccessToken($token);

        return $token;
    }

    public function saveOAuthToken(OAuth2AccessToken $token): void
    {
        $this->tokenManager->saveOAuthToken($token);
        $this->company = $this->settings->company();
    }

    public function ensureTokenFresh(bool $force = false): void
    {
        $this->tokenManager->refreshIfNeeded($force);
        $this->company = $this->settings->company();
    }

    public function isAuthenticationFailure(\Throwable $e): bool
    {
        return $this->tokenManager->isAuthenticationFailure($e);
    }

    public function accessToken(): ?OAuth2AccessToken
    {
        return $this->tokenManager->accessToken();
    }

    public function disconnect(): void
    {
        try {
            if ($this->tokenManager->accessToken()) {
                $this->dataService->getOAuth2LoginHelper()->revokeToken($this->tokenManager->accessToken()->getAccessToken());
            }
        } catch (\Throwable $e) {
            nlog("QB: failure to revoke token during disconnect:: " . $e->getMessage());
        }

        $this->settings->clear();
        $this->company = $this->settings->company();
    }

    private function configureDataService(): ?DataService
    {
        if (! config('services.quickbooks.client_id')) {
            return null;
        }

        $config = [
            'ClientID' => config('services.quickbooks.client_id'),
            'ClientSecret' => config('services.quickbooks.client_secret'),
            'auth_mode' => 'oauth2',
            'scope' => 'com.intuit.quickbooks.accounting',
            'RedirectURI' => config('services.quickbooks.redirect'),
            'baseUrl' => config('services.quickbooks.env') === 'sandbox' ? CoreConstants::SANDBOX_DEVELOPMENT : CoreConstants::QBO_BASEURL,
        ];

        if (! $this->settings->requiresReconnect()) {
            $config = array_merge($config, $this->settings->accessTokenConfig());
        }

        $dataService = DataService::Configure($config);
        $dataService->enableLog();
        $dataService->setMinorVersion('75');
        $dataService->throwExceptionOnError(true);

        return $dataService;
    }
}
