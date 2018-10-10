<?php

namespace App\Ninja\Import\Pancake;

use League\Fractal\Resource\Item;
use App\Ninja\Import\BaseTransformer;

/**
 * Class PaymentTransformer.
 */
class PaymentTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return Item
     */
    public function transform($data)
    {
        return new Item($data, function ($data) {
            return [
                'amount' => (float) $data->amount_paid,
                'payment_date_sql' => $data->create_date,
                'client_id' => $data->client_id,
                'invoice_id' => $data->invoice_id,
            ];
        });
    }
}
