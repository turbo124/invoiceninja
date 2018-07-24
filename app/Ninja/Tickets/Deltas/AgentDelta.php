<?php

namespace App\Ninja\Tickets\Deltas;

/**
 *
 * Class AgentDelta
 * @package App\Ninja\Tickets\Deltas
 *
 * Handles attribute specific events on Model change.
 *
 */

class AgentDelta
{
   public static function agentTicketChange(Ticket $ticket, Ticket $originalTicket)
   {
       $accountTicketSettings = $ticket->account->account_ticket_settings;

       if($accountTicketSettings->alert_ticket_assign_agent) {}
           //notify agent they have a new ticket

   }


}
