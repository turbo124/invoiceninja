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

namespace App\Events\Workflow;

use App\Models\Company;
use App\Models\Workflow;
use Illuminate\Queue\SerializesModels;

class WorkflowWasArchived
{
    use SerializesModels;

    public $workflow;

    public $company;

    public $event_vars;

    public function __construct(Workflow $workflow, Company $company, array $event_vars)
    {
        $this->workflow = $workflow;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
