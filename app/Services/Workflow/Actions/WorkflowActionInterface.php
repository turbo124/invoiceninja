<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Workflow\Actions;

use App\Models\Company;
use App\Models\WorkflowRun;

interface WorkflowActionInterface
{
    /**
     * Execute the workflow action.
     *
     * @param  array  $params  Action parameters from step definition
     * @param  array  $context  Accumulated entity IDs from prior steps
     * @param  WorkflowRun  $run  The current workflow run
     * @param  Company  $company  The company context
     * @return array|null  Result data to store (e.g., ['entity_type' => 'Quote', 'entity_id' => 42])
     */
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array;
}
