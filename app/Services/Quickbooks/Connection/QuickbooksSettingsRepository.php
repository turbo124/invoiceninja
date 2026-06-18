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

use App\DataMapper\QuickbooksSettings;
use App\Models\Company;
use Carbon\Carbon;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;

class QuickbooksSettingsRepository
{
    public function __construct(private Company $company) {}

    public function company(): Company
    {
        return $this->company;
    }

    public function refreshCompany(): Company
    {
        $this->company = $this->company->fresh() ?? $this->company;

        return $this->company;
    }

    public function settings(): ?QuickbooksSettings
    {
        return $this->company->quickbooks;
    }

    public function isConnected(): bool
    {
        return (bool) ($this->company->quickbooks?->isConfigured());
    }

    public function requiresReconnect(): bool
    {
        return (bool) ($this->company->quickbooks?->requires_reconnect);
    }

    public function accessTokenConfig(): array
    {
        $settings = $this->company->quickbooks;

        if (! $settings || $settings->accessTokenExpiresAt <= 0) {
            return [];
        }

        return [
            'accessTokenKey' => $settings->accessTokenKey,
            'refreshTokenKey' => $settings->refresh_token,
            'QBORealmID' => $settings->realmID,
        ];
    }

    public function saveOAuthToken(OAuth2AccessToken $token): void
    {
        $settings = $this->company->quickbooks ?? new QuickbooksSettings();

        $this->preserveConnectionContext($token, $settings);

        $settings->accessTokenKey = $token->getAccessToken();
        $settings->refresh_token = $token->getRefreshToken();
        $settings->accessTokenExpiresAt = $this->normalizeTokenTimestamp($token->getAccessTokenExpiresAt());
        $settings->refreshTokenExpiresAt = $this->normalizeTokenTimestamp($token->getRefreshTokenExpiresAt());
        $settings->requires_reconnect = false;
        $settings->realmID = $token->getRealmID() ?: $settings->realmID;
        $settings->baseURL = $token->getBaseURL() ?: $settings->baseURL;

        $this->company->quickbooks = $settings;
        $this->company->save();
        $this->refreshCompany();
    }

    public function markRequiresReconnect(): void
    {
        if (! $this->company->quickbooks) {
            return;
        }

        $settings = $this->company->quickbooks;
        $settings->requires_reconnect = true;
        $this->company->quickbooks = $settings;
        $this->company->save();
        $this->refreshCompany();
    }

    public function clear(): void
    {
        $this->company->quickbooks = null;
        $this->company->save();
        $this->refreshCompany();
    }

    public function preserveConnectionContext(OAuth2AccessToken $token, QuickbooksSettings $settings): void
    {
        if ($token->getRealmID() === '' && $settings->realmID !== '') {
            $token->setRealmID($settings->realmID);
        }

        if ($token->getBaseURL() === '' && $settings->baseURL !== '') {
            $token->setBaseURL($settings->baseURL);
        }
    }

    private function normalizeTokenTimestamp(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return Carbon::createFromFormat('Y/m/d H:i:s', (string) $value)->timestamp;
    }
}
