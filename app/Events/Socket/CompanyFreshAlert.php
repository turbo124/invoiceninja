<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Socket;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Class CompanyFreshAlert.
 */
class CompanyFreshAlert
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $company;
    /**
     * Create a new event instance.
     */
    public function __construct($company)
    {
        $this->company = $company;
    }

    /**
     * Broadcast this data
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'it' => 'works'
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('company.'.$this->company->company_key);
    }
}
