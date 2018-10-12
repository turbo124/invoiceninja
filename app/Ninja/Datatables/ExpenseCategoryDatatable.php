<?php

namespace App\Ninja\Datatables;

use URL;
use Auth;

class ExpenseCategoryDatatable extends EntityDatatable
{
    public $entityType = ENTITY_EXPENSE_CATEGORY;
    public $sortCol = 1;

    public function columns()
    {
        return [
            [
                'name',
                function ($model) {
                    if (Auth::user()->can('edit', [ENTITY_EXPENSE_CATEGORY, $model])) {
                        return link_to("expense_categories/{$model->public_id}/edit", $model->category)->toHtml();
                    }

                    return $model->category;
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
                    return URL::to("expense_categories/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('edit', [ENTITY_EXPENSE_CATEGORY, $model]);
                },
            ],
        ];
    }
}
