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

namespace App\Jobs\Workflow;

use App\Libraries\MultiDB;
use App\Models\BaseModel;
use App\Models\Company;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ProcessWorkflowEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $maxExceptions = 2;

    public function __construct(
        private string $entityType,
        private string $event,
        private BaseModel $entity,
        private Company $company
    ) {}

    public function handle(): void
    {
        MultiDB::setDb($this->company->db);

        try {
            $engine = new WorkflowEngine();
            $engine->onEvent($this->entityType, $this->event, $this->entity, $this->company);
        } catch (\Throwable $e) {
            nlog("Workflow event processing failed: {$e->getMessage()}");
            nlog($e->getTraceAsString());
        }
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("workflow_event_{$this->entityType}_{$this->entity->id}_{$this->event}"))->dontRelease()];
    }
}
