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

class WorkflowTransformer extends EntityTransformer
{
    use MakesHash;

    protected array $defaultIncludes = [];

    protected array $availableIncludes = [
        'runs',
    ];

    public function includeRuns(Workflow $workflow)
    {
        $transformer = new WorkflowRunTransformer($this->serializer);

        return $this->includeCollection($workflow->runs()->latest()->limit(50)->get(), $transformer, WorkflowRun::class);
    }

    public function transform(Workflow $workflow)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($workflow->id),
            'user_id' => (string) $this->encodePrimaryKey($workflow->user_id),
            'name' => (string) $workflow->name,
            'description' => (string) $workflow->description ?: '',
            'trigger_entity' => (string) $workflow->trigger_entity,
            'trigger_event' => (string) $workflow->trigger_event,
            'trigger_conditions' => (array) ($workflow->trigger_conditions ?? []),
            'steps' => (array) ($workflow->steps ?? []),
            'is_active' => (bool) $workflow->is_active,
            'is_deleted' => (bool) $workflow->is_deleted,
            'is_template' => (bool) $workflow->is_template,
            'runs_count' => (int) $workflow->runs_count,
            'last_run_at' => $workflow->last_run_at ? (int) $workflow->last_run_at->timestamp : null,
            'created_at' => (int) $workflow->created_at,
            'updated_at' => (int) $workflow->updated_at,
            'archived_at' => (int) $workflow->deleted_at,
        ];
    }
}
