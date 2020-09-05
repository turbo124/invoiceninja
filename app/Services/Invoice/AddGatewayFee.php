<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\DataMapper\InvoiceItem;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Models\Client;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Services\Client\ClientService;
use App\Services\Payment\PaymentService;
use App\Utils\Traits\GeneratesCounter;

class AddGatewayFee extends AbstractService
{
    private $company_gateway;

    private $invoice;

    private $amount;

    public function __construct(CompanyGateway $company_gateway, Invoice $invoice, float $amount)
    {
        $this->company_gateway = $company_gateway;

        $this->invoice = $invoice;

        $this->amount = $amount;
    }

    public function run()
    {
        $gateway_fee = round($this->company_gateway->calcGatewayFee($this->amount), $this->invoice->client->currency()->precision);

        $this->cleanPendingGatewayFees();

        if ($gateway_fee > 0) {
            return $this->processGatewayFee($gateway_fee);
        }

        return $this->processGatewayDiscount($gateway_fee);
    }

    private function cleanPendingGatewayFees()
    {
        $invoice_items = $this->invoice->line_items;

        $invoice_items = collect($invoice_items)->filter(function ($item) {
            return $item->type_id != '3';
        });

        $this->invoice->line_items = $invoice_items;

        return $this;
    }

    private function processGatewayFee($gateway_fee)
    {
        $invoice_item = new InvoiceItem;
        $invoice_item->type_id = '3';
        $invoice_item->product_key = ctrans('texts.surcharge');
        $invoice_item->notes = ctrans('texts.online_payment_surcharge');
        $invoice_item->quantity = 1;
        $invoice_item->cost = $gateway_fee;

        if ($fees_and_limits = $this->company_gateway->getFeesAndLimits()) {
            $invoice_item->tax_rate1 = $fees_and_limits->fee_tax_rate1;
            $invoice_item->tax_rate2 = $fees_and_limits->fee_tax_rate2;
            $invoice_item->tax_rate3 = $fees_and_limits->fee_tax_rate3;
        }

        $invoice_items = $this->invoice->line_items;
        $invoice_items[] = $invoice_item;

        $this->invoice->line_items = $invoice_items;

        /**Refresh Invoice values*/
        $this->invoice = $this->invoice->calc()->getInvoice();

        /*Update client balance*/ // don't increment until we have process the payment!
        //$this->invoice->client->service()->updateBalance($gateway_fee)->save();
        //$this->invoice->ledger()->updateInvoiceBalance($gateway_fee, $notes = 'Gateway fee adjustment');

        return $this->invoice;
    }

    private function processGatewayDiscount($gateway_fee)
    {
        $invoice_item = new InvoiceItem;
        $invoice_item->type_id = '3';
        $invoice_item->product_key = ctrans('texts.discount');
        $invoice_item->notes = ctrans('texts.online_payment_discount');
        $invoice_item->quantity = 1;
        $invoice_item->cost = $gateway_fee;

        if ($fees_and_limits = $this->company_gateway->getFeesAndLimits()) {
            $invoice_item->tax_rate1 = $fees_and_limits->fee_tax_rate1;
            $invoice_item->tax_rate2 = $fees_and_limits->fee_tax_rate2;
            $invoice_item->tax_rate3 = $fees_and_limits->fee_tax_rate3;
        }

        $invoice_items = $this->invoice->line_items;
        $invoice_items[] = $invoice_item;

        $this->invoice->line_items = $invoice_items;

        $this->invoice = $this->invoice->calc()->getInvoice();

        // $this->invoice->client->service()->updateBalance($gateway_fee)->save();

        // $this->invoice->ledger()->updateInvoiceBalance($gateway_fee, $notes = 'Discount fee adjustment');

        return $this->invoice;
    }
}
