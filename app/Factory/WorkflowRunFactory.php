<?php

namespace App\Factory;

use App\Models\BaseModel;
use App\Models\WorkflowRun;
use App\Models\Workflow;

class WorkflowRunFactory
{
    public static function create(int $company_id, int $user_id, Workflow $workflow, BaseModel $entity): WorkflowRun
    {
        $run = new WorkflowRun();
        $run->company_id = $company_id;
        $run->user_id = $user_id;
        $run->workflow_id = $workflow->id;
        $run->workflowable_type = get_class($entity);
        $run->workflowable_id = $entity->id;
        $run->workflow_steps = $workflow->steps;
        $run->current_step_id = $workflow->firstStep()['id'] ?? null;
        $run->status = WorkflowRun::STATUS_ACTIVE;
        $run->context = [];
        $run->step_history = [];

        return $run;
    }
}
