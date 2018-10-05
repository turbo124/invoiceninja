<?php

namespace App\Ninja\Datatables;

use URL;
use Auth;

class ProposalCategoryDatatable extends EntityDatatable
{
    public $entityType = ENTITY_PROPOSAL_CATEGORY;
    public $sortCol = 1;

    public function columns()
    {
        return [
            [
                'name',
                function ($model) {
                    if (Auth::user()->can('view', [ENTITY_PROPOSAL_CATEGORY, $model])) {
                        return link_to("proposals/categories/{$model->public_id}/edit", $model->name)->toHtml();
                    }

                    return $model->name;
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_category'),
                function ($model) {
                    return URL::to("proposals/categories/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('view', [ENTITY_PROPOSAL_CATEGORY, $model]);
                },
            ],
        ];
    }
}
