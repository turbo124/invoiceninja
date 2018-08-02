<?php

namespace App\Http\Middleware;

use App\Ninja\Tickets\Inbound\InboundTicketFactory;
use Closure;
use Illuminate\Http\Request;



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

        $inbound = new InboundTicketFactory($request->input());

        if($inbound->mailboxHash()){
            //check if we can find the ticket_hash
            //if exists - process ticket.
        }
        elseif($inbound->to()) {
            //check if this is an email to a catch all email address
            //if exists - fire a new ticket creation
            //new tickets can only be accepted this way from EXISTING contacts, so we need to ensure it is from a
            //existing contact. $inbound->fromFull
        }
        return $next($request);
    }
}
