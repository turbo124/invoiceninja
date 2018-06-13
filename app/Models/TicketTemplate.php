<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TicketTemplate extends Model
{
    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET_TEMPLATE;
    }
}
