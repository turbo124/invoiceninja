<?php

namespace App\Models;

/**
 * Class ExpenseCategory.
 */
class LookupCompany extends LookupModel
{
    /**
     * @var array
     */
    protected $fillable = [
        'db_server_id',
        'company_id',
    ];

    public function dbServer()
    {
        return $this->belongsTo('App\Models\DbServer');
    }

    public function getDbServer()
    {
        return $this->dbServer->name;
    }
}
