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

use App\Factory\TaskFactory;
use App\Models\Company;
use App\Models\WorkflowRun;
use App\Repositories\TaskRepository;
use App\Services\Workflow\ContextResolver;
use App\Services\Workflow\TemplateVariableResolver;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;

class CreateTaskAction implements WorkflowActionInterface
{
    use MakesHash;

    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $description = TemplateVariableResolver::resolve($params['description'] ?? '', $context, $run);

        $task = TaskFactory::create($company->id, $run->user_id);
        $task->description = $description;

        // Optional: assign to project
        if (! empty($params['project_ref'])) {
            $project = ContextResolver::resolveEntity($params['project_ref'], $context, $run);
            if ($project) {
                $task->project_id = $project->id;
                $task->client_id = $project->client_id;
            }
        }

        // Optional: assign user
        if (! empty($params['assigned_user_id'])) {
            $task->assigned_user_id = $this->decodePrimaryKey($params['assigned_user_id']);
        }

        // Optional: set status
        if (! empty($params['status_id'])) {
            $task->status_id = $this->decodePrimaryKey($params['status_id']);
        } else {
            $firstStatus = $company->task_statuses()->whereNull('deleted_at')->orderBy('id', 'asc')->first();
            if ($firstStatus) {
                $task->status_id = $firstStatus->id;
            }
        }

        // Optional: due date offset
        if (isset($params['due_date_offset']) && is_numeric($params['due_date_offset'])) {
            $task->due_date = Carbon::now()->addDays((int) $params['due_date_offset'])->format('Y-m-d');
        }

        // Optional: priority
        if (isset($params['priority_id'])) {
            $task->priority_id = (int) $params['priority_id'];
        }

        $taskRepo = new TaskRepository();
        $taskRepo->save([], $task);

        return [
            'action' => 'create_task',
            'entity_type' => 'Task',
            'entity_id' => $task->id,
        ];
    }
}
