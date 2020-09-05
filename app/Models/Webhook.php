<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Models\Filterable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Webhook extends BaseModel
{
    use SoftDeletes;
    use Filterable;

    const EVENT_CREATE_CLIENT = 1;
    const EVENT_CREATE_INVOICE = 2;
    const EVENT_CREATE_QUOTE = 3;
    const EVENT_CREATE_PAYMENT = 4;
    const EVENT_CREATE_VENDOR = 5;
    const EVENT_UPDATE_QUOTE = 6;
    const EVENT_DELETE_QUOTE = 7;
    const EVENT_UPDATE_INVOICE = 8;
    const EVENT_DELETE_INVOICE = 9;
    const EVENT_UPDATE_CLIENT = 10;
    const EVENT_DELETE_CLIENT = 11;
    const EVENT_DELETE_PAYMENT = 12;
    const EVENT_UPDATE_VENDOR = 13;
    const EVENT_DELETE_VENDOR = 14;
    const EVENT_CREATE_EXPENSE = 15;
    const EVENT_UPDATE_EXPENSE = 16;
    const EVENT_DELETE_EXPENSE = 17;
    const EVENT_CREATE_TASK = 18;
    const EVENT_UPDATE_TASK = 19;
    const EVENT_DELETE_TASK = 20;
    const EVENT_APPROVE_QUOTE = 21;

    public static $valid_events = [
        self::EVENT_CREATE_CLIENT,
        self::EVENT_CREATE_PAYMENT,
        self::EVENT_CREATE_QUOTE,
        self::EVENT_CREATE_INVOICE,
        self::EVENT_CREATE_VENDOR,
        self::EVENT_CREATE_EXPENSE,
        self::EVENT_CREATE_TASK,
    ];

    protected $fillable = [
        'target_url',
        'format',
        'event_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
