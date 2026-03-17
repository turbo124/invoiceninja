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

namespace App\Services\Workflow\Actions;

use App\Models\Company;
use App\Models\WorkflowRun;
use App\Services\Workflow\ContextResolver;
use App\Services\Workflow\OperationFailureType;
use App\Services\Workflow\OperationRegistry;
use App\Services\Workflow\WorkflowOperationException;
use Illuminate\Support\Str;

class EntityOperationAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        // 1. Resolve
        $entity = ContextResolver::resolveEntity($params['entity_ref'] ?? '$trigger', $context, $run);
        if (! $entity) {
            throw new WorkflowOperationException(
                "Cannot resolve entity: " . ($params['entity_ref'] ?? '$trigger'),
                OperationFailureType::PERMANENT
            );
        }

        // 2. Lookup
        $entityKey = Str::snake(class_basename($entity));
        $operation = $params['operation'] ?? '';
        $registry = OperationRegistry::get($entityKey, $operation);
        if (! $registry) {
            throw new WorkflowOperationException(
                "Unknown operation '{$operation}' for entity '{$entityKey}'",
                OperationFailureType::PERMANENT
            );
        }

        // 3. Guard
        if (! empty($registry['guard']) && ! $this->guardPasses($entity, $registry['guard'])) {
            throw new WorkflowOperationException(
                "Guard failed: {$registry['guard'][0]} {$registry['guard'][1]} " . $this->formatGuardValue($registry['guard'][2]),
                OperationFailureType::GUARD_FAILED
            );
        }

        // 4. Snapshot
        $preState = $this->capturePreState($entity, $registry['assert'] ?? null);

        // 5. Pre-call
        if (! empty($registry['pre_call'])) {
            $entity = $entity->service()->{$registry['pre_call']}()->save();
        }

        // 6. Execute
        $method = $registry['method'];
        try {
            $args = $this->resolveArgs($registry, $params['args'] ?? []);
            $entity = $entity->service()->{$method}(...$args)->save();
        } catch (WorkflowOperationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Classify unknown exceptions from service calls
            $failureType = $this->classifyException($e);
            throw new WorkflowOperationException(
                "Operation '{$operation}' failed: {$e->getMessage()}",
                $failureType,
                $e
            );
        }

        // 7. Assert
        if (! empty($registry['assert']) && ! $this->assertPasses($entity, $registry['assert'], $preState)) {
            throw new WorkflowOperationException(
                "Assertion failed after '{$operation}': expected {$registry['assert'][0]} "
                . "{$registry['assert'][1]} {$registry['assert'][2]}, "
                . "got {$entity->{$registry['assert'][0]}}",
                OperationFailureType::ASSERTION_FAILED
            );
        }

        // 8. Result
        return [
            'action' => 'entity_operation',
            'operation' => $operation,
            'entity_type' => $entityKey,
            'entity_id' => $entity->id,
        ];
    }

    private function guardPasses($entity, array $guard): bool
    {
        [$field, $operator, $value] = $guard;

        $actual = $entity->{$field};

        return match ($operator) {
            '=' => $actual == $value,
            '!=' => $actual != $value,
            '>' => $actual > $value,
            '>=' => $actual >= $value,
            '<' => $actual < $value,
            '<=' => $actual <= $value,
            'in' => is_array($value) && in_array($actual, $value),
            default => false,
        };
    }

    private function capturePreState($entity, ?array $assert): array
    {
        if (! $assert) {
            return [];
        }

        $state = [];
        $field = $assert[0];
        $state[$field] = $entity->{$field};

        // Also capture any $pre.field references in the value
        if (is_string($assert[2]) && str_starts_with($assert[2], '$pre.')) {
            $preField = substr($assert[2], 5);
            $state[$preField] = $entity->{$preField};
        }

        return $state;
    }

    private function assertPasses($entity, array $assert, array $preState): bool
    {
        [$field, $operator, $value] = $assert;

        $actual = $entity->{$field};

        // Resolve $pre.field references
        if (is_string($value) && str_starts_with($value, '$pre.')) {
            $preField = substr($value, 5);
            $value = $preState[$preField] ?? null;
        }

        return match ($operator) {
            '=' => $actual == $value,
            '!=' => $actual != $value,
            '>' => $actual > $value,
            '>=' => $actual >= $value,
            '<' => $actual < $value,
            '<=' => $actual <= $value,
            default => false,
        };
    }

    private function resolveArgs(array $registry, array $providedArgs): array
    {
        if (empty($registry['args'])) {
            return [];
        }

        $resolved = [];
        foreach ($registry['args'] as $name => $schema) {
            if (isset($providedArgs[$name])) {
                $resolved[] = $providedArgs[$name];
            } elseif (! empty($schema['required'])) {
                throw new WorkflowOperationException(
                    "Required argument '{$name}' not provided",
                    OperationFailureType::PERMANENT
                );
            }
        }

        return $resolved;
    }

    private function classifyException(\Throwable $e): OperationFailureType
    {
        $message = strtolower($e->getMessage());

        // Network/gateway errors are transient
        if (str_contains($message, 'timeout') ||
            str_contains($message, 'connection') ||
            str_contains($message, 'gateway') ||
            str_contains($message, 'rate limit') ||
            str_contains($message, '429') ||
            str_contains($message, '503')) {
            return OperationFailureType::TRANSIENT;
        }

        return OperationFailureType::PERMANENT;
    }

    private function formatGuardValue(mixed $value): string
    {
        if (is_array($value)) {
            return '[' . implode(', ', $value) . ']';
        }

        return (string) $value;
    }
}
