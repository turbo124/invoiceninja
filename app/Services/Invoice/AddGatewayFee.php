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

namespace App\Services\Invoice;

use App\DataMapper\InvoiceItem;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use App\Models\Product;

class AddGatewayFee extends AbstractService
{
    public function __construct(private CompanyGateway $company_gateway, private int $gateway_type_id, public Invoice $invoice, private float $amount)
    {
    }

    public function run()
    {

        $gateway_fee = $this->company_gateway->calcGatewayFee($this->amount, $this->gateway_type_id, $this->invoice->uses_inclusive_taxes);

        if (! $gateway_fee || $gateway_fee == 0) {
            return $this->invoice;
        }

        $this->invoice->gateway_fee = $gateway_fee;
        $this->invoice->saveQuietly();

        return $this->invoice;

    }

}
