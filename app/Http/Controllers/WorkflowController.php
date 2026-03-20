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

use App\Factory\WorkflowFactory;
use App\Filters\WorkflowFilters;
use App\Http\Requests\Workflow\BulkWorkflowRequest;
use App\Http\Requests\Workflow\DestroyWorkflowRequest;
use App\Http\Requests\Workflow\ShowWorkflowRequest;
use App\Http\Requests\Workflow\StoreWorkflowRequest;
use App\Http\Requests\Workflow\UpdateWorkflowRequest;
use App\Models\Workflow;
use App\Repositories\WorkflowRepository;
use App\Services\Workflow\OperationRegistry;
use App\Services\Workflow\WorkflowMetadata;
use App\Transformers\WorkflowTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;

class WorkflowController extends BaseController
{
    use MakesHash;

    protected $entity_type = Workflow::class;

    protected $entity_transformer = WorkflowTransformer::class;

    protected WorkflowRepository $workflow_repo;

    public function __construct(WorkflowRepository $workflow_repo)
    {
        parent::__construct();

        $this->workflow_repo = $workflow_repo;
    }

    public function index(WorkflowFilters $filters)
    {
        $workflows = Workflow::filter($filters);

        return $this->listResponse($workflows);
    }

    public function show(ShowWorkflowRequest $request, Workflow $workflow)
    {
        return $this->itemResponse($workflow);
    }

    public function store(StoreWorkflowRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $workflow = WorkflowFactory::create($user->company()->id, $user->id);
        $workflow = $this->workflow_repo->save($request->all(), $workflow);

        return $this->itemResponse($workflow->fresh());
    }

    public function update(UpdateWorkflowRequest $request, Workflow $workflow)
    {
        $workflow = $this->workflow_repo->save($request->all(), $workflow);

        return $this->itemResponse($workflow->fresh());
    }

    public function destroy(DestroyWorkflowRequest $request, Workflow $workflow)
    {
        $this->workflow_repo->delete($workflow);

        return $this->itemResponse($workflow->fresh());
    }

    public function bulk(BulkWorkflowRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $action = $request->input('action');
        $ids = $request->input('ids');
        $workflows = Workflow::withTrashed()->find($this->transformKeys($ids));

        if ($action === 'clone') {
            $clones = collect();

            $workflows->each(function ($workflow) use ($user, $clones) {
                if ($user->can('create', Workflow::class)) {
                    $clone = $workflow->replicate(['is_deleted', 'deleted_at']);
                    $clone->company_id = $user->company()->id;
                    $clone->user_id = $user->id;
                    $clone->name = $workflow->name . ' (Copy)';
                    $clone->is_template = false;
                    $clone->save();
                    $clones->push($clone);
                }
            });

            return $this->listResponse(Workflow::whereIn('id', $clones->pluck('id')));
        }

        $workflows->each(function ($workflow) use ($action, $user) {
            if ($user->can('edit', $workflow)) {
                match ($action) {
                    'archive' => $this->workflow_repo->archive($workflow),
                    'restore' => $this->workflow_repo->restore($workflow),
                    'delete' => $this->workflow_repo->delete($workflow),
                    'cancel_runs' => $this->workflow_repo->cancelRuns($workflow),
                };
            }
        });

        return $this->listResponse(Workflow::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }

    public function templates()
    {
        $templates = Workflow::where('is_template', true)
            ->where('is_deleted', false)
            ->get();

        // Also include built-in templates from WorkflowMetadata
        $builtInTemplates = WorkflowMetadata::templates();

        return response()->json(['data' => array_merge(
            (new WorkflowTransformer())->transformCollection($templates),
            $builtInTemplates
        )]);
    }

    public function createFromTemplate()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $templateId = request()->input('template_id');
        $templateKey = request()->input('template_key');

        if ($templateId) {
            $template = Workflow::findOrFail($this->decodePrimaryKey($templateId));
            $workflow = $template->replicate();
            $workflow->company_id = $user->company()->id;
            $workflow->user_id = $user->id;
            $workflow->is_template = false;
            $workflow->save();
        } elseif ($templateKey) {
            $templateData = WorkflowMetadata::getTemplate($templateKey);
            if (! $templateData) {
                return response()->json(['message' => 'Template not found'], 404);
            }

            $workflow = WorkflowFactory::create($user->company()->id, $user->id);
            $workflow->fill($templateData);
            $workflow->is_template = false;
            $workflow->save();
        } else {
            return response()->json(['message' => 'template_id or template_key required'], 422);
        }

        return $this->itemResponse($workflow->fresh());
    }

    public function triggers()
    {
        return response()->json(['data' => WorkflowMetadata::triggers()]);
    }

    public function actions()
    {
        $entity = request()->query('entity');

        $actions = $entity
            ? WorkflowMetadata::actionsForEntity($entity)
            : WorkflowMetadata::actions();

        return response()->json(['data' => $actions]);
    }

    public function fields()
    {
        $company = auth()->user()->company();

        return response()->json([
            'data' => WorkflowMetadata::fields($company),
            'condition_groups' => WorkflowMetadata::conditionGroupMeta(),
        ]);
    }

    public function dateFields()
    {
        return response()->json(['data' => WorkflowMetadata::dateFields()]);
    }

    public function operations()
    {
        $allOps = OperationRegistry::operations();
        $result = [];

        foreach ($allOps as $entityType => $operations) {
            $result[$entityType] = OperationRegistry::forEntity($entityType);
        }

        return response()->json(['data' => $result]);
    }

}
