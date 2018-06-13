<?php

namespace App\Ninja\Datatables;

use Auth;
use URL;
use Utils;

class TicketTemplateDatatable extends EntityDatatable
{
    public $entityType = ENTITY_TICKET_TEMPLATE;
    public $sortCol = 1;

    public function columns()
    {
        return [
        ];
    }

    public function actions()
    {
        return [];
    }
}
