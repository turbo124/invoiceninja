<?php

namespace App\Ninja\Repositories;

use App\Models\Document;
use App\Models\Ticket;
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
            ->leftJoin('ticket_statuses', 'ticket_statuses.id', '=', 'tickets.status_id')
            ->where('clients.deleted_at', '=', null)
            ->where('contacts.deleted_at', '=', null)
            ->where('contacts.is_primary', '=', true)
            ->select(
                'tickets.public_id as ticket_number',
                'tickets.public_id',
                'tickets.user_id',
                'tickets.deleted_at',
                'tickets.created_at',
                'tickets.is_deleted',
                'tickets.private_notes',
                'tickets.subject',
                'ticket_statuses.name as status',
                'tickets.contact_key',
                DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                'clients.user_id as client_user_id',
                'clients.public_id as client_public_id'
            );

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

        if (! empty($input['document_ids'])) {
            $document_ids = array_map('intval', $input['document_ids']);

            foreach ($document_ids as $document_id) {
                $document = Document::scope($document_id)->first();
                if ($document && Auth::user()->can('edit', $document)) {

                    $document->ticket_id = $ticket->id;
                    $document->save();
                }
            }
        }

        return $ticket;
    }

}
