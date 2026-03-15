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

/**
 * App\Models\WorkflowRun
 *
 * @property int $id
 * @property int $workflow_id
 * @property int $company_id
 * @property int $user_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string|null $current_step_id
 * @property string $status
 * @property string|null $waiting_for
 * @property \Carbon\Carbon|null $waiting_since
 * @property \Carbon\Carbon|null $wait_until
 * @property array|null $context
 * @property array|null $step_history
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $completed_at
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property-read \App\Models\Workflow $workflow
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $user
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
        'workflow_id',
        'company_id',
        'user_id',
        'entity_type',
        'entity_id',
        'current_step_id',
        'status',
        'waiting_for',
        'waiting_since',
        'wait_until',
        'context',
        'step_history',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
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
     * Get the entity this run is associated with.
     */
    public function entity(): ?BaseModel
    {
        if (! class_exists($this->entity_type)) {
            return null;
        }

        return $this->entity_type::withTrashed()->find($this->entity_id);
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
