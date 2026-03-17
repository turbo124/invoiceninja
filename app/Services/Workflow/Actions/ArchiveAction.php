<?php

namespace App\Services\Workflow\Actions;

use App\Models\Company;
use App\Models\WorkflowRun;
use App\Repositories\BaseRepository;
use App\Services\Workflow\OperationFailureType;
use App\Services\Workflow\WorkflowOperationException;
use Illuminate\Support\Str;

class ArchiveAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $entity = $run->workflowable;

        if (! $entity) {
            throw new WorkflowOperationException(
                'Cannot resolve trigger entity for archive',
                OperationFailureType::PERMANENT
            );
        }

        (new BaseRepository())->archive($entity);

        return [
            'action' => 'archive',
            'entity_type' => Str::snake(class_basename($entity)),
            'entity_id' => $entity->id,
        ];
    }
}
