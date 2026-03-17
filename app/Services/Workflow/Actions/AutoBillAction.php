<?php

namespace App\Services\Workflow\Actions;

use App\Models\Company;
use App\Models\WorkflowRun;

class AutoBillAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $params['entity_ref'] = '$trigger';
        $params['operation'] = 'auto_bill';

        return (new EntityOperationAction())->execute($params, $context, $run, $company);
    }
}
