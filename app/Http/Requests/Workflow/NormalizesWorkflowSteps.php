<?php

namespace App\Http\Requests\Workflow;

trait NormalizesWorkflowSteps
{
    /**
     * Normalize a single step from UI format to engine format.
     *
     * Handles:
     * - kind → type
     * - action_id → action
     * - config {} → flat step properties
     * - offset_operator (before/on/after) + offset_days → signed offset_days
     */
    protected function normalizeStep(array $step): array
    {
        // kind → type
        if (isset($step['kind']) && ! isset($step['type'])) {
            $step['type'] = $step['kind'];
            unset($step['kind']);
        }

        // action_id → action
        if (isset($step['action_id']) && ! isset($step['action'])) {
            $step['action'] = $step['action_id'];
            unset($step['action_id']);
        }

        // Flatten config into the step
        if (isset($step['config']) && is_array($step['config'])) {
            $config = $step['config'];
            unset($step['config']);

            $step = $this->flattenConfig($step, $config);
        }

        return $step;
    }

    /**
     * Flatten a step's config object into engine-compatible properties.
     */
    private function flattenConfig(array $step, array $config): array
    {
        $type = $step['type'] ?? '';

        return match ($type) {
            'wait_delay' => $this->flattenWaitDelayConfig($step, $config),
            'wait_for_event' => $this->flattenWaitForEventConfig($step, $config),
            'branch' => $this->flattenBranchConfig($step, $config),
            'action' => $this->flattenActionConfig($step, $config),
            default => array_merge($step, $config),
        };
    }

    /**
     * wait_delay config:
     *   UI sends: { date_field: "$trigger.due_date", offset_days: 3, offset_operator: "before"|"on"|"after" }
     *   Engine needs: { date_field: "$trigger.due_date", offset_days: -3|0|3 }
     *
     *   - "before" → negative offset_days
     *   - "on"     → offset_days forced to 0
     *   - "after"  → positive offset_days (default)
     */
    private function flattenWaitDelayConfig(array $step, array $config): array
    {
        if (! empty($config['date_field'])) {
            $step['date_field'] = $config['date_field'];
        }

        $days = abs((int) ($config['offset_days'] ?? 0));
        $operator = $config['offset_operator'] ?? 'after';

        $step['offset_days'] = match ($operator) {
            'before' => -$days,
            'on' => 0,
            default => $days,
        };

        return $step;
    }

    /**
     * wait_for_event config:
     *   { event: "invoice.paid", timeout_days: 7, on_timeout: "step_id" }
     */
    private function flattenWaitForEventConfig(array $step, array $config): array
    {
        return array_merge($step, $config);
    }

    /**
     * branch config:
     *   { conditions: [...], default_next: "step_id" }
     */
    private function flattenBranchConfig(array $step, array $config): array
    {
        return array_merge($step, $config);
    }

    /**
     * action config → params
     *   { entity_ref: "$trigger", operation: "mark_sent" }
     */
    private function flattenActionConfig(array $step, array $config): array
    {
        if (! isset($step['params'])) {
            $step['params'] = $config;
        }

        return $step;
    }
}
