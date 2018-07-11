<?php

namespace App\Http\Controllers;

use App\Libraries\Utils;
use App\Models\Invitation;
use App\Models\Ticket;
use App\Ninja\Repositories\TicketRepository;
use App\Services\TicketService;


class ClientPortalTicketController extends ClientPortalController
{

    private $ticketRepo;

    private $ticketService;

    public function __construct(TicketRepository $ticketRepo, TicketService $ticketService)
    {
        $this->ticketRepo = $ticketRepo;
        $this->ticketService = $ticketService;
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
            'title' => trans('texts.tickets'),
            'entityType' => ENTITY_TICKET,
            'columns' => Utils::trans(['ticket_number', 'subject', 'created_at', 'status']),
            'sortColumn' => 0,
        ];

        return response()->view('public_list', $data);

    }

    public function ticketDatatable()
    {
        if (! $contact = $this->getContact()) {
            return false;
        }

        return $this->ticketService->getClientDatatable($contact->client->id);
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

    public function view($ticketid)
    {
        if (! $contact = $this->getContact())
            $this->returnError();

        $account = $contact->account;

        $ticket = Ticket::whereAccountId($account->id)
                            ->where('id', '=', Ticket::getPrivateId($ticketid))
                            ->with('status', 'comments', 'documents')
                            ->first();


        $data = [
            'color' => $account->primary_color ? $account->primary_color : '#0b4d78',
            'ticket' => $ticket,
            'contact' => $contact,
            'account' => $account,
            //'title' => trans('texts.ticket')." ".$ticket->ticket_number,
            'entityType' => ENTITY_TICKET,
        ];


        return view('tickets.portal.ticket_view', $data);
    }

}
