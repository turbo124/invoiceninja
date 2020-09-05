<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners\Quote;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Models\ClientContact;
use App\Models\QuoteInvitation;
use App\Repositories\ActivityRepository;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class QuoteViewedActivity implements ShouldQueue
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

        $fields->user_id = $event->quote->user_id;
        $fields->company_id = $event->quote->company_id;
        $fields->activity_type_id = Activity::VIEW_QUOTE;
        $fields->client_id = $event->invitation->client_id;
        $fields->client_contact_id = $event->invitation->client_contact_id;
        $fields->invitation_id = $event->invitation->id;
        $fields->quote_id = $event->invitation->quote_id;

        $this->activity_repo->save($fields, $event->quote, $event->event_vars);
    }
}
