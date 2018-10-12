<?php

namespace App\Ninja\Datatables;

use URL;
use Auth;

class ProposalSnippetDatatable extends EntityDatatable
{
    public $entityType = ENTITY_PROPOSAL_SNIPPET;
    public $sortCol = 1;

    public function columns()
    {
        return [
            [
                'name',
                function ($model) {
                    $icon = '<i class="fa fa-'.$model->icon.'"></i>&nbsp;&nbsp;';

                    if (Auth::user()->can('view', [ENTITY_PROPOSAL_SNIPPET, $model])) {
                        return $icon.link_to("proposals/snippets/{$model->public_id}/edit", $model->name)->toHtml();
                    }

                    return $icon.$model->name;
                },
            ],
            [
                'category',
                function ($model) {
                    if (Auth::user()->can('view', [ENTITY_PROPOSAL_CATEGORY, $model])) {
                        return link_to("proposals/categories/{$model->category_public_id}/edit", $model->category ?: ' ')->toHtml();
                    }

                    return $model->category;
                },
            ],
            [
                'content',
                function ($model) {
                    return $this->showWithTooltip(strip_tags($model->content));
                },
            ],
            [
                'private_notes',
                function ($model) {
                    return $this->showWithTooltip($model->private_notes);
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_proposal_snippet'),
                function ($model) {
                    return URL::to("proposals/snippets/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('view', [ENTITY_PROPOSAL_SNIPPET, $model]);
                },
            ],
        ];
    }
}
