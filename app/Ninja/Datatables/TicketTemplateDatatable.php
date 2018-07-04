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
            [
                'name',
                function ($model) {
                    return link_to("ticket_templates/{$model->public_id}/edit", $model->name ?: '')->toHtml();
                },
            ],
            [
                'description',
                function ($model) {
                    return $model->description;
                }
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit'),
                function ($model) {
                    return URL::to("ticket_templates/{$model->public_id}/edit");
                },
                function ($model) {
                    return true;
                },
            ],
            [
                trans('texts.edit'),
                function ($model) {
                    return URL::to("ticket_templates/{$model->public_id}/edit");
                },
                function ($model) {
                    return true;
                },
            ],
            [
                trans('texts.edit'),
                function ($model) {
                    return URL::to("ticket_templates/{$model->public_id}/edit");
                },
                function ($model) {
                    return true;
                },
            ],
        ];
    }
}
