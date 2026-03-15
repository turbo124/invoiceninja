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

class UpdateFieldAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $entity = ContextResolver::resolveEntity($params['entity_ref'] ?? '$trigger', $context, $run);

        if (! $entity) {
            throw new \RuntimeException("Cannot resolve entity for field update");
        }

        $field = $params['field'] ?? '';
        $value = $params['value'] ?? null;

        if (empty($field)) {
            throw new \RuntimeException("No field specified for update");
        }

        // Only allow updating fillable fields
        if (! in_array($field, $entity->getFillable())) {
            throw new \RuntimeException("Field '{$field}' is not updateable on " . class_basename($entity));
        }

        $oldValue = $entity->{$field};
        $entity->{$field} = $value;
        $entity->saveQuietly();

        return [
            'action' => 'update_field',
            'entity_type' => class_basename($entity),
            'entity_id' => $entity->id,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $value,
        ];
    }
}
