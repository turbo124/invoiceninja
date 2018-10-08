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
            if (! empty($data->payment_date)) {
                $paymentDate = $this->getDate($data, 'payment_date');
            } else {
                $paymentDate = isset($data->invoice_date) ? $data->invoice_date : null;
            }

            return [
                'amount' => $this->getFloat($data, 'paid'),
                'payment_date_sql' => $paymentDate,
                'transaction_reference' => $this->getString($data, 'payment_reference'),
                'client_id' => $data->client_id,
                'invoice_id' => $data->invoice_id,
            ];
        });
    }
}
