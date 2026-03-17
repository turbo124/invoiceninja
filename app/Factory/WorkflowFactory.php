<?php

namespace App\Factory;

use App\Models\Workflow;

class WorkflowFactory
{
    public static function create(int $company_id, int $user_id): Workflow
    {
        $workflow = new Workflow();
        $workflow->company_id = $company_id;
        $workflow->user_id = $user_id;
        $workflow->name = '';
        $workflow->steps = [];
        $workflow->trigger_entity = '';
        $workflow->trigger_event = '';
        $workflow->is_deleted = false;
        $workflow->is_template = false;

        return $workflow;
    }
}
