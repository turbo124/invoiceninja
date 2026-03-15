<?php

namespace App\Http\Requests\Workflow;

use App\Http\Requests\Request;

class BulkWorkflowRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'action' => 'required|string|in:archive,restore,delete,activate,deactivate',
            'ids' => 'required|bail|array',
        ];
    }
}
