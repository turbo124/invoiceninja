<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TicketRelation extends Model
{
    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET_RELATION;
    }
}
