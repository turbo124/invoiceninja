<?php

namespace App\Models;


class TicketComment extends EntityModel
{
    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET_COMMENT;
    }
}
