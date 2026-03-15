<?php

namespace App\Http\Requests\Workflow;

use App\Http\Requests\Request;
use App\Models\Workflow;

class StoreWorkflowRequest extends Request
{
    public function authorize(): bool
    {
        return auth()->user()->can('create', Workflow::class);
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'trigger_entity' => 'required|string|max:50',
            'trigger_event' => 'required|string|max:50',
            'trigger_conditions' => 'sometimes|nullable|array',
            'steps' => 'required|array|min:1',
            'is_active' => 'sometimes|boolean',
            'is_template' => 'sometimes|boolean',
            'description' => 'sometimes|nullable|string',
        ];
    }
}
