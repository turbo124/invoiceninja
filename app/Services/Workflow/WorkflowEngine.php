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

use App\Events\Workflow\WorkflowRunFailed;
use App\Factory\WorkflowRunFactory;
use App\Models\BaseModel;
use App\Models\Company;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Services\Workflow\Actions;
use App\Services\Workflow\Actions\AssignUserAction;
use App\Services\Workflow\Actions\ConvertAction;
use App\Services\Workflow\Actions\CreateTaskAction;
use App\Services\Workflow\Actions\NotifyUserAction;
use App\Services\Workflow\Actions\SendEmailAction;
use App\Services\Workflow\Actions\SendWebhookAction;
use App\Services\Workflow\Actions\WorkflowActionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowEngine
{
    /**
     * Map of action type keys to handler classes.
     */
    private static array $actionHandlers = [
        'send_email' => SendEmailAction::class,
        'notify_user' => NotifyUserAction::class,
        'send_webhook' => SendWebhookAction::class,
        'create_task' => CreateTaskAction::class,
        'assign_user' => AssignUserAction::class,
        'convert' => ConvertAction::class,
        'mark_sent' => Actions\MarkSentAction::class,
        'auto_bill' => Actions\AutoBillAction::class,
        'approve' => Actions\ApproveAction::class,
        'increase_price' => Actions\IncreasePriceAction::class,
        'update_price' => Actions\UpdatePriceAction::class,
        'add_to_inventory' => Actions\AddToInventoryAction::class,
        'expense_from_po' => Actions\ExpenseFromPoAction::class,
        'archive' => Actions\ArchiveAction::class,
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

    /** Maximum concurrent active/waiting runs per workflow+entity. */
    private const MAX_RUNS_PER_ENTITY = 3;

    /**
     * Start a new workflow run from a trigger.
     *
     * Uses a pessimistic row lock to prevent race conditions when
     * duplicate events fire concurrently. If an active/waiting run
     * already exists, refreshes it with current entity data. A hard
     * cap prevents runaway loops from creating unbounded runs.
     */
    public function startRun(Workflow $workflow, BaseModel $entity, Company $company): ?WorkflowRun
    {
        // Acquire a row lock to prevent duplicate runs from concurrent events.
        // The lock scope is narrow: only workflow_runs for this workflow+entity.
        $run = DB::transaction(function () use ($workflow, $entity, $company) {

            $existingRun = WorkflowRun::where('workflow_id', $workflow->id)
                ->where('workflowable_type', get_class($entity))
                ->where('workflowable_id', $entity->id)
                ->whereIn('status', [WorkflowRun::STATUS_ACTIVE, WorkflowRun::STATUS_WAITING])
                ->lockForUpdate()
                ->first();

            if ($existingRun) {
                $this->refreshExistingRun($existingRun, $entity, $company);
                return $existingRun;
            }

            // Hard cap: prevent runaway event loops from creating unbounded runs
            $recentRunCount = WorkflowRun::where('workflow_id', $workflow->id)
                ->where('workflowable_type', get_class($entity))
                ->where('workflowable_id', $entity->id)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($recentRunCount >= self::MAX_RUNS_PER_ENTITY) {
                nlog("Workflow {$workflow->id} hit run cap for entity {$entity->id} ({$recentRunCount} runs in last hour)");
                return null;
            }

            $entityKey = $this->entityKey($entity);

            $run = WorkflowRunFactory::create(
                $company->id,
                $entity->user_id ?? $company->owner()->id,
                $workflow,
                $entity,
            );
            $run->context = ['trigger' => $entity->id, $entityKey => $entity->id];
            $run->save();

            return $run;
        });

        // Advance outside the transaction — release the row lock before
        // potentially long-running step execution begins.
        if ($run && $run->wasRecentlyCreated) {
            $this->advanceRun($run, $company);
        }

        return $run;
    }

    /**
     * Refresh an existing in-flight run with the entity's current data.
     *
     * Updates the run context and, if the run is parked on a timer wait,
     * recomputes wait_until from the entity's current field values.
     * This avoids stale dates when an entity is modified between trigger
     * events (e.g. next_send_date changed after a stop/start cycle).
     */
    private function refreshExistingRun(WorkflowRun $run, BaseModel $entity, Company $company): void
    {
        $entityKey = $this->entityKey($entity);
        $run->mergeContext(['trigger' => $entity->id, $entityKey => $entity->id]);

        $run->logStep(
            $run->findStep($run->current_step_id) ?? ['id' => $run->current_step_id, 'type' => 'unknown'],
            'refreshed',
            ['reason' => 'trigger re-fired, entity data updated']
        );

        // If parked on a timer, recompute wait_until from current entity fields
        if ($run->status === WorkflowRun::STATUS_WAITING && $run->waiting_for === '__timer__') {
            $currentStep = $run->findStep($run->current_step_id);

            if ($currentStep && $currentStep['type'] === 'wait_delay') {
                try {
                    $newWaitUntil = $this->resolveWaitUntil($run, $currentStep);

                    if (! $newWaitUntil->eq($run->wait_until)) {
                        $run->update(['wait_until' => $newWaitUntil]);

                        $run->logStep($currentStep, 'wait_updated', [
                            'old_wait_until' => $run->getOriginal('wait_until'),
                            'new_wait_until' => $newWaitUntil->toIso8601String(),
                        ]);
                    }
                } catch (WorkflowOperationException) {
                    // Field resolution failed — leave existing wait_until intact
                }
            }
        }
    }

    /**
     * Core execution loop: advance through steps until a wait or end.
     * Now with classified error handling per the spec.
     */
    public function advanceRun(WorkflowRun $run, Company $company): void
    {
        if ($this->entityIsInactive($run)) {
            return;
        }

        $maxSteps = 50;
        $stepsExecuted = 0;

        while ($run->status === WorkflowRun::STATUS_ACTIVE && $stepsExecuted < $maxSteps) {
            $step = $run->findStep($run->current_step_id);

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
                    'wait_for_event' => $this->enterWait($run, $step, $company),
                    'wait_delay' => $this->enterDelay($run, $step),
                    'branch' => $this->evaluateBranch($run, $step),
                    'end' => $this->endRun($run, $step),
                    default => throw new \RuntimeException("Unknown step type: {$step['type']}"),
                };
            } catch (WorkflowOperationException $e) {
                match ($e->failureType) {
                    OperationFailureType::GUARD_FAILED =>
                        $this->routeFailure($run, $step, $e, $step['on_guard_fail'] ?? 'skip'),
                    OperationFailureType::TRANSIENT =>
                        $this->handleTransientFailure($run, $step, $e),
                    OperationFailureType::ASSERTION_FAILED,
                    OperationFailureType::PERMANENT =>
                        $this->routeFailure($run, $step, $e, $step['on_error'] ?? 'stop'),
                };
                // If the run was stopped or is waiting for retry, break out
                if ($run->status !== WorkflowRun::STATUS_ACTIVE) {
                    break;
                }
            } catch (\Throwable $e) {
                // Unknown/unclassified errors always route via on_error
                $this->routeFailure($run, $step, $e, $step['on_error'] ?? 'stop');
                if ($run->status !== WorkflowRun::STATUS_ACTIVE) {
                    break;
                }
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
        if ($this->entityIsInactive($run)) {
            return;
        }

        $currentStep = $run->findStep($run->current_step_id);

        if ($currentStep) {
            $run->logStep($currentStep, 'completed', ['event_received' => $run->waiting_for]);
        }

        $nextStep = $run->nextStep($run->current_step_id);

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
     * Retry a failed run from its current step.
     */
    public function retryRun(WorkflowRun $run, Company $company): void
    {
        if ($run->status !== WorkflowRun::STATUS_FAILED) {
            throw new \RuntimeException('Can only retry a failed workflow run');
        }

        $run->update([
            'status' => WorkflowRun::STATUS_ACTIVE,
            'error_message' => null,
        ]);

        $this->advanceRun($run, $company);
    }

    /**
     * Process timed-out workflow runs (called by cron).
     * Handles timer delays, event timeouts, and retries.
     */
    public function processTimedOutRuns(): void
    {
        $timedOutRuns = WorkflowRun::query()
            ->where('status', WorkflowRun::STATUS_WAITING)
            ->whereNotNull('wait_until')
            ->where('wait_until', '<=', now())
            ->get();

        foreach ($timedOutRuns as $run) {
            $currentStep = $run->findStep($run->current_step_id);

            if (! $currentStep) {
                $run->update(['status' => WorkflowRun::STATUS_TIMED_OUT, 'completed_at' => now()]);
                continue;
            }

            $company = $run->company;

            // Handle retry runs — re-execute the current step
            if ($run->waiting_for === '__retry__') {
                $run->update([
                    'status' => WorkflowRun::STATUS_ACTIVE,
                    'waiting_for' => null,
                    'waiting_since' => null,
                    'wait_until' => null,
                ]);
                $this->advanceRun($run, $company);
                continue;
            }

            if ($currentStep['type'] === 'wait_delay' || $run->waiting_for === '__timer__') {
                // Delay completed - advance to next step
                $run->logStep($currentStep, 'completed', ['delay_elapsed' => true]);
                $nextStep = $run->nextStep($run->current_step_id);

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
                $timeoutStep = $run->findStep($currentStep['on_timeout']);

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
            throw new WorkflowOperationException(
                "Unknown action type: {$actionType}",
                OperationFailureType::PERMANENT
            );
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
        $this->moveToNextStep($run, $step);
    }

    private function enterWait(WorkflowRun $run, array $step, Company $company): void
    {
        // Pre-check: if the entity already satisfies the wait condition, skip it
        if (! empty($step['satisfied_when'])) {
            $fieldValue = ContextResolver::resolveField(
                $step['satisfied_when']['field'],
                $run->context ?? [],
                $run
            );

            if ($this->evaluateCondition($fieldValue, $step['satisfied_when']['operator'], $step['satisfied_when']['value'])) {
                $run->logStep($step, 'skipped', ['reason' => 'satisfied_when already met']);
                $this->moveToNextStep($run, $step);
                return;
            }
        }

        // Normal wait entry — park the run
        $run->logStep($step, 'waiting');

        $waitData = [
            'status' => WorkflowRun::STATUS_WAITING,
            'waiting_for' => $step['event'] ?? '',
            'waiting_since' => now(),
        ];

        if (! empty($step['timeout_days'])) {
            $waitData['wait_until'] = now()->addDays((int) $step['timeout_days']);
        }

        $run->update($waitData);
    }

    private function enterDelay(WorkflowRun $run, array $step): void
    {
        $waitUntil = $this->resolveWaitUntil($run, $step);

        // If the computed wait time is already in the past, skip the wait
        if ($waitUntil->isPast()) {
            $run->logStep($step, 'skipped', ['reason' => 'wait_until already passed', 'wait_until' => $waitUntil->toIso8601String()]);
            $this->moveToNextStep($run, $step);
            return;
        }

        $run->logStep($step, 'waiting', ['wait_until' => $waitUntil->toIso8601String()]);

        $run->update([
            'status' => WorkflowRun::STATUS_WAITING,
            'waiting_for' => '__timer__',
            'waiting_since' => now(),
            'wait_until' => $waitUntil,
        ]);
    }

    /**
     * Resolve the wait_until timestamp for a wait_delay step.
     *
     * Requires date_field — resolves an entity date property
     * and adds offset_days to determine when to resume.
     */
    private function resolveWaitUntil(WorkflowRun $run, array $step): \Illuminate\Support\Carbon
    {
        $companyTz = $this->companyTimezone($run);
        $dateField = $step['date_field'] ?? '';

        if (empty($dateField)) {
            throw new WorkflowOperationException(
                'wait_delay step requires a date_field property',
                OperationFailureType::PERMANENT
            );
        }

        $dateValue = ContextResolver::resolveField(
            $dateField,
            $run->context ?? [],
            $run
        );

        if (! $dateValue) {
            throw new WorkflowOperationException(
                "Cannot resolve date_field '{$dateField}' — field is null or entity not found",
                OperationFailureType::PERMANENT
            );
        }

        // Parse date in company timezone (date fields like due_date are Y-m-d with no time)
        $baseDate = \Illuminate\Support\Carbon::parse($dateValue, $companyTz);
        $offsetDays = $step['offset_days'] ?? 0;

        if ($offsetDays != 0) {
            $baseDate->addDays((int) $offsetDays);
        }

        return $baseDate;
    }

    /**
     * Get the company timezone name for a workflow run.
     */
    private function companyTimezone(WorkflowRun $run): string
    {
        $tz = $run->company->timezone();

        return $tz ? $tz->name : 'UTC';
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

            if ($this->evaluateCondition($actualValue, $operator, $expectedValue)) {
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
        $nextStep = $defaultNext ? $run->findStep($defaultNext) : $run->nextStep($step['id']);

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

        if (! empty($step['restart'])) {
            $firstStep = $run->firstStep();

            $run->logStep($step, 'restarted', ['cycle_status' => $endStatus]);

            $run->update([
                'status' => WorkflowRun::STATUS_ACTIVE,
                'current_step_id' => $firstStep['id'] ?? null,
                'waiting_for' => null,
                'waiting_since' => null,
                'wait_until' => null,
                'error_message' => null,
            ]);

            return;
        }

        $run->logStep($step, 'completed', ['end_status' => $endStatus]);

        $run->update([
            'status' => WorkflowRun::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    // --- Error Handling ---

    private function routeFailure(WorkflowRun $run, array $step, \Throwable $e, string $action): void
    {
        match ($action) {
            'skip' => $this->skipStep($run, $step, $e->getMessage()),
            'stop' => $this->failRun($run, $step, $e),
            default => $this->gotoStep($run, $action, $step, $e->getMessage()),
        };
    }

    private function skipStep(WorkflowRun $run, array $step, string $reason): void
    {
        $run->logStep($step, 'skipped', null, $reason);
        $this->moveToNextStep($run, $step);
    }

    private function failRun(WorkflowRun $run, array $step, \Throwable $e, ?string $overrideMessage = null): void
    {
        $message = $overrideMessage ?? $e->getMessage();

        $run->logStep($step, 'failed', null, $message);
        $run->update([
            'status' => WorkflowRun::STATUS_FAILED,
            'error_message' => $message,
        ]);

        nlog("Workflow run {$run->id} failed at step {$step['id']}: {$message}");

        event(new WorkflowRunFailed($run, $step, $message));
    }

    private function gotoStep(WorkflowRun $run, string $targetStepId, array $failedStep, string $reason): void
    {
        $run->logStep($failedStep, 'failed', ['routed_to' => $targetStepId], $reason);

        $targetStep = $run->findStep($targetStepId);
        if (! $targetStep) {
            // Target step doesn't exist — fail the run
            $run->update([
                'status' => WorkflowRun::STATUS_FAILED,
                'error_message' => "Error handler step '{$targetStepId}' not found. Original error: {$reason}",
            ]);
            return;
        }

        $run->update(['current_step_id' => $targetStepId]);
    }

    private function handleTransientFailure(WorkflowRun $run, array $step, WorkflowOperationException $e): void
    {
        $maxRetries = $step['max_retries'] ?? 0;
        $retryCount = $this->getStepRetryCount($run, $step['id']);

        if ($retryCount >= $maxRetries) {
            // Exhausted retries — now route via on_error
            $this->routeFailure($run, $step, $e, $step['on_error'] ?? 'stop');
            return;
        }

        // Schedule retry with exponential backoff: 5min, 15min, 45min
        $backoffMinutes = 5 * pow(3, $retryCount);
        $run->logStep($step, 'retry_scheduled', [
            'retry_count' => $retryCount + 1,
            'next_retry_at' => now()->addMinutes($backoffMinutes)->toIso8601String(),
            'error' => $e->getMessage(),
        ]);

        $run->update([
            'status' => WorkflowRun::STATUS_WAITING,
            'waiting_for' => '__retry__',
            'waiting_since' => now(),
            'wait_until' => now()->addMinutes($backoffMinutes),
        ]);
    }

    private function getStepRetryCount(WorkflowRun $run, string $stepId): int
    {
        $history = $run->step_history ?? [];
        $count = 0;

        foreach ($history as $entry) {
            if (($entry['step_id'] ?? '') === $stepId && ($entry['status'] ?? '') === 'retry_scheduled') {
                $count++;
            }
        }

        return $count;
    }

    // --- Navigation Helper ---

    private function moveToNextStep(WorkflowRun $run, array $step): void
    {
        $nextStep = $run->nextStep($step['id']);
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

    // --- Condition Evaluation ---

    /**
     * Normalize operator aliases (gt, gte, lt, lte, eq, neq) to symbols.
     */
    private function normalizeOperator(string $operator): string
    {
        return match (strtolower($operator)) {
            'eq' => '=',
            'neq', 'ne' => '!=',
            'gt' => '>',
            'gte', 'ge' => '>=',
            'lt' => '<',
            'lte', 'le' => '<=',
            default => $operator,
        };
    }

    private function evaluateCondition($actual, string $operator, $expected): bool
    {
        $operator = $this->normalizeOperator($operator);

        if ($operator === 'in') {
            return is_array($expected) && in_array($actual, $expected);
        }

        if ($operator === 'is_empty') {
            return empty($actual);
        }

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
            default => false,
        };
    }

    // --- Helpers ---

    /**
     * Evaluate trigger conditions against the entity.
     *
     * Supports two formats:
     *  - Grouped: each entry has "conditions" array + "match" (all|any).
     *    Groups are OR'd: if ANY group passes, the workflow triggers.
     *  - Legacy flat: each entry has "field"/"operator"/"value" directly.
     *    Falls back to the old trigger_conditions_match_all behaviour.
     */
    private function triggerConditionsMatch(Workflow $workflow, BaseModel $entity): bool
    {
        $conditions = $workflow->trigger_conditions;

        if (empty($conditions)) {
            return true;
        }

        $entityKey = $this->entityKey($entity);
        $dummyContext = ['trigger' => $entity->id, $entityKey => $entity->id];

        $tempRun = new WorkflowRun();
        $tempRun->workflowable_type = get_class($entity);
        $tempRun->workflowable_id = $entity->id;

        // Detect format: grouped (has "conditions" key) vs legacy flat (has "field" key)
        $isGrouped = isset($conditions[0]['conditions']);

        if ($isGrouped) {
            return $this->evaluateConditionGroups($conditions, $entityKey, $dummyContext, $tempRun);
        }

        return $this->evaluateFlatConditions($conditions, $entityKey, $dummyContext, $tempRun, $workflow->trigger_conditions_match_all ?? true);
    }

    /**
     * Grouped conditions: groups are OR'd, conditions within a group
     * are AND'd or OR'd based on the group's "match" setting.
     */
    private function evaluateConditionGroups(array $groups, string $entityKey, array $context, WorkflowRun $tempRun): bool
    {
        foreach ($groups as $group) {
            $conditions = $group['conditions'] ?? [];

            if (empty($conditions)) {
                continue;
            }

            $matchAll = ($group['match'] ?? 'all') === 'all';

            if ($this->evaluateFlatConditions($conditions, $entityKey, $context, $tempRun, $matchAll)) {
                return true; // Any group passing is enough
            }
        }

        return false;
    }

    /**
     * Evaluate a flat list of conditions with AND (all) or OR (any) logic.
     */
    private function evaluateFlatConditions(array $conditions, string $entityKey, array $context, WorkflowRun $tempRun, bool $matchAll): bool
    {
        $matchCount = 0;
        $total = count($conditions);

        foreach ($conditions as $condition) {
            $fieldRef = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? '=';
            $expectedValue = $condition['value'] ?? null;

            if (! str_starts_with($fieldRef, '$')) {
                $fieldRef = '$' . $entityKey . '.' . $fieldRef;
            }

            $actualValue = ContextResolver::resolveField($fieldRef, $context, $tempRun);

            if ($this->evaluateCondition($actualValue, $operator, $expectedValue)) {
                if (! $matchAll) {
                    return true; // OR mode: one match is enough
                }
                $matchCount++;
            }
        }

        return $matchAll ? $matchCount === $total : $matchCount > 0;
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
                    ($run->workflowable_type === get_class($entity) && $run->workflowable_id === $entity->id) ||
                    (isset($context[$entityKey]) && $context[$entityKey] === $entity->id)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the triggering entity has been archived or deleted.
     * If so, mark the run as completed and return true to halt execution.
     */
    private function entityIsInactive(WorkflowRun $run): bool
    {
        $entity = $run->workflowable;

        if (! $entity) {
            $run->update([
                'status' => WorkflowRun::STATUS_COMPLETED,
                'completed_at' => now(),
                'error_message' => 'Triggering entity no longer exists',
            ]);

            return true;
        }

        $isDeleted = $entity->getAttribute('is_deleted') === true;
        $isArchived = method_exists($entity, 'trashed') && $entity->trashed();

        if ($isDeleted || $isArchived) {
            $run->update([
                'status' => WorkflowRun::STATUS_COMPLETED,
                'completed_at' => now(),
                'error_message' => 'Triggering entity was ' . ($isDeleted ? 'deleted' : 'archived'),
            ]);

            return true;
        }

        return false;
    }

    private function entityKey(BaseModel $entity): string
    {
        return Str::snake(class_basename($entity));
    }
}
