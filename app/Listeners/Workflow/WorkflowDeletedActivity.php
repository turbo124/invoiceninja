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

namespace App\Listeners\Workflow;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class WorkflowDeletedActivity implements ShouldQueue
{
    public $delay = 5;

    protected $activity_repo;

    public function __construct(ActivityRepository $activity_repo)
    {
        $this->activity_repo = $activity_repo;
    }

    public function handle($event)
    {
        MultiDB::setDb($event->company->db);

        $fields = new stdClass();

        $user_id = $event->event_vars['user_id'] ?? $event->workflow->user_id;

        $fields->user_id = $user_id;
        $fields->company_id = $event->workflow->company_id;
        $fields->activity_type_id = Activity::DELETE_WORKFLOW;

        $this->activity_repo->save($fields, $event->workflow, $event->event_vars);
    }
}
