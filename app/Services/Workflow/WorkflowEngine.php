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

namespace App\Services\Workflow;

use App\Models\BaseModel;
use App\Models\Company;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Services\Workflow\Actions\AssignUserAction;
use App\Services\Workflow\Actions\ConvertAction;
use App\Services\Workflow\Actions\CreateTaskAction;
use App\Services\Workflow\Actions\NotifyUserAction;
use App\Services\Workflow\Actions\SendEmailAction;
use App\Services\Workflow\Actions\SendWebhookAction;
use App\Services\Workflow\Actions\UpdateFieldAction;
use App\Services\Workflow\Actions\WorkflowActionInterface;
use Illuminate\Support\Str;

class WorkflowEngine
{
    /**
     * Map of action type keys to handler classes.
     */
    private static array $actionHandlers = [
        'send_email' => SendEmailAction::class,
        'convert' => ConvertAction::class,
        'assign_user' => AssignUserAction::class,
        'update_field' => UpdateFieldAction::class,
        'create_task' => CreateTaskAction::class,
        'notify_user' => NotifyUserAction::class,
        'send_webhook' => SendWebhookAction::class,
    ];

    /**
     * Called when a trigger event fires. Starts new workflow runs
     * and resumes any waiting runs that match this event.
     */
    public function onEvent(string $entityType, string $event, BaseModel $entity, Company $company): void
    {
        // 1. Find workflows matching this trigger and start new runs
        $workflows = Workflow::query()
            ->where('company_id', $company->id)
            ->where('trigger_entity', $entityType)
            ->where('trigger_event', $event)
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->where('is_template', false)
            ->get();

        foreach ($workflows as $workflow) {
            if ($this->triggerConditionsMatch($workflow, $entity)) {
                $this->startRun($workflow, $entity, $company);
            }
        }

        // 2. Resume any waiting runs that match this event
        $eventPattern = $entityType . '.' . $event;

        $waitingRuns = WorkflowRun::query()
            ->where('company_id', $company->id)
            ->where('status', WorkflowRun::STATUS_WAITING)
            ->where(function ($q) use ($eventPattern) {
                $q->where('waiting_for', $eventPattern)
                  ->orWhere('waiting_for', 'LIKE', "%{$eventPattern}%");
            })
            ->get();

        foreach ($waitingRuns as $run) {
            if ($this->eventMatchesWaitingRun($run, $entity, $entityType, $event)) {
                $this->resumeRun($run, $company);
            }
        }
    }

    /**
     * Start a new workflow run from a trigger.
     */
    public function startRun(Workflow $workflow, BaseModel $entity, Company $company): WorkflowRun
    {
        $entityKey = $this->entityKey($entity);

        $run = WorkflowRun::create([
            'workflow_id' => $workflow->id,
            'company_id' => $company->id,
            'user_id' => $entity->user_id ?? $company->owner()->id,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->id,
            'current_step_id' => $workflow->firstStep()['id'] ?? null,
            'status' => WorkflowRun::STATUS_ACTIVE,
            'context' => ['trigger' => $entity->id, $entityKey => $entity->id],
            'step_history' => [],
        ]);

        $workflow->increment('runs_count');
        $workflow->update(['last_run_at' => now()]);

        $this->advanceRun($run, $company);

        return $run;
    }

