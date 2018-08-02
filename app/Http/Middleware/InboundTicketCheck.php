<?php

namespace App\Http\Middleware;

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

        if($key = request()->MailboxHash){
            //check if we can find the ticket_hash

        }
        elseif($key = request()->To) {
            //check if this is an email to a catch all email address

        }
        return $next($request);
    }
}
