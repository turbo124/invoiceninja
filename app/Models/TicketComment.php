<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET_COMMENT;
    }
}
