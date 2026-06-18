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

namespace App\Services\Quickbooks;

use App\Models\Company;
use App\Services\Quickbooks\Connection\QuickbooksReconnectNotifier;
use App\Services\Quickbooks\Connection\QuickbooksSettingsRepository;
use App\Services\Quickbooks\Connection\QuickbooksTokenManager;
use QuickBooksOnline\API\DataService\DataService;

class SdkWrapper
{
    public const MAXRESULTS = 1000;

    private const RATE_LIMIT_MAX_WAIT_SECONDS = 90;

    private $entities = ['Customer','Invoice', 'Item', 'SalesReceipt', 'Vendor', 'Purchase', 'Payment'];

    private ?QuickbooksRateLimiter $rate_limiter = null;

    public function __construct(
        public DataService $sdk,
        private Company $company,
        private ?QuickbooksTokenManager $token_manager = null
    ) {
        $this->init();
    }

    private function init(): self
    {
        $this->token_manager ??= new QuickbooksTokenManager(
            $this->company,
            $this->sdk,
            new QuickbooksSettingsRepository($this->company),
            new QuickbooksReconnectNotifier()
        );

        return $this;

    }

    public function company()
    {
        return $this->execute(fn () => $this->sdk->getCompanyInfo());
    }

    public function getPreferences()
    {
        return $this->execute(fn () => $this->sdk->getCompanyPreferences());
    }


    /// Data Access ///

    public function totalRecords(string $entity): int
    {
        $whereClause = $this->buildEntityWhereClause($entity);
        $query = "select count(*) from $entity" . ($whereClause ? " WHERE $whereClause" : "");
        return (int) $this->query($query);
    }

    private function queryData(string $query, int $start = 1, $limit = 1000): array
    {
        return (array) $this->query($query, $start, $limit);
    }

    public function query(string $query, ?int $start = null, ?int $limit = null): mixed
    {
        return $this->execute(function () use ($query, $start, $limit): mixed {
            if ($start === null && $limit === null) {
                return $this->sdk->Query($query);
            }

            return $this->sdk->Query($query, $start, $limit);
        });
    }

    public function fetchById(string $entity, $id)
    {
        return $this->findById($entity, $id);
    }

    public function findById(string $entity, mixed $id): mixed
    {
        return $this->execute(fn () => $this->sdk->FindById($entity, $id));
    }

    public function add(mixed $entity): mixed
    {
        return $this->execute(fn () => $this->sdk->Add($entity));
    }

    public function update(mixed $entity): mixed
    {
        return $this->execute(fn () => $this->sdk->Update($entity));
    }

    public function voidEntity(mixed $entity): mixed
    {
        return $this->execute(fn () => $this->sdk->Void($entity));
    }

    public function fetchRecordsPage(string $entity, int $startPosition = 1, int $limit = self::MAXRESULTS): array
    {
        if (!in_array($entity, $this->entities)) {
            return [];
        }

        $startPosition = max(1, $startPosition);
        $limit = max(1, min($limit, self::MAXRESULTS));

        $whereClause = $this->buildEntityWhereClause($entity);
        $baseQuery = "select * from $entity" . ($whereClause ? " WHERE $whereClause" : "");

        return $this->normalizeRecords($this->query($baseQuery, $startPosition, $limit));
    }

    public function fetchRecords(string $entity, int $max = 100000): array
    {

        if (!in_array($entity, $this->entities)) {
            return [];
        }

        $records = [];
        $start = 0;
        $limit = 1000;
        try {

            // Build query with filters for specific entities
            $whereClause = $this->buildEntityWhereClause($entity);
            $baseQuery = "select * from $entity" . ($whereClause ? " WHERE $whereClause" : "");

            $total = $this->totalRecords($entity);
            $total = min($max, $total);

            // Step 3 & 4: Get chunks of records until the total required records are retrieved
            do {
                $limit = min(self::MAXRESULTS, $total - $start);

                $recordsChunk = $this->queryData($baseQuery, $start, $limit);
                if (empty($recordsChunk)) {
                    break;
                }

                $records = array_merge($records, $recordsChunk);
                $start += $limit;

            } while ($start < $total);
            if (empty($records)) {
                throw new \Exception("No records retrieved!");
            }

        } catch (\Throwable $th) {
            nlog("Fetch Quickbooks API Error: {$th->getMessage()}");
        }

        return $records;
    }

    private function normalizeRecords(mixed $records): array
    {
        if (empty($records)) {
            return [];
        }

        if (is_array($records)) {
            return array_is_list($records) ? $records : [$records];
        }

        return [$records];
    }

    /**
     * Build WHERE clause for entity-specific filtering.
     *
     * For Items, we only include types that can be used as line items on invoices.
     * QuickBooks doesn't support != operator, so we use IN with valid types.
     *
     * @param string $entity The QuickBooks entity name
     * @return string The WHERE clause (without the WHERE keyword) or empty string
     */
    private function buildEntityWhereClause(string $entity): string
    {
        if ($entity === 'Item') {
            // Only include item types that can be used as line items on invoices/estimates
            // Valid types: Service, NonInventory, Inventory
            // Excluded types: Category, Group, Bundle (not universally supported)
            // See: https://developer.intuit.com/app/developer/qbo/docs/api/accounting/all-entities/item
            return "Type IN ('Service', 'NonInventory', 'Inventory')";
        }

        return '';
    }

    private function rateLimiter(): ?QuickbooksRateLimiter
    {
        $realm = $this->company->quickbooks->realmID ?? null;

        if (! $realm) {
            return null;
        }

        return $this->rate_limiter ??= new QuickbooksRateLimiter($realm);
    }

    private function execute(callable $callback): mixed
    {
        if ($this->token_manager->tokenNeedsRefresh()) {
            $this->token_manager->refreshIfNeeded();
        }

        $limiter = $this->rateLimiter();
        $request_token = null;

        if ($limiter) {
            if (! $limiter->waitForCapacity(self::RATE_LIMIT_MAX_WAIT_SECONDS)) {
                throw new \RuntimeException('QuickBooks rate limit: capacity unavailable after wait');
            }

            $request_token = $limiter->acquireRequest();
            $limiter->trackRequest();
        }

        try {
            return $callback();
        } catch (\Throwable $e) {

            if ($limiter && QuickbooksRateLimiter::isRateLimitException($e)) {
                $limiter->enterBackoff(60);

                if ($limiter->waitForCapacity(self::RATE_LIMIT_MAX_WAIT_SECONDS)) {
                    return $callback();
                }

                throw $e;
            }

            if (! $this->token_manager->isAuthenticationFailure($e)) {
                throw $e;
            }

            $this->token_manager->refreshIfNeeded(true);

            return $callback();
        } finally {
            if ($limiter && $request_token) {
                $limiter->releaseRequest($request_token);
            }
        }
    }

}
