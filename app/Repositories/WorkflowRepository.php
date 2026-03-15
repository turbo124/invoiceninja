<?php

namespace App\Repositories;

use App\Models\Workflow;

class WorkflowRepository extends BaseRepository
{
    public function save(array $data, Workflow $workflow): Workflow
    {
        $workflow->fill($data);
        $workflow->save();

        return $workflow;
    }
}
