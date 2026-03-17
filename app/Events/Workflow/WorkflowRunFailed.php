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

use App\Models\WorkflowRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowRunFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public WorkflowRun $run,
        public array $step,
        public string $message
    ) {}
}
