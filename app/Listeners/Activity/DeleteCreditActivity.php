<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Activity;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class DeleteCreditActivity implements ShouldQueue
{
    protected $activity_repo;

    /**
     * Create the event listener.
     */
    public function __construct(ActivityRepository $activity_repo)
    {
        $this->activity_repo = $activity_repo;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     */
    public function handle($event): void
    {
        MultiDB::setDb($event->company->db);

        $fields = new stdClass();

        $user_id = isset($event->event_vars['user_id']) ? $event->event_vars['user_id'] : $event->credit->user_id;

        $fields->client_id = $event->credit->client_id;
        $fields->credit_id = $event->credit->id;
        $fields->user_id = $user_id;
        $fields->company_id = $event->credit->company_id;
        $fields->activity_type_id = Activity::DELETE_CREDIT;

        $this->activity_repo->save($fields, $event->credit, $event->event_vars);
    }
}
