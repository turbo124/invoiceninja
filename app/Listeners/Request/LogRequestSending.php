<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Request;

use Illuminate\Contracts\Queue\ShouldQueue;

class LogRequestSending implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        nlog($event->request->url());
        nlog(json_encode($event->request->data()));
        nlog(json_encode($event->request->headers()));

    }
}
