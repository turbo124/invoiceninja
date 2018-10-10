<?php

namespace App\Ninja\Intents\WebApp;

use App\Models\Invoice;
use App\Ninja\Intents\InvoiceIntent;

class CreateInvoiceIntent extends InvoiceIntent
{
    public function process()
    {
        $client = $this->requestClient();
        $clientPublicId = $client ? $client->public_id : null;

        //$invoiceItems = $this->requestInvoiceItems();

        $url = '/invoices/create/'.$clientPublicId.'?';
        $url .= $this->requestFieldsAsString(Invoice::$requestFields);

        $url = rtrim($url, '?');
        $url = rtrim($url, '&');

        return redirect($url);
    }
}
