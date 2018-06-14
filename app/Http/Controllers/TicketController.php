<?php

namespace App\Http\Controllers;

use App\Ninja\Datatables\TicketDatatable;
use Illuminate\Support\Facades\View;

class TicketController extends BaseController
{
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


}
