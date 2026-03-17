<?php

namespace App\Services\Workflow\Actions;

use App\Models\Company;
use App\Models\WorkflowRun;

class IncreasePriceAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $params['entity_ref'] = '$trigger';
        $params['operation'] = 'increase_price';
        $params['args'] = ['percentage' => $params['percentage'] ?? 0];

        return (new EntityOperationAction())->execute($params, $context, $run, $company);
    }
}
