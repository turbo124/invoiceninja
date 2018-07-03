<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Ninja\Repositories\TicketRepository;

class ClientPortalTicketController extends ClientPortalController
{

    private $ticketRepo;

    public function __construct(TicketRepository $ticketRepo)
    {
        $this->ticketRepo = $ticketRepo;
    }

    public function viewTicket($invitationKey)
    {
        if (! $invitation = $this->ticketRepo->findInvitationByKey($invitationKey)) {
            return $this->returnError(trans('texts.ticket_not_found'));
        }

        $account = $invitation->account;
        $ticket = $invitation->ticket;

        $data = [
            'ticket' => $ticket,
            'account' => $account,
            'ticketInvitation' => $invitation,
        ];


            return view('invited.ticket', $data);

    }

}
