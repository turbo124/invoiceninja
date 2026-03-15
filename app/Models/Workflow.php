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

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Workflow
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property string $trigger_entity
 * @property string $trigger_event
 * @property array|null $trigger_conditions
 * @property array $steps
 * @property bool $is_active
 * @property bool $is_deleted
 * @property bool $is_template
 * @property int $runs_count
 * @property \Carbon\Carbon|null $last_run_at
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WorkflowRun> $runs
 * @mixin \Eloquent
 */
class Workflow extends BaseModel
{
    use SoftDeletes;
    use Filterable;

    protected $fillable = [
        'name',
        'description',
        'trigger_entity',
        'trigger_event',
        'trigger_conditions',
        'steps',
        'is_active',
        'is_template',
    ];

    protected $casts = [
        'trigger_conditions' => 'array',
        'steps' => 'array',
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'is_template' => 'boolean',
        'last_run_at' => 'datetime',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    public function getEntityType()
    {
        return self::class;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }

    public function activeRuns(): HasMany
    {
        return $this->hasMany(WorkflowRun::class)->whereIn('status', ['active', 'waiting']);
    }

    public function translate_entity(): string
    {
        return ctrans('texts.workflow');
    }

    /**
     * Find a step definition by its ID.
     */
    public function findStep(string $stepId): ?array
    {
        foreach ($this->steps as $step) {
            if ($step['id'] === $stepId) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Get the first step in the workflow.
     */
    public function firstStep(): ?array
    {
        return $this->steps[0] ?? null;
    }

    /**
     * Get the next step after the given step ID.
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
        foreach ($this->steps as $index => $step) {
            if ($step['id'] === $currentStepId && isset($this->steps[$index + 1])) {
                return $this->steps[$index + 1];
            }
        }

        return null;
    }
}
