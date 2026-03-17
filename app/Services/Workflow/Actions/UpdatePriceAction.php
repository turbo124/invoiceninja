<?php

namespace App\Services\Workflow\Actions;

use App\Models\Company;
use App\Models\WorkflowRun;

class UpdatePriceAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        $params['entity_ref'] = '$trigger';
        $params['operation'] = 'update_price';

        return (new EntityOperationAction())->execute($params, $context, $run, $company);
    }
}
