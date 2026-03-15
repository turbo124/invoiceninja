<?php

namespace App\Http\Requests\Workflow;

use App\Http\Requests\Request;

class DestroyWorkflowRequest extends Request
{
    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->workflow);
    }

    public function rules()
    {
        return [];
    }
}
