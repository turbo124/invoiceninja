<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketRequest;
use App\Ninja\Datatables\TicketDatatable;
use App\Services\TicketService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\View;

class TicketController extends BaseController
{

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_TICKET,
            'datatable' => new TicketDatatable(),
            'title' => trans('texts.tickets'),
        ]);
    }

    public function getDatatable($clientPublicId = null)
    {
        $search = Input::get('sSearch');

        return $this->ticketService->getDatatable($search);
    }

    public function show($publicId)
    {
        Session::reflash();

        return redirect("tickets/$publicId/edit");
    }

    public function edit(TicketRequest $request)
    {
        $ticket = $request->entity();

        $data = array_merge($this->getViewmodel($ticket), [
            'ticket' => $ticket,
            'entity' => $ticket,
            'method' => 'PUT',
            'url' => 'tickets/' . $ticket->public_id,
            'title' => trans('texts.edit_ticket'),
        ]);

        return View::make('tickets.edit', $data);
    }

    /**
     * @return array
     */
    private static function getViewModel($ticket = false)
    {
        return [
            'status' => $ticket->status(),
            'comments' => $ticket->comments(),
            'account' => Auth::user()->account,
        ];
    }

}
