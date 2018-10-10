<?php

namespace App\Ninja\Import\Harvest;

use League\Fractal\Resource\Item;
use App\Ninja\Import\BaseTransformer;

/**
 * Class ClientTransformer.
 */
class ClientTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        if ($this->hasClient($data->client_name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'client_name'),
            ];
        });
    }
}
