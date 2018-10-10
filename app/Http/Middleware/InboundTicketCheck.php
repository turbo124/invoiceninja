<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\LookupAccount;
use App\Models\LookupTicketInvitation;
use App\Ninja\Tickets\Inbound\InboundTicketFactory;

class InboundTicketCheck
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (! config('ninja.multi_db_enabled')) {
            return $next($request);
        }

        $inbound = new InboundTicketFactory($request->input());

        if ($inbound->mailboxHash()) {
            //check if we can find the ticket_hash
            LookupTicketInvitation::setServerByField('ticket_hash', $inbound->mailboxHash());
        } elseif ($inbound->to()) {
            //otherwise check if we can find the unique localpart.
            $parts = explode('@', $inbound->to());

            LookupAccount::setServerByField('support_email_local_part', $parts[0]);
        }

        /*
         * If we don't trigger any of these if blocks, we need to die()
         */

        return $next($request);
    }
}
