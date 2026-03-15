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

namespace App\Transformers;

use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Utils\Traits\MakesHash;

class WorkflowRunTransformer extends EntityTransformer
{
    use MakesHash;

    protected array $defaultIncludes = [];

    protected array $availableIncludes = [
        'workflow',
    ];

    public function includeWorkflow(WorkflowRun $run)
    {
        $transformer = new WorkflowTransformer($this->serializer);

        if (! $run->workflow) {
            return null;
        }

        return $this->includeItem($run->workflow, $transformer, Workflow::class);
    }

    public function transform(WorkflowRun $run)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($run->id),
            'workflow_id' => (string) $this->encodePrimaryKey($run->workflow_id),
            'user_id' => (string) $this->encodePrimaryKey($run->user_id),
            'entity_type' => (string) class_basename($run->entity_type),
            'entity_id' => (string) $this->encodePrimaryKey($run->entity_id),
            'entity_hashed_id' => (string) $this->encodePrimaryKey($run->entity_id),
            'current_step_id' => (string) $run->current_step_id ?: '',
            'status' => (string) $run->status,
            'waiting_for' => (string) $run->waiting_for ?: '',
            'waiting_since' => $run->waiting_since ? (int) $run->waiting_since->timestamp : null,
            'wait_until' => $run->wait_until ? (int) $run->wait_until->timestamp : null,
            'context' => (array) ($run->context ?? []),
            'step_history' => (array) ($run->step_history ?? []),
            'error_message' => (string) $run->error_message ?: '',
            'completed_at' => $run->completed_at ? (int) $run->completed_at->timestamp : null,
            'created_at' => (int) $run->created_at,
            'updated_at' => (int) $run->updated_at,
        ];
    }
}
