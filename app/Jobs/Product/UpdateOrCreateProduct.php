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

namespace App\Jobs\Product;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Product;
use App\Repositories\InvoiceRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Capsule\Eloquent;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateOrCreateProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $products;

    private $invoice;

    private $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($products, $invoice, $company)
    {
        $this->products = $products;

        $this->invoice = $invoice;

        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDB($this->company->db);

        foreach ($this->products as $item) {
            if (empty($item->product_key)) {
                continue;
            }

            $product = Product::firstOrNew(['product_key' => $item->product_key, 'company_id' => $this->invoice->company->id]);

            $product->product_key = $item->product_key;
            $product->notes = isset($item->notes) ? $item->notes : '';
            //$product->cost = isset($item->cost) ? $item->cost : 0; //this value shouldn't be updated.
            $product->price = isset($item->cost) ? $item->cost : 0;
            $product->quantity = isset($item->quantity) ? $item->quantity : 0;
            $product->tax_name1 = isset($item->tax_name1) ? $item->tax_name1 : '';
            $product->tax_rate1 = isset($item->tax_rate1) ? $item->tax_rate1 : 0;
            $product->tax_name2 = isset($item->tax_name2) ? $item->tax_name2 : '';
            $product->tax_rate2 = isset($item->tax_rate2) ? $item->tax_rate2 : 0;
            $product->tax_name3 = isset($item->tax_name3) ? $item->tax_name3 : '';
            $product->tax_rate3 = isset($item->tax_rate3) ? $item->tax_rate3 : 0;
            $product->custom_value1 = isset($item->custom_value1) ? $item->custom_value1 : '';
            $product->custom_value2 = isset($item->custom_value2) ? $item->custom_value2 : '';
            $product->custom_value3 = isset($item->custom_value3) ? $item->custom_value3 : '';
            $product->custom_value4 = isset($item->custom_value4) ? $item->custom_value4 : '';
            $product->user_id = $this->invoice->user_id;
            $product->company_id = $this->invoice->company_id;
            $product->project_id = $this->invoice->project_id;
            $product->vendor_id = $this->invoice->vendor_id;
            $product->save();
        }
    }
}
