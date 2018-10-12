<?php

namespace App\Ninja\Import\CSV;

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
                'amount' => $this->getFloat($data, 'paid'),
                'payment_date_sql' => isset($data->invoice_date) ? $data->invoice_date : null,
                'client_id' => $data->client_id,
                'invoice_id' => $data->invoice_id,
            ];
        });
    }
}
