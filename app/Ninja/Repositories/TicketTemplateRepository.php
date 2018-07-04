<?php

namespace App\Ninja\Repositories;

use App\Models\TicketTemplate;
use Auth;
use DB;
use Utils;

class TicketTemplateRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\TicketTemplate';
    }

    public function all()
    {
        return TicketTemplate::scope()->get();
    }

    public function find($filter = null, $userId = false)
    {

        $query = DB::table('ticket_templates')
            ->where('ticket_templates.account_id', '=', Auth::user()->account_id)
            ->select(
                'tickets.name',
                'ticket_templates.public_id',
                'ticket_templates.description',
                'tickets.deleted_at',
                'tickets.created_at'
            );

        if ($userId) {
            $query->where('ticket_templates.user_id', '=', $userId);
        }

        return $query;
    }

    public function save($input, $ticketTemplate = false)
    {
        if (! $ticketTemplate) {
            $ticketTemplate = TicketTemplate::createNew();
        }

        $ticketTemplate->fill($input);
        $ticketTemplate->save();


        return $ticketTemplate;
    }

}
