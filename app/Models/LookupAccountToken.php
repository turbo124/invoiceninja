<?php

namespace App\Models;

/**
 * Class ExpenseCategory.
 */
class LookupAccountToken extends LookupModel
{
    /**
     * @var array
     */
    protected $fillable = [
        'lookup_account_id',
        'token',
    ];
}
