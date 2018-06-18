<?php

namespace App\Models;

use App\Libraries\Utils;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

class Ticket extends EntityModel
{
    use PresentableTrait;
    use SoftDeletes;

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\TicketPresenter';

    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @var array
     */
    protected $fillable = [
        'client_id',
        'subject',
        'description',
        'private_notes',
        'due_date',
        'ccs',
        'priority_id',
        'agent_id',
        'category_id',
        'is_deleted',
        'is_internal',
        'status_id',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function category()
    {
        return $this->belongsTo('App\Models\TicketCategory');
    }

    /**
     * @return mixed
     */
    public function comments()
    {
        return $this->hasMany('App\Models\TicketComment');
    }

    /**
     * @return mixed
     */
    public function templates()
    {
        return $this->hasMany('App\Models\TicketTemplate');
    }

    /**
     * @return mixed
     */
    public function status()
    {
        return $this->belongsTo('App\Models\TicketStatus');
    }

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TICKET;
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return "/tickets/{$this->public_id}";
    }

    /**
     *
     * @return string
     */
    public function getContactName()
    {
        $contact = Contact::withTrashed()->where('contact_key', '=', $this->contact_key)->first();
        if ($contact && ! $contact->is_deleted) {
            return $contact->getFullName();
        } else {
            return null;
        }
    }

    /**
     *
     * @return string
     */
    public function getPriorityName()
    {
        switch($this->priority_id)
        {
            case TICKET_PRIORITY_LOW:
                return trans('texts.low');
                break;
            case TICKET_PRIORITY_MEDIUM:
                return trans('texts.medium');
                break;
            case TICKET_PRIORITY_HIGH:
                return trans('texts.high');
                break;
        }
    }

    /**
     *
     * @return string
     */
    public function getDueDate()
    {
        if($this->duedate)
            return Utils::fromSqlDateTime($this->duedate);
        else
            return trans('texts.no_due_date');
    }


}
