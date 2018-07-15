<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateClientPortalTicketRequest;
use App\Libraries\Utils;
use App\Models\Ticket;
use App\Ninja\Repositories\TicketRepository;
use App\Services\TicketService;
use Illuminate\Support\Facades\Session;


class ClientPortalTicketController extends ClientPortalController
{

    /**
     * @var TicketRepository
     */
    private $ticketRepo;

    /**
     * @var TicketService
     */
    private $ticketService;

    /**
     * ClientPortalTicketController constructor.
     * @param TicketRepository $ticketRepo
     * @param TicketService $ticketService
     */
    public function __construct(TicketRepository $ticketRepo, TicketService $ticketService)
    {
        $this->ticketRepo = $ticketRepo;
        $this->ticketService = $ticketService;
    }

    /**
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $contact = $this->getContact();

        if ((!$contact || (!$contact->account->enable_client_portal)))
            return $this->returnError();


       $account = $contact->account;

        $data = [
            'color' => $account->primary_color ? $account->primary_color : '#0b4d78',
            'account' => $account,
            'title' => trans('texts.tickets'),
            'entityType' => ENTITY_TICKET,
            'columns' => Utils::trans(['ticket_number', 'subject', 'created_at', 'status']),
            'sortColumn' => 0,
        ];

        return response()->view('public_list', $data);

    }

    /**
     * @return bool
     */
    public function ticketDatatable()
    {
        if (! $contact = $this->getContact()) {
            return false;
        }

        return $this->ticketService->getClientDatatable($contact->client->id);
    }

    /**
     * @param $invitationKey
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
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

    /**
     * @param $ticketid
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function view($ticketId)
    {
        if (! $contact = $this->getContact())
            $this->returnError();

        $account = $contact->account;

        $ticket = Ticket::whereAccountId($account->id)
                            ->where('id', '=', Ticket::getPortalPrivateId($ticketId, $account->id))
                            ->where('is_internal', '=', false)
                            ->with('status', 'comments', 'documents')
                            ->first();

        if(!$ticket)
            $this->returnError();

        $data['method'] = 'PUT';
        $data['entityType'] = ENTITY_TICKET;

        $data = array_merge($data, self::getViewModel($contact, $ticket));

        return view('tickets.portal.ticket_view', $data);
    }

    /**
     *
     */
    public function update(UpdateClientPortalTicketRequest $request)
    {
        $contact = $this->getContact();

        $data = $request->input();

        $data['document_ids'] = $request->document_ids;
        $data['contact_key'] = $contact->contact_key;
        $data['method'] = 'PUT';
        $data['entityType'] = ENTITY_TICKET;

        $ticket = $this->ticketService->save($data, $request->entity());

        if(!$ticket)
            $this->returnError();

        $data = array_merge($data, self::getViewModel($contact, $ticket));

        Session::flash('message', trans('texts.updated_ticket'));

        return view('tickets.portal.ticket_view', $data);
    }


    private static function getViewModel($contact, $ticket = false)
    {

        return [
            'color' => $contact->account->primary_color ? $contact->account->primary_color : '#0b4d78',
            'ticket' => $ticket,
            'contact' => $contact,
            'account' => $ticket->account,
            'title' => trans('texts.ticket')." ".$ticket->ticket_number,
            'comments' => $ticket->comments(),
            'url' => 'client/tickets/' . $ticket->public_id,
            'timezone' => $ticket->account->timezone ? $ticket->account->timezone->name : DEFAULT_TIMEZONE,
            'datetimeFormat' => $contact->account->getMomentDateTimeFormat(),
        ];
    }


}
