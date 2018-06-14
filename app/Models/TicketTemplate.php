<?php

namespace App\Models;

class TicketTemplate extends EntityModel
{
    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET_TEMPLATE;
    }
}
