<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Casts;

use App\DataMapper\InvoiceSync;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class InvoiceSyncCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        
        if (is_null($value)) {
            return null; // Return null if the value is null
        }

        $data = json_decode($value, true);

        if (!is_array($data)) {
            return null;
        }

        $is = new InvoiceSync($data);
        // $is->qb_id = $data['qb_id'];
        // $is->email = $data['email'];

        return $is;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        $data = [];

        if(isset($value->qb_id) && strlen($value->qb_id) >= 1)
            $data['qb_id'] = $value->qb_id;

        if (isset($value->email) && $value->email !== null) {
        
            $data['email'] = [
                'body' => $value->email->body ?? '',
                'subject' => $value->email->subject ?? '',
                'template' => $value->email->template ?? '',
                'entity' => $value->email->entity ?? '',
                'entity_id' => $value->email->entity_id ?? '',
                'cc_email' => $value->email->cc_email ?? '',
                'action' => $value->email->action ?? '',
                'ids' => $value->email->ids ?? '',
                'type' => $value->email->email_type ?? '',
            ];

        }

        return [
            $key => json_encode($data)
        ];

    }
}
