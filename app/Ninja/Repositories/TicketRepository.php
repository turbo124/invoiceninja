<?php

namespace App\Ninja\Repositories;

use App\Models\Ticket;
use App\Models\Invoice;
use App\Models\ProposalTemplate;
use App\Models\ProposalInvitation;
use Auth;
use DB;
use Utils;

class TicketRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Ticket';
    }

    public function all()
    {
        return Ticket::scope()->get();
    }

    public function find($filter = null, $userId = false)
    {
        $query = DB::table('tickets')
                ->where('tickets.account_id', '=', Auth::user()->account_id)
                ->leftjoin('clients', 'clients.id', '=', 'tickets.client_id')
                ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                ->where('clients.deleted_at', '=', null)
                ->where('contacts.deleted_at', '=', null)
                ->where('contacts.is_primary', '=', true);

        $this->applyFilters($query, ENTITY_TICKET);

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%');
            });
        }

        if ($userId) {
            $query->where('tickets.user_id', '=', $userId);
        }

        return $query;
    }

    public function save($input, $ticket = false)
    {
        if (! $ticket) {
            $ticket = Ticket::createNew();
        }

        $ticket->fill($input);

        $ticket->save();

        return $ticket;
    }

}
