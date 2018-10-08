<?php

namespace App\Ninja\Import\Harvest;

use League\Fractal\Resource\Item;
use App\Ninja\Import\BaseTransformer;

// vendor
/**
 * Class VendorContactTransformer.
 */
class VendorContactTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        if (! $this->hasVendor($data->vendor)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'vendor_id' => $this->getVendorId($data->vendor),
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'email' => $data->email,
                'phone' => $data->office_phone ?: $data->mobile_phone,
            ];
        });
    }
}
