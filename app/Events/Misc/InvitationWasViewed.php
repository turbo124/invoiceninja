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

namespace App\Events\Misc;

use App\Models\Invoice;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvitationWasViewed.
 */
class InvitationWasViewed
{
    use SerializesModels;

    /**
     * @var Invoice
     */
    public $invitation;

    public $entity;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Invoice $invoice
     */
    public function __construct($entity, $invitation, $company, $event_vars)
    {
        $this->entity = $entity;
        $this->invitation = $invitation;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
