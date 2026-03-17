<?php

namespace App\Http\Requests\Workflow;

use App\Http\Requests\Request;

class UpdateWorkflowRequest extends Request
{
    use NormalizesWorkflowSteps;

    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->workflow);
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'trigger_entity' => 'sometimes|nullable|string|max:50',
            'trigger_event' => 'required_with:trigger_entity|exclude_if:trigger_entity,manual|string|max:50',
            'trigger_conditions' => 'sometimes|nullable|array',
            'steps' => 'sometimes|nullable|array',
            'is_template' => 'sometimes|boolean',
            'description' => 'sometimes|nullable|string',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        // Transform nested trigger object to flat fields
        if (isset($input['trigger']) && is_array($input['trigger'])) {
            $trigger = $input['trigger'];

            if (isset($trigger['entity']) && ! isset($input['trigger_entity'])) {
                $input['trigger_entity'] = $trigger['entity'] !== '' ? strtolower($trigger['entity']) : null;
            }

            if (isset($trigger['event']) && ! isset($input['trigger_event'])) {
                $input['trigger_event'] = $trigger['event'] !== '' ? strtolower($trigger['event']) : null;
            }

            if (isset($trigger['conditions']) && ! isset($input['trigger_conditions'])) {
                $input['trigger_conditions'] = $trigger['conditions'];
            }

            unset($input['trigger']);
        }

        // Normalize steps from UI format to engine format
        if (isset($input['steps']) && is_array($input['steps'])) {
            $input['steps'] = array_map(fn ($step) => $this->normalizeStep($step), $input['steps']);
        }

        // Strip UI-only fields
        unset($input['id'], $input['status'], $input['edges']);

        $this->replace($input);
    }
}
