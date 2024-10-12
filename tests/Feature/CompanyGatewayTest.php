<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\DataMapper\InvoiceItem;
use Tests\TestCase;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\CompanyGateway;
use App\Jobs\Invoice\CheckGatewayFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 *   App\Models\CompanyGateway
 */
class CompanyGatewayTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    // use RefreshDatabase;
    private int $iterator_tests = 20;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (! config('ninja.testvars.stripe')) {
            $this->markTestSkipped('Skip test no company gateways installed');
        }
    }

    private function stubInvoice()
    {
        $item = new InvoiceItem;
        $item->cost = rand(10, 1000);
        $item->quantity = 2;

        $items = array_values([$item]);

        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'line_items' => $items,
            'status_id' => 1,
        ]);

        $i->calc()->getInvoice();
        $i->service()->markSent()->save();

        return $i;
    }

    private function pushThroughGateway(Invoice $i)
    {
        CompanyGateway::query()->where('company_id', $this->company->id)->forceDelete();

        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = 0.3;
        $data[1]['fee_percent'] = 3.2;
        $data[1]['fee_tax_name1'] = '';
        $data[1]['fee_tax_rate1'] = 0;
        $data[1]['fee_tax_name2'] = '';
        $data[1]['fee_tax_rate2'] = 0;
        $data[1]['fee_tax_name3'] = '';
        $data[1]['fee_tax_rate3'] = 0;
        $data[1]['adjust_fee_percent'] = false;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(config('ninja.testvars.stripe'));
        $cg->fees_and_limits = $data;
        $cg->save();

        $fee_invoice = $i->service()->addGatewayFee($cg, GatewayType::CREDIT_CARD, $i->balance)->save();

        $this->assertNotNull($this->invoice->gateway_fee);

        // nlog($fee_invoice->balance);
        // nlog($fee_invoice->gateway_fee);
        
        $payment_hash = PaymentHash::create([
            'hash' => \Illuminate\Support\Str::random(32),
            'data' => [
                'amount_with_fee' => round($fee_invoice->balance + $fee_invoice->gateway_fee,2),
                'invoices' => [
                    [
                        'invoice_id' => $fee_invoice->hashed_id,
                        'amount' => $fee_invoice->balance,
                        'invoice_number' => $fee_invoice->number,
                        'pre_payment' => $fee_invoice->is_proforma,
                    ],
                ],
            ],
            'fee_total' => $fee_invoice->gateway_fee,
            'fee_invoice_id' => $fee_invoice->id,
        ]);
     
        // nlog($payment_hash->amount_with_fee());

        $cg->driver($fee_invoice->client)
                ->setPaymentHash($payment_hash)
                ->confirmGatewayFee();

        $fee_invoice = $fee_invoice->fresh();

        return [$fee_invoice, $payment_hash];
    }

    public function testEdgeCase1()
    {

        $item = new InvoiceItem();
        $item->cost = 65;
        $item->quantity = 2;
        $item->is_amount_discount = false;
        $item->discount = 0;

        $items = array_values([$item]);

        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'line_items' => $items,
            'status_id' => 1,
            'discount' => 1,
            'is_amount_discount' => false,
            'tax_name1' => 'GST',
            'tax_rate1' => 10,
            'tax_name2' => 'VAT',
            'tax_rate2' => 17.5,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'uses_inclusive_taxes' => false,
        ]);

        $i->calc()->getInvoice();
        $i->service()->markSent()->save();
        
        $this->assertEquals(164.09, $i->amount);
        $this->assertEquals(164.09, $i->balance);
        
        $arr = $this->pushThroughGateway($i);
        
        $fee_invoice = $arr[0];
        $payment_hash = $arr[1];

        $this->assertEquals(5.55, round($fee_invoice->gateway_fee,2));
        $this->assertEquals($fee_invoice->amount, $payment_hash->amount_with_fee());

    }

    public function testNewGatewayFeePathAmountDiscounts()
    {

        for($x=0; $x<$this->iterator_tests; $x++)
        {
            $i = $this->stubInvoice();
            $i->uses_inclusive_taxes = false;
            $i->is_amount_discount = true;
            $i->discount = rand(1,99);
            $i->calc()->getInvoice();

            // nlog("------");
            // nlog($i->discount);
            // nlog($i->is_amount_discount ? 'amount' : 'percent');
            // nlog($i->balance);
            // nlog($i->total_taxes);
            // nlog($i->gateway_fee);
            // nlog($i->line_items);
            // nlog($i->tax_rate1);
            // nlog($i->tax_rate2);
            // nlog($i->tax_rate3);
            // nlog("======");

            $return_array = $this->pushThroughGateway($i);

            $fee_invoice = $return_array[0];
            $payment_hash = $return_array[1];

            $this->assertEquals($fee_invoice->amount, $payment_hash->amount_with_fee());
        }
    }

    public function testNewGatewayFeePathInclusiveTaxes()
    {

        for($x=0; $x<$this->iterator_tests; $x++)
        {
            $i = $this->stubInvoice();
            $i->uses_inclusive_taxes = true;
            $i->calc()->getInvoice();

            // nlog("------");
            // nlog($i->discount);
            // nlog($i->is_amount_discount ? 'amount' : 'percent');
            // nlog($i->balance);
            // nlog($i->total_taxes);
            // nlog($i->gateway_fee);
            // nlog($i->line_items);
            // nlog($i->tax_rate1);
            // nlog($i->tax_rate2);
            // nlog($i->tax_rate3);
            // nlog("======");

            $return_array = $this->pushThroughGateway($i);

            $fee_invoice = $return_array[0];
            $payment_hash = $return_array[1];

            $this->assertEquals($fee_invoice->amount, $payment_hash->amount_with_fee());
        }
    }

    public function testNewGatewayFeePath()
    {

        for($x=0; $x<$this->iterator_tests; $x++)
        {
            $i = $this->stubInvoice();

            // nlog("------");
            // nlog($i->discount);
            // nlog($i->is_amount_discount ? 'amount' : 'percent');
            // nlog($i->balance);
            // nlog($i->total_taxes);
            // nlog($i->gateway_fee);
            // nlog($i->line_items);
            // nlog($i->tax_rate1);
            // nlog($i->tax_rate2);
            // nlog($i->tax_rate3);
            // nlog("======");

            $return_array = $this->pushThroughGateway($i);

            $fee_invoice = $return_array[0];
            $payment_hash = $return_array[1];

            $this->assertEquals($fee_invoice->amount, $payment_hash->amount_with_fee());
        }
    }


    

    public function testGatewayExists()
    {
        $company_gateway = CompanyGateway::first();
        $this->assertNotNull($company_gateway);
    }

    public function testSetConfigFields()
    {
        $company_gateway = CompanyGateway::first();

        $this->assertNotNull($company_gateway->getConfig());

        $company_gateway->setConfigField('test', 'test');

        $this->assertEquals('test', $company_gateway->getConfigField('test'));

        $company_gateway->setConfigField('signatureKey', 'hero');

        $this->assertEquals('hero', $company_gateway->getConfigField('signatureKey'));

    }

    public function testFeesAndLimitsExists()
    {
        $data = [];
        $data[1]['min_limit'] = 234;
        $data[1]['max_limit'] = 65317;
        $data[1]['fee_amount'] = 0.00;
        $data[1]['fee_percent'] = 0.000;
        $data[1]['fee_tax_name1'] = '';
        $data[1]['fee_tax_rate1'] = '';
        $data[1]['fee_tax_name2'] = '';
        $data[1]['fee_tax_rate2'] = '';
        $data[1]['fee_tax_name3'] = '';
        $data[1]['fee_tax_rate3'] = 0;
        $data[1]['adjust_fee_percent'] = true;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(config('ninja.testvars.stripe'));
        $cg->fees_and_limits = $data;
        $cg->save();

        $this->assertNotNull($cg->fees_and_limits);

        $properties = array_keys(get_object_vars($cg->fees_and_limits));
        $fees_and_limits = $cg->fees_and_limits->{$properties[0]};

        $this->assertNotNull($fees_and_limits);

        //confirm amount filtering works
        $amount = 100;

        $this->assertFalse($this->checkSieve($cg, $amount));

        $amount = 235;

        $this->assertTrue($this->checkSieve($cg, $amount));

        $amount = 70000;

        $this->assertFalse($this->checkSieve($cg, $amount));
    }

    public function checkSieve($cg, $amount)
    {
        if (isset($cg->fees_and_limits)) {
            $properties = array_keys(get_object_vars($cg->fees_and_limits));
            $fees_and_limits = $cg->fees_and_limits->{$properties[0]};
        } else {
            $passes = true;
        }

        if ((property_exists($fees_and_limits, 'min_limit')) && $fees_and_limits->min_limit !== null && $amount < $fees_and_limits->min_limit) {
            $passes = false;
        } elseif ((property_exists($fees_and_limits, 'max_limit')) && $fees_and_limits->max_limit !== null && $amount > $fees_and_limits->max_limit) {
            $passes = false;
        } else {
            $passes = true;
        }

        return $passes;
    }

    public function testFeesAreAppendedToInvoice() //after refactor this may be redundant
    {
        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = 1.00;
        $data[1]['fee_percent'] = 0.000;
        $data[1]['fee_tax_name1'] = '';
        $data[1]['fee_tax_rate1'] = 0;
        $data[1]['fee_tax_name2'] = '';
        $data[1]['fee_tax_rate2'] = 0;
        $data[1]['fee_tax_name3'] = '';
        $data[1]['fee_tax_rate3'] = 0;
        $data[1]['adjust_fee_percent'] = false;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(config('ninja.testvars.stripe'));
        $cg->fees_and_limits = $data;
        $cg->save();

        $balance = $this->invoice->balance;

        $this->invoice = $this->invoice->service()->addGatewayFee($cg, GatewayType::CREDIT_CARD, $this->invoice->balance)->save();
        $this->invoice = $this->invoice->calc()->getInvoice();

        $this->assertNotNull($this->invoice->gateway_fee);
        // $items = $this->invoice->line_items;
        // $this->assertEquals(($balance + 1), $this->invoice->balance);
    }

    public function testGatewayFeesAreClearedAppropriately()
    {
        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = 1.00;
        $data[1]['fee_percent'] = 0.000;
        $data[1]['fee_tax_name1'] = '';
        $data[1]['fee_tax_rate1'] = 0;
        $data[1]['fee_tax_name2'] = '';
        $data[1]['fee_tax_rate2'] = 0;
        $data[1]['fee_tax_name3'] = '';
        $data[1]['fee_tax_rate3'] = 0;
        $data[1]['adjust_fee_percent'] = false;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(config('ninja.testvars.stripe'));
        $cg->fees_and_limits = $data;
        $cg->save();

        $balance = $this->invoice->balance;
        $wiped_balance = $balance;

        $this->invoice = $this->invoice->service()->addGatewayFee($cg, GatewayType::CREDIT_CARD, $this->invoice->balance)->save();
        $this->invoice = $this->invoice->calc()->getInvoice();


        $this->assertNotNull($this->invoice->gateway_fee);

        // $items = $this->invoice->line_items;

        // $this->assertEquals(($balance + 1), $this->invoice->balance);

        // (new CheckGatewayFee($this->invoice->id, $this->company->db))->handle();

        // $i = Invoice::withTrashed()->find($this->invoice->id);

        // $this->assertEquals($wiped_balance, $i->balance);
    }

    public function testMarkPaidAdjustsGatewayFeeAppropriately()
    {
        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = 1.00;
        $data[1]['fee_percent'] = 0.000;
        $data[1]['fee_tax_name1'] = '';
        $data[1]['fee_tax_rate1'] = 0;
        $data[1]['fee_tax_name2'] = '';
        $data[1]['fee_tax_rate2'] = 0;
        $data[1]['fee_tax_name3'] = '';
        $data[1]['fee_tax_rate3'] = 0;
        $data[1]['adjust_fee_percent'] = false;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(config('ninja.testvars.stripe'));
        $cg->fees_and_limits = $data;
        $cg->save();

        $balance = $this->invoice->balance;
        $wiped_balance = $balance;

        $this->invoice = $this->invoice->service()->addGatewayFee($cg, GatewayType::CREDIT_CARD, $this->invoice->balance)->save();
        $this->invoice = $this->invoice->calc()->getInvoice();

        $this->assertNotNull($this->invoice->gateway_fee);


        $payment_hash = PaymentHash::create([
            'hash' => \Illuminate\Support\Str::random(32),
            'data' => [
                'amount_with_fee' => $this->invoice->balance + $this->invoice->gateway_fee,
                'invoices' => [
                    [
                        'invoice_id' => $this->invoice->hashed_id,
                        'amount' => $this->invoice->balance,
                        'invoice_number' => $this->invoice->number,
                        'pre_payment' => $this->invoice->is_proforma,
                    ],
                ],
            ],
            'fee_total' => $this->invoice->gateway_fee,
            'fee_invoice_id' => $this->invoice->id,
        ]);

        $cg->driver($this->invoice->client)
           ->setPaymentHash($payment_hash)
           ->confirmGatewayFee();

        $i = $this->invoice->fresh();

        $this->assertEquals($i->amount, $payment_hash->amount_with_fee());

    }



    public function testProRataGatewayFees()
    {
        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = 1.00;
        $data[1]['fee_percent'] = 2;
        $data[1]['fee_tax_name1'] = 'GST';
        $data[1]['fee_tax_rate1'] = 10;
        $data[1]['fee_tax_name2'] = 'GST';
        $data[1]['fee_tax_rate2'] = 10;
        $data[1]['fee_tax_name3'] = 'GST';
        $data[1]['fee_tax_rate3'] = 10;
        $data[1]['adjust_fee_percent'] = false;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(config('ninja.testvars.stripe'));
        $cg->fees_and_limits = $data;
        $cg->save();

        $total = 10.93;
        $total_invoice_count = 5;
        $total_gateway_fee = round($cg->calcGatewayFee($total, GatewayType::CREDIT_CARD, true), 2);

        $this->assertEquals(1.58, $total_gateway_fee);

        /*simple pro rata*/
        $fees_and_limits = $cg->getFeesAndLimits(GatewayType::CREDIT_CARD);
    }

    
}
