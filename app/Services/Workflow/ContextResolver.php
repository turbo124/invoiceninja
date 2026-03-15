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

namespace App\Services\Workflow;

use App\Models\BaseModel;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\WorkflowRun;

class ContextResolver
{
    /**
     * Map of context keys to model classes.
     */
    private static array $entityMap = [
        'invoice' => Invoice::class,
        'quote' => Quote::class,
        'credit' => Credit::class,
        'client' => Client::class,
        'project' => Project::class,
        'task' => Task::class,
        'expense' => Expense::class,
    ];

    /**
     * Resolve an entity from a $reference string like "$quote" or "$trigger".
     */
    public static function resolveEntity(string $ref, array $context, WorkflowRun $run): ?BaseModel
    {
        $ref = ltrim($ref, '$');

        if ($ref === 'trigger') {
            return $run->entity();
        }

        return self::resolveEntityByKey($ref, $context, $run);
    }

    /**
     * Resolve an entity by its context key (e.g., "quote", "invoice").
     */
    public static function resolveEntityByKey(string $key, array $context, WorkflowRun $run): ?BaseModel
    {
        $entityId = $context[$key] ?? null;

        if (! $entityId) {
            return null;
        }

        $modelClass = self::$entityMap[$key] ?? null;

        if (! $modelClass) {
            // Try to infer from the trigger entity type
            return null;
        }

        return $modelClass::withTrashed()->find($entityId);
    }

    /**
     * Resolve a field value from a dotted reference like "$quote.status_id" or "$client.name".
     */
    public static function resolveField(string $fieldRef, array $context, WorkflowRun $run): mixed
    {
        $fieldRef = ltrim($fieldRef, '$');
        $parts = explode('.', $fieldRef, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$entityKey, $fieldName] = $parts;

        $entity = self::resolveEntity('$' . $entityKey, $context, $run);

        if (! $entity) {
            return null;
        }

        return self::getFieldValue($entity, $fieldName);
    }

    /**
     * Get a field value from an entity, including computed fields.
     */
    private static function getFieldValue(BaseModel $entity, string $field): mixed
    {
        return match ($field) {
            'days_overdue' => self::daysOverdue($entity),
            'budget_utilization_pct' => self::budgetUtilization($entity),
            'days_until_expiry' => self::daysUntilExpiry($entity),
            default => $entity->{$field} ?? null,
        };
    }

    private static function daysOverdue(BaseModel $entity): int
    {
        if (! isset($entity->due_date) || ! $entity->due_date) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($entity->due_date, false) * -1);
    }

    private static function budgetUtilization(BaseModel $entity): float
    {
        if (! isset($entity->budgeted_hours) || $entity->budgeted_hours <= 0) {
            return 0;
        }

        return round(($entity->current_hours / $entity->budgeted_hours) * 100, 1);
    }

    private static function daysUntilExpiry(BaseModel $entity): int
    {
        if (! isset($entity->due_date) || ! $entity->due_date) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($entity->due_date, false));
    }
}
