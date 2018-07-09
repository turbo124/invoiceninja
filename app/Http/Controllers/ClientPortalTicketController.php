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

    public function index()
    {
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $account = $contact->account;

        if (! $account->enable_client_portal) {
            return $this->returnError();
        }

        $color = $account->primary_color ? $account->primary_color : '#0b4d78';

        $data = [
            'color' => $color,
            'account' => $account,
            'title' => trans('texts.credits'),
            'entityType' => ENTITY_CREDIT,
            'columns' => Utils::trans(['credit_date', 'credit_amount', 'credit_balance', 'notes']),
            'sortColumn' => 0,
        ];

        return response()->view('public_list', $data);

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
