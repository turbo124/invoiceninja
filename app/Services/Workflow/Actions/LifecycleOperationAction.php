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
use App\Repositories\BaseRepository;
use App\Services\Workflow\ContextResolver;
use App\Services\Workflow\OperationFailureType;
use App\Services\Workflow\OperationRegistry;
use App\Services\Workflow\WorkflowOperationException;
use Illuminate\Support\Str;

class LifecycleOperationAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $entity = ContextResolver::resolveEntity($params['entity_ref'] ?? '$trigger', $context, $run);
        if (! $entity) {
            throw new WorkflowOperationException(
                "Cannot resolve entity: " . ($params['entity_ref'] ?? '$trigger'),
                OperationFailureType::PERMANENT
            );
        }

        $operation = $params['operation'] ?? '';
        $registry = OperationRegistry::getLifecycle($operation);
        if (! $registry) {
            throw new WorkflowOperationException(
                "Unknown lifecycle operation: '{$operation}'",
                OperationFailureType::PERMANENT
            );
        }

        $repo = new BaseRepository();

        match ($operation) {
            'archive' => $repo->archive($entity),
            'restore' => $repo->restore($entity),
            'delete' => $repo->delete($entity),
            default => throw new WorkflowOperationException(
                "Unsupported lifecycle operation: '{$operation}'",
                OperationFailureType::PERMANENT
            ),
        };

        return [
            'action' => 'lifecycle_operation',
            'operation' => $operation,
            'entity_type' => Str::snake(class_basename($entity)),
            'entity_id' => $entity->id,
        ];
    }
}
