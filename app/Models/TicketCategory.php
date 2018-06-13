<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TicketCategory extends Model
{
    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET_CATEGORY;
    }

    public function statuses()
    {
        return $this->hasMany('App\Models\TicketStatus');
    }

}
