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

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\WorkflowRun
 *
 * @property int $id
 * @property int $workflow_id
 * @property int $company_id
 * @property int $user_id
 * @property string $workflowable_type
 * @property int $workflowable_id
 * @property string|null $current_step_id
 * @property string $status
 * @property string|null $waiting_for
 * @property \Carbon\Carbon|null $waiting_since
 * @property \Carbon\Carbon|null $wait_until
 * @property array|null $workflow_steps
 * @property array|null $context
 * @property array|null $step_history
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $completed_at
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property-read \App\Models\Workflow $workflow
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $user
 * @property-read \App\Models\BaseModel $workflowable
 * @mixin \Eloquent
 */
class WorkflowRun extends BaseModel
{
    use Filterable;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_TIMED_OUT = 'timed_out';

    protected $fillable = [
        'workflowable_type',
        'workflowable_id',
        'current_step_id',
        'status',
        'waiting_for',
        'waiting_since',
        'wait_until',
        'workflow_steps',
        'context',
        'step_history',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'workflow_steps' => 'array',
        'context' => 'array',
        'step_history' => 'array',
        'waiting_since' => 'datetime',
        'wait_until' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function getEntityType()
    {
        return self::class;
    }

    public function workflowable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Find a step by ID from the snapshotted workflow steps.
     */
    public function findStep(string $stepId): ?array
    {
        foreach ($this->workflow_steps ?? [] as $step) {
            if ($step['id'] === $stepId) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Get the first step from the snapshotted workflow steps.
     */
    public function firstStep(): ?array
    {
        return ($this->workflow_steps ?? [])[0] ?? null;
    }

    /**
     * Get the next step after the given step ID from the snapshot.
     */
    public function nextStep(string $currentStepId): ?array
    {
        $currentStep = $this->findStep($currentStepId);

        if (! $currentStep) {
            return null;
        }

        // Explicit next reference
        if (! empty($currentStep['next'])) {
            return $this->findStep($currentStep['next']);
        }

        // Sequential: find index and return next
        $steps = $this->workflow_steps ?? [];
        foreach ($steps as $index => $step) {
            if ($step['id'] === $currentStepId && isset($steps[$index + 1])) {
                return $steps[$index + 1];
            }
        }

        return null;
    }

    /**
     * Check if the run is still active (not in a terminal state).
     */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_WAITING]);
    }

    /**
     * Check if the run is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_TIMED_OUT]);
    }

    /**
     * Append a step history entry.
     */
    public function logStep(array $step, string $status, ?array $result = null, ?string $error = null): void
    {
        $history = $this->step_history ?? [];

        $history[] = [
            'step_id' => $step['id'],
            'step_name' => $step['name'] ?? $step['id'],
            'step_type' => $step['type'],
            'status' => $status,
            'started_at' => now()->timestamp,
            'completed_at' => $status !== 'started' ? now()->timestamp : null,
            'result' => $result,
            'error' => $error,
        ];

        $this->step_history = $history;
        $this->saveQuietly();
    }

    /**
     * Update context with new key-value pairs.
     */
    public function mergeContext(array $data): void
    {
        $context = $this->context ?? [];
        $this->context = array_merge($context, $data);
        $this->saveQuietly();
    }

    public function translate_entity(): string
    {
        return ctrans('texts.workflow_run');
    }
}
