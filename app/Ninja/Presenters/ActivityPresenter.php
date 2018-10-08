<?php

namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;

class ActivityPresenter extends Presenter
{
    public function createdAt()
    {
        return Utils::timestampToDateTimeString(strtotime($this->entity->created_at));
    }

    public function createdAtDate()
    {
        return Utils::dateToString($this->entity->created_at);
    }

    public function user()
    {
        if ($this->entity->is_system) {
            return '<i>'.trans('texts.system').'</i>';
        }

        return $this->entity->user->getDisplayName();
    }

    public function notes()
    {
        if ($this->entity->notes) {
            return trans('texts.notes_'.$this->entity->notes);
        } elseif (in_array($this->entity->activity_type_id, [ACTIVITY_TYPE_EMAIL_INVOICE, ACTIVITY_TYPE_EMAIL_QUOTE])) {
            return trans('texts.initial_email');
        }

        return '';
    }
}
