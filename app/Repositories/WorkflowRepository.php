<?php

namespace App\Repositories;

use App\Models\Workflow;

class WorkflowRepository extends BaseRepository
{
    /**
     * Save (create or update) a workflow from validated request data.
     */
    public function save(array $data, Workflow $workflow): Workflow
    {
        $workflow->fill($data);
        $workflow->save();

        return $workflow;
    }

    /**
     * Soft-delete a workflow via the base repository.
     */
    public function delete($workflow): Workflow
    {
        parent::delete($workflow);

        return $workflow;
    }

    /**
     * Cancel all active/waiting runs for a workflow.
     */
    public function cancelRuns(Workflow $workflow): Workflow
    {
        $workflow->activeRuns()->delete();

        return $workflow;
    }
}
