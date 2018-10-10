<?php

namespace App\Models;

/**
 * Class ExpenseCategory.
 */
class LookupContact extends LookupModel
{
    /**
     * @var array
     */
    protected $fillable = [
        'lookup_account_id',
        'contact_key',
    ];
}
