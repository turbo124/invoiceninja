<?php

namespace App\Http\Controllers;

use App\Ninja\Datatables\TicketDatatable;
use App\Services\TicketService;
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

}
