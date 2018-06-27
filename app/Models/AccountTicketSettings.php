<?php

namespace App\Models;

use Eloquent;

/**
 * Class AccountTicketSettings.
 */
class AccountTicketSettings extends Eloquent
{
    /**
     * @var array
     */
    protected $fillable = [
        'local_part',
        'from_name',
        'allow_contact_upload_documents',
        'postmark_api_token',
    ];



}
