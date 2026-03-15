<?php

namespace App\Http\Requests\Workflow;

use App\Http\Requests\Request;

class UpdateWorkflowRequest extends Request
{
    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->workflow);
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'trigger_entity' => 'sometimes|string|max:50',
            'trigger_event' => 'sometimes|string|max:50',
            'trigger_conditions' => 'sometimes|nullable|array',
            'steps' => 'sometimes|array|min:1',
            'is_active' => 'sometimes|boolean',
            'is_template' => 'sometimes|boolean',
            'description' => 'sometimes|nullable|string',
        ];
    }
}
