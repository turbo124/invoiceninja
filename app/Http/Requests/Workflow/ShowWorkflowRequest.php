<?php

namespace App\Http\Requests\Workflow;

use App\Http\Requests\Request;

class ShowWorkflowRequest extends Request
{
    public function authorize(): bool
    {
        return auth()->user()->can('view', $this->workflow);
    }

    public function rules()
    {
        return [];
    }
}
