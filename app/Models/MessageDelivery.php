<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $thread_id
 * @property int $company_id
 * @property int|null $client_id
 * @property string $channel
 * @property string $status
 * @property string|null $reason_code
 * @property string|null $reason_detail
 * @property string|null $provider_message_id
 * @property bool $retryable
 * @property array|null $payload_ref
 * @property array $events
 */
class MessageDelivery extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'events' => 'array',
        'payload_ref' => 'array',
        'retryable' => 'bool',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
