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
use App\Services\Workflow\WorkflowOperationException;
use Illuminate\Support\Str;

class CloneAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $entity = ContextResolver::resolveEntity($params['entity_ref'] ?? '$trigger', $context, $run);
        if (! $entity) {
            throw new WorkflowOperationException(
                'Cannot resolve entity for clone',
                OperationFailureType::PERMANENT
            );
        }

        $targetType = $params['target_type'] ?? Str::snake(class_basename($entity));

        $cloned = $entity->service()->clone()->save();

        return [
            'action' => 'clone_to',
            'source_type' => Str::snake(class_basename($entity)),
            'target_type' => $targetType,
            'entity_type' => Str::snake(class_basename($cloned)),
            'entity_id' => $cloned->id,
        ];
    }
}
