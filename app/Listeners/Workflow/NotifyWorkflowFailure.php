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

use App\Events\Workflow\WorkflowRunFailed;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyWorkflowFailure implements ShouldQueue
{
    public function handle(WorkflowRunFailed $event): void
    {
        $run = $event->run;
        $workflow = $run->workflow;
        $step = $event->step;
        $message = $event->message;

        // Log the failure
        nlog("Workflow '{$workflow->name}' (run #{$run->id}) failed at step '{$step['name']}': {$message}");

        // Notify the workflow creator via existing notification system
        $user = $run->user;

        if ($user) {
            // Use the existing nlog-based notification for now.
            // This can be extended to use Laravel notifications, Slack, email etc.
            nlog("Notifying user {$user->id} about workflow failure: {$workflow->name}");
        }
    }
}
