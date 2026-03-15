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

namespace App\Http\Controllers;

use App\Filters\WorkflowRunFilters;
use App\Models\WorkflowRun;
use App\Services\Workflow\WorkflowEngine;
use App\Transformers\WorkflowRunTransformer;
use App\Utils\Traits\MakesHash;

class WorkflowRunController extends BaseController
{
    use MakesHash;

    protected $entity_type = WorkflowRun::class;

    protected $entity_transformer = WorkflowRunTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    public function index(WorkflowRunFilters $filters)
    {
        $runs = WorkflowRun::filter($filters);

        return $this->listResponse($runs);
    }

    public function show(WorkflowRun $workflow_run)
    {
        return $this->itemResponse($workflow_run);
    }

    public function cancel(WorkflowRun $workflow_run)
    {
        $engine = new WorkflowEngine();

        try {
            $engine->cancelRun($workflow_run);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->itemResponse($workflow_run->fresh());
    }

    public function advance(WorkflowRun $workflow_run)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $engine = new WorkflowEngine();

        try {
            $engine->manualAdvance($workflow_run, $user->company());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->itemResponse($workflow_run->fresh());
    }

    public function retry(WorkflowRun $workflow_run)
    {
        if ($workflow_run->status !== WorkflowRun::STATUS_FAILED) {
            return response()->json(['message' => 'Can only retry failed workflow runs'], 422);
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $workflow_run->update([
            'status' => WorkflowRun::STATUS_ACTIVE,
            'error_message' => null,
        ]);

        $engine = new WorkflowEngine();
        $engine->advanceRun($workflow_run, $user->company());

        return $this->itemResponse($workflow_run->fresh());
    }
}
