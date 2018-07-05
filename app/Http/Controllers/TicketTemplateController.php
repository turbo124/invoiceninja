<?php

namespace App\Http\Controllers;


use App\Http\Requests\CreateTicketTemplateRequest;
use App\Libraries\Utils;
use App\Models\TicketTemplate;
use App\Services\TicketService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;

class TicketTemplateController extends BaseController
{

    protected $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    public function index()
    {
        return Redirect::to('settings/' . ACCOUNT_TICKETS);
    }

    public function getDatatable($clientPublicId = null)
    {
        return $this->ticketService->getTemplateDatatable();
    }

    public function show($publicId)
    {
        Session::reflash();

        return Redirect::to("ticket_templates/$publicId/edit");
    }

    public function edit($publicId)
    {
        $ticketTemplate = TicketTemplate::scope($publicId)->firstOrFail();

        $data = self::getViewModel($ticketTemplate);

        return View::make('accounts.ticket_templates', $data);
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    public function store(CreateTicketTemplateRequest $request)
    {
        return $this->save();
    }

    /**
     * Displays the form for account creation.
     */
    public function create()
    {

        $account = Auth::user()->account;

        $data = self::getViewModel();
        $data['method'] = 'POST';
        $data['title'] = trans('texts.add_template');

        return View::make('accounts.ticket_templates', $data);

    }

    private function getViewModel($ticketTemplate)
    {
        $user = Auth::user();
        $account = $user->account;

        return [
            'account' => $account,
            'user' => $user,
            'config' => false,
            'ticket_templates' => $ticketTemplate,

        ];
    }

    public function bulk()
    {

    }


    public function save($ticketTemplatePublicId = false)
    {
        if ($ticketTemplatePublicId) {
            $ticketTemplate = TicketTemplate::scope($ticketTemplatePublicId)->firstOrFail();
        } else {
            $ticketTemplate = TicketTemplate::createNew();
        }

        $ticketTemplate->name = Input::get('name');
        $ticketTemplate->description = Input::get('description');
        $ticketTemplate->save();

        $message = $ticketTemplatePublicId ? trans('texts.updated_ticket_template') : trans('texts.created_ticket_template');
        Session::flash('message', $message);

        return Redirect::to('settings/' . ACCOUNT_TICKETS);
    }


}
