<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\ProductIntent;

class CreateProductIntent extends ProductIntent
{
    public function process()
    {
        $url = '/products/create';

        //$url = '/invoices/create/' . $clientPublicId . '?';
        //$url .= $this->requestFieldsAsString(Invoice::$requestFields);

        return redirect($url);
    }
}
