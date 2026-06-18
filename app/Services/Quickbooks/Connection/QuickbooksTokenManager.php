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
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Exception\ServiceException;

class QuickbooksTokenManager
{
    private const ACCESS_TOKEN_REFRESH_LEEWAY_SECONDS = 300;

    private const TOKEN_REFRESH_LOCK_SECONDS = 30;

    private const TOKEN_REFRESH_LOCK_WAIT_SECONDS = 10;

    private ?OAuth2AccessToken $token = null;

    public function __construct(
        private Company $company,
        private DataService $sdk,
        private QuickbooksSettingsRepository $settings,
        private QuickbooksReconnectNotifier $notifier,
    ) {
        $this->setStoredAccessToken();
    }

    public function accessToken(): ?OAuth2AccessToken
    {
        return $this->token;
    }

    public function setAccessToken(OAuth2AccessToken $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function setStoredAccessToken(): self
    {
        $settings = $this->settings->settings();

        if (! $settings || ! $settings->accessTokenKey || $settings->requires_reconnect) {
            return $this;
        }

        $token = new OAuth2AccessToken(
            config('services.quickbooks.client_id'),
            config('services.quickbooks.client_secret'),
            $settings->accessTokenKey,
            $settings->refresh_token,
            3600,
            8726400
        );

        $token->setAccessTokenExpiresAt($this->formatTokenTimestamp($settings->accessTokenExpiresAt));
        $token->setRefreshTokenExpiresAt($this->formatTokenTimestamp($settings->refreshTokenExpiresAt));
        $token->setAccessTokenValidationPeriodInSeconds(3600);
        $token->setRefreshTokenValidationPeriodInSeconds(8726400);

        $this->settings->preserveConnectionContext($token, $settings);
        $this->setAccessToken($token);

        return $this;
    }

    public function refreshIfNeeded(bool $force = false): self
    {
        if (! $this->settings->settings() || $this->settings->requiresReconnect()) {
            return $this;
        }

        Cache::lock(
            "quickbooks-token-refresh:{$this->company->id}:{$this->company->db}",
            self::TOKEN_REFRESH_LOCK_SECONDS
        )->block(self::TOKEN_REFRESH_LOCK_WAIT_SECONDS, function () use ($force): void {
            $this->company = $this->settings->refreshCompany();

            if (! $this->settings->settings() || $this->settings->requiresReconnect()) {
                return;
            }

            if (! $force && ! $this->tokenNeedsRefresh()) {
                $this->setStoredAccessToken();

                if ($this->token) {
                    $this->sdk->updateOAuth2Token($this->token);
                }

                return;
            }

            if ($this->refreshTokenExpired()) {
                $this->markRequiresReconnect();

                throw new \RuntimeException('Quickbooks refresh token expired');
            }

            try {
                $this->refreshToken($this->settings->settings()->refresh_token);
            } catch (\Throwable $e) {
                if ($this->isRefreshTokenFailure($e)) {
                    $this->markRequiresReconnect();
                }

                throw $e;
            }
        });

        return $this;
    }

    public function refreshToken(string $refresh_token): self
    {
        $new_token = $this->sdk->getOAuth2LoginHelper()->refreshAccessTokenWithRefreshToken($refresh_token);

        if ($settings = $this->settings->settings()) {
            $this->settings->preserveConnectionContext($new_token, $settings);
        }

        $this->setAccessToken($new_token);
        $this->sdk->updateOAuth2Token($new_token);
        $this->settings->saveOAuthToken($new_token);
        $this->company = $this->settings->company();

        return $this;
    }

    public function saveOAuthToken(OAuth2AccessToken $token): void
    {
        $this->setAccessToken($token);
        $this->settings->saveOAuthToken($token);
        $this->company = $this->settings->company();
    }

    public function markRequiresReconnect(): void
    {
        $this->settings->markRequiresReconnect();
        $this->company = $this->settings->company();
        $this->notifier->notifyOwnerTokenExpired($this->company);
    }

    public function tokenNeedsRefresh(int $leewaySeconds = self::ACCESS_TOKEN_REFRESH_LEEWAY_SECONDS): bool
    {
        $settings = $this->settings->settings();

        if (! $settings || $settings->requires_reconnect || $settings->accessTokenExpiresAt === 0) {
            return false;
        }

        return $settings->accessTokenExpiresAt <= time() + $leewaySeconds;
    }

    public function refreshTokenExpired(): bool
    {
        $settings = $this->settings->settings();

        if (! $settings) {
            return true;
        }

        return $settings->refreshTokenExpiresAt > 0
            && $settings->refreshTokenExpiresAt < time();
    }

    public function isAuthenticationFailure(\Throwable $e): bool
    {
        if ($e instanceof ServiceException && (int) $e->getCode() === 401) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, '401')
            || str_contains($message, 'unauthorized')
            || str_contains($message, 'authenticationfailed')
            || str_contains($message, 'invalid_token')
            || str_contains($message, 'token expired');
    }

    public function isRefreshTokenFailure(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'invalid_grant')
            || str_contains($message, 'refresh token')
            || str_contains($message, 'refresh_token');
    }

    private function formatTokenTimestamp(int $timestamp): string
    {
        return Carbon::createFromTimestamp($timestamp)->format('Y/m/d H:i:s');
    }
}
