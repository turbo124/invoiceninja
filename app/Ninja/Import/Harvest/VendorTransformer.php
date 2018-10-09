<?php

namespace App\Ninja\Import\Harvest;

use League\Fractal\Resource\Item;
use App\Ninja\Import\BaseTransformer;

// vendor
/**
 * Class VendorTransformer.
 */
class VendorTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        if ($this->hasVendor($data->vendor_name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $data->vendor_name,
            ];
        });
    }
}