    /**
     * Core execution loop: advance through steps until a wait or end.
     */
    public function advanceRun(WorkflowRun $run, Company $company): void
    {
        $workflow = $run->workflow;
        $maxSteps = 50; // Safety limit to prevent infinite loops
        $stepsExecuted = 0;

        while ($run->status === WorkflowRun::STATUS_ACTIVE && $stepsExecuted < $maxSteps) {
            $step = $workflow->findStep($run->current_step_id);

            if (! $step) {
                $run->update([
                    'status' => WorkflowRun::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
                break;
            }

            try {
                match ($step['type']) {
                    'action' => $this->executeAction($run, $step, $company),
                    'wait_for_event' => $this->enterWait($run, $step),
                    'wait_delay' => $this->enterDelay($run, $step),
                    'branch' => $this->evaluateBranch($run, $step),
                    'end' => $this->endRun($run, $step),
                    default => throw new \RuntimeException("Unknown step type: {$step['type']}"),
                };
            } catch (\Throwable $e) {
                $run->logStep($step, 'failed', null, $e->getMessage());
                $run->update([
                    'status' => WorkflowRun::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
                nlog("Workflow run {$run->id} failed at step {$step['id']}: {$e->getMessage()}");
                break;
            }

            $stepsExecuted++;
        }

        if ($stepsExecuted >= $maxSteps && $run->status === WorkflowRun::STATUS_ACTIVE) {
            $run->update([
                'status' => WorkflowRun::STATUS_FAILED,
                'error_message' => 'Maximum step execution limit reached (possible infinite loop)',
            ]);
        }
    }

    /**
     * Resume a waiting run after its event fires.
     */
    public function resumeRun(WorkflowRun $run, Company $company): void
    {
        $workflow = $run->workflow;
        $currentStep = $workflow->findStep($run->current_step_id);

        if ($currentStep) {
            $run->logStep($currentStep, 'completed', ['event_received' => $run->waiting_for]);
        }

        $nextStep = $workflow->nextStep($run->current_step_id);

        $run->update([
            'status' => WorkflowRun::STATUS_ACTIVE,
            'current_step_id' => $nextStep['id'] ?? null,
            'waiting_for' => null,
            'waiting_since' => null,
        ]);

        if ($nextStep) {
            $this->advanceRun($run, $company);
        } else {
            $run->update([
                'status' => WorkflowRun::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Manually advance a run past a wait step (admin action).
     */
    public function manualAdvance(WorkflowRun $run, Company $company): void
    {
        if ($run->status !== WorkflowRun::STATUS_WAITING) {
            throw new \RuntimeException('Can only advance a waiting workflow run');
        }

        $this->resumeRun($run, $company);
    }

    /**
     * Cancel an active/waiting run.
     */
    public function cancelRun(WorkflowRun $run): void
    {
        if ($run->isTerminal()) {
            throw new \RuntimeException('Cannot cancel a completed workflow run');
        }

        $run->update([
            'status' => WorkflowRun::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Process timed-out workflow runs (called by cron).
     */
    public function processTimedOutRuns(): void
    {
        $timedOutRuns = WorkflowRun::query()
            ->where('status', WorkflowRun::STATUS_WAITING)
            ->whereNotNull('wait_until')
            ->where('wait_until', '<=', now())
            ->get();

        foreach ($timedOutRuns as $run) {
            $workflow = $run->workflow;
            $currentStep = $workflow->findStep($run->current_step_id);

            if (! $currentStep) {
                $run->update(['status' => WorkflowRun::STATUS_TIMED_OUT, 'completed_at' => now()]);
                continue;
            }

            $company = $run->company;

            if ($currentStep['type'] === 'wait_delay') {
                // Delay completed - advance to next step
                $run->logStep($currentStep, 'completed', ['delay_elapsed' => true]);
                $nextStep = $workflow->nextStep($run->current_step_id);

                $run->update([
                    'status' => WorkflowRun::STATUS_ACTIVE,
                    'current_step_id' => $nextStep['id'] ?? null,
                    'waiting_for' => null,
                    'waiting_since' => null,
                    'wait_until' => null,
                ]);

                if ($nextStep) {
                    $this->advanceRun($run, $company);
                } else {
                    $run->update(['status' => WorkflowRun::STATUS_COMPLETED, 'completed_at' => now()]);
                }
            } elseif ($currentStep['type'] === 'wait_for_event' && ! empty($currentStep['on_timeout'])) {
                // Event wait timed out - jump to timeout step
                $run->logStep($currentStep, 'timed_out');
                $timeoutStep = $workflow->findStep($currentStep['on_timeout']);

                $run->update([
                    'status' => WorkflowRun::STATUS_ACTIVE,
                    'current_step_id' => $timeoutStep['id'] ?? null,
                    'waiting_for' => null,
                    'waiting_since' => null,
                    'wait_until' => null,
                ]);

                if ($timeoutStep) {
                    $this->advanceRun($run, $company);
                } else {
                    $run->update(['status' => WorkflowRun::STATUS_TIMED_OUT, 'completed_at' => now()]);
                }
            } else {
                // No timeout handler - mark as timed out
                $run->logStep($currentStep, 'timed_out');
                $run->update(['status' => WorkflowRun::STATUS_TIMED_OUT, 'completed_at' => now()]);
            }
        }
    }

    // --- Private Step Handlers ---

    private function executeAction(WorkflowRun $run, array $step, Company $company): void
    {
        $actionType = $step['action'] ?? '';
        $handlerClass = self::$actionHandlers[$actionType] ?? null;

        if (! $handlerClass) {
            throw new \RuntimeException("Unknown action type: {$actionType}");
        }

        /** @var WorkflowActionInterface $handler */
        $handler = new $handlerClass();

        $run->logStep($step, 'started');

        $context = $run->context ?? [];
        $result = $handler->execute($step['params'] ?? [], $context, $run, $company);

        // Store output entity in context
        if (! empty($step['output_key']) && $result && isset($result['entity_id'])) {
            $run->mergeContext([$step['output_key'] => $result['entity_id']]);
        }

        $run->logStep($step, 'completed', $result);

        // Move to next step
        $nextStep = $run->workflow->nextStep($step['id']);
        $run->update([
            'current_step_id' => $nextStep['id'] ?? null,
        ]);

        if (! $nextStep) {
            $run->update([
                'status' => WorkflowRun::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }

    private function enterWait(WorkflowRun $run, array $step): void
    {
        $run->logStep($step, 'waiting');

        $run->update([
            'status' => WorkflowRun::STATUS_WAITING,
            'waiting_for' => $step['event'] ?? '',
            'waiting_since' => now(),
            'wait_until' => isset($step['timeout_days']) ? now()->addDays((int) $step['timeout_days']) : null,
        ]);
    }

    private function enterDelay(WorkflowRun $run, array $step): void
    {
        $delayDays = $step['delay_days'] ?? 0;
        $delayHours = $step['delay_hours'] ?? 0;

        $waitUntil = now();
        if ($delayDays > 0) {
            $waitUntil = $waitUntil->addDays($delayDays);
        }
        if ($delayHours > 0) {
            $waitUntil = $waitUntil->addHours($delayHours);
        }

        $run->logStep($step, 'waiting');

        $run->update([
            'status' => WorkflowRun::STATUS_WAITING,
            'waiting_for' => '__timer__',
            'waiting_since' => now(),
            'wait_until' => $waitUntil,
        ]);
    }

    private function evaluateBranch(WorkflowRun $run, array $step): void
    {
        $context = $run->context ?? [];
        $conditions = $step['conditions'] ?? [];

        $run->logStep($step, 'started');

        foreach ($conditions as $condition) {
            $fieldRef = $condition['if']['field'] ?? '';
            $operator = $condition['if']['operator'] ?? '=';
            $expectedValue = $condition['if']['value'] ?? null;
            $gotoStep = $condition['goto'] ?? null;

            $actualValue = ContextResolver::resolveField($fieldRef, $context, $run);

            if ($this->evaluateOperator($actualValue, $expectedValue, $operator)) {
                $run->logStep($step, 'completed', [
                    'branch_taken' => $condition['label'] ?? $gotoStep,
                    'field' => $fieldRef,
                    'actual_value' => $actualValue,
                ]);

                $run->update(['current_step_id' => $gotoStep]);

                return;
            }
        }

        // No condition matched - use default or next sequential
        $defaultNext = $step['default_next'] ?? null;
        $nextStep = $defaultNext ? $run->workflow->findStep($defaultNext) : $run->workflow->nextStep($step['id']);

        $run->logStep($step, 'completed', ['branch_taken' => 'default']);
        $run->update(['current_step_id' => $nextStep['id'] ?? null]);

        if (! $nextStep) {
            $run->update([
                'status' => WorkflowRun::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }

    private function endRun(WorkflowRun $run, array $step): void
    {
        $endStatus = $step['end_status'] ?? 'completed';

        $run->logStep($step, 'completed', ['end_status' => $endStatus]);

        $run->update([
            'status' => WorkflowRun::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    // --- Helpers ---

    private function triggerConditionsMatch(Workflow $workflow, BaseModel $entity): bool
    {
        $conditions = $workflow->trigger_conditions;

        if (empty($conditions)) {
            return true;
        }

        // Create a temporary context with the trigger entity
        $entityKey = $this->entityKey($entity);
        $dummyContext = ['trigger' => $entity->id, $entityKey => $entity->id];

        // Create a temporary run-like object for field resolution
        $tempRun = new WorkflowRun();
        $tempRun->entity_type = get_class($entity);
        $tempRun->entity_id = $entity->id;

        $matchCount = 0;
        $totalConditions = count($conditions);

        foreach ($conditions as $condition) {
            $fieldRef = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? '=';
            $expectedValue = $condition['value'] ?? null;

            // Prefix with trigger entity if no $ prefix
            if (! str_starts_with($fieldRef, '$')) {
                $fieldRef = '$' . $entityKey . '.' . $fieldRef;
            }

            $actualValue = ContextResolver::resolveField($fieldRef, $dummyContext, $tempRun);

            if ($this->evaluateOperator($actualValue, $expectedValue, $operator)) {
                $matchCount++;
            }
        }

        $matchAll = $workflow->trigger_conditions_match_all ?? true;

        return $matchAll ? $matchCount === $totalConditions : $matchCount > 0;
    }

    private function evaluateOperator($actual, $expected, string $operator): bool
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            $actual = (float) $actual;
            $expected = (float) $expected;

            return match ($operator) {
                '=' => $actual == $expected,
                '!=' => $actual != $expected,
                '>' => $actual > $expected,
                '>=' => $actual >= $expected,
                '<' => $actual < $expected,
                '<=' => $actual <= $expected,
                default => false,
            };
        }

        return match ($operator) {
            '=', 'is' => (string) $actual === (string) $expected,
            '!=' => (string) $actual !== (string) $expected,
            'contains' => str_contains((string) $actual, (string) $expected),
            'starts_with' => str_starts_with((string) $actual, (string) $expected),
            'is_empty' => empty($actual),
            default => false,
        };
    }

    private function eventMatchesWaitingRun(WorkflowRun $run, BaseModel $entity, string $entityType, string $event): bool
    {
        $waitingFor = $run->waiting_for;
        $eventPattern = $entityType . '.' . $event;

        // Check if the event pattern matches (supports pipe-separated OR)
        $patterns = explode('|', $waitingFor);

        foreach ($patterns as $pattern) {
            if (trim($pattern) === $eventPattern) {
                // Verify the entity is related to this run's context
                $context = $run->context ?? [];
                $entityKey = $this->entityKey($entity);

                // Match if entity is in context or is the trigger entity
                if (
                    ($run->entity_type === get_class($entity) && $run->entity_id === $entity->id) ||
                    (isset($context[$entityKey]) && $context[$entityKey] === $entity->id)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function entityKey(BaseModel $entity): string
    {
        return Str::snake(class_basename($entity));
    }
}
