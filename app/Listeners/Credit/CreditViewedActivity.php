<?php
/**
 * Credit Ninja (https://creditninja.com).
 *
 * @link https://github.com/creditninja/creditninja source repository
 *
 * @copyright Copyright (c) 2020. Credit Ninja LLC (https://creditninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners\Credit;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Models\ClientContact;
use App\Models\CreditInvitation;
use App\Repositories\ActivityRepository;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreditViewedActivity implements ShouldQueue
{
    protected $activity_repo;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(ActivityRepository $activity_repo)
    {
        $this->activity_repo = $activity_repo;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        MultiDB::setDb($event->company->db);

        $fields = new \stdClass;

        $fields->user_id = $event->invitation->user_id;
        $fields->company_id = $event->invitation->company_id;
        $fields->activity_type_id = Activity::VIEW_CREDIT;
        $fields->client_id = $event->invitation->credit->client_id;
        $fields->client_contact_id = $event->invitation->client_contact_id;
        $fields->invitation_id = $event->invitation->id;
        $fields->credit_id = $event->invitation->credit_id;

        $this->activity_repo->save($fields, $event->invitation->credit, $event->event_vars);
    }
}
