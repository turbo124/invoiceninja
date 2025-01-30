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

namespace Tests\Pdf;

use App\Services\Pdf\PdfConfiguration;
use App\Services\Pdf\PdfService;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *   App\Services\Pdf\PdfService
 */
class PdfServiceTest extends TestCase
{
    use MockAccountData;

    private string $max_pdf_variables = '{"client_details":["$client.name","$contact.full_name","$client.address1","$client.city_state_postal","$client.number","$client.vat_number","$client.postal_city_state","$client.website","$client.country","$client.custom3","$client.id_number","$client.phone","$client.address2","$client.custom1","$contact.custom1"],"vendor_details":["$vendor.name","$vendor.number","$vendor.vat_number","$vendor.address1","$vendor.address2","$vendor.city_state_postal","$vendor.country","$vendor.phone","$contact.email","$vendor.id_number","$vendor.website","$vendor.custom2","$vendor.custom1","$vendor.custom4","$vendor.custom3","$contact.phone","$contact.full_name","$contact.custom2","$contact.custom1"],"purchase_order_details":["$purchase_order.number","$purchase_order.date","$purchase_order.total","$purchase_order.balance_due","$purchase_order.due_date","$purchase_order.po_number","$purchase_order.custom1","$purchase_order.custom2","$purchase_order.custom3"],"company_details":["$company.name","$company.email","$company.phone","$company.id_number","$company.vat_number","$company.website","$company.address2","$company.address1","$company.city_state_postal","$company.postal_city_state","$company.custom1","$company.custom3"],"company_address":["$company.address1","$company.city_state_postal","$company.country","$company.id_number","$company.vat_number","$company.website","$company.email","$company.name","$company.custom1"],"invoice_details":["$invoice.number","$invoice.date","$invoice.balance","$invoice.custom1","$invoice.due_date","$invoice.project","$invoice.balance_due","$invoice.custom3","$invoice.po_number","$invoice.custom2","$invoice.amount","$invoice.custom4"],"quote_details":["$quote.number","$quote.custom1","$quote.po_number","$quote.date","$quote.valid_until","$quote.total","$quote.custom2","$quote.custom3","$quote.custom4"],"credit_details":["$credit.number","$credit.balance","$credit.po_number","$credit.date","$credit.valid_until","$credit.total","$credit.custom1","$credit.custom2","$credit.custom3"],"product_columns":["$product.item","$product.product1","$product.description","$product.product2","$product.tax","$product.line_total","$product.quantity","$product.unit_cost","$product.discount","$product.product3","$product.product4","$product.gross_line_total"],"product_quote_columns":["$product.item","$product.description","$product.unit_cost","$product.quantity","$product.discount","$product.tax","$product.line_total"],"task_columns":["$task.service","$task.description","$task.rate","$task.hours","$task.discount","$task.line_total","$task.tax","$task.tax_amount","$task.task2","$task.task1","$task.task3"],"total_columns":["$total","$line_taxes","$total_taxes","$discount","$custom_surcharge1","$outstanding","$net_subtotal","$custom_surcharge2","$custom_surcharge3","$subtotal","$paid_to_date"],"statement_invoice_columns":["$invoice.number","$invoice.date","$due_date","$total","$balance"],"statement_payment_columns":["$invoice.number","$payment.date","$method","$statement_amount"],"statement_credit_columns":["$credit.number","$credit.date","$total","$credit.balance"],"statement_details":["$statement_date","$balance"],"delivery_note_columns":["$product.item","$product.description","$product.quantity"],"statement_unapplied_columns":["$payment.number","$payment.date","$payment.amount","$payment.payment_balance"]}';
    
    private string $min_pdf_variables = '{"client_details":["$client.name","$client.vat_number","$client.address1","$client.city_state_postal","$client.country"],"vendor_details":["$vendor.name","$vendor.vat_number","$vendor.address1","$vendor.city_state_postal","$vendor.country"],"purchase_order_details":["$purchase_order.number","$purchase_order.date","$purchase_order.total"],"company_details":["$company.name","$company.address1","$company.city_state_postal"],"company_address":["$company.name","$company.website"],"invoice_details":["$invoice.number","$invoice.date","$invoice.due_date","$invoice.balance"],"quote_details":["$quote.number","$quote.date","$quote.valid_until"],"credit_details":["$credit.date","$credit.number","$credit.balance"],"product_columns":["$product.item","$product.description","$product.line_total"],"product_quote_columns":["$product.item","$product.description","$product.unit_cost","$product.quantity","$product.discount","$product.tax","$product.line_total"],"task_columns":["$task.description","$task.rate","$task.line_total"],"total_columns":["$total","$total_taxes","$outstanding"],"statement_invoice_columns":["$invoice.number","$invoice.date","$due_date","$total","$balance"],"statement_payment_columns":["$invoice.number","$payment.date","$method","$statement_amount"],"statement_credit_columns":["$credit.number","$credit.date","$total","$credit.balance"],"statement_details":["$statement_date","$balance"],"delivery_note_columns":["$product.item","$product.description","$product.quantity"],"statement_unapplied_columns":["$payment.number","$payment.date","$payment.amount","$payment.payment_balance"]}';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testMultiDesignGeneration()
    {

        if (config('ninja.testvars.travis')) {
            $this->markTestSkipped();
        }

        \App\Models\Design::where('is_custom',false)->cursor()->each(function ($design){


            $this->invoice->design_id = $design->id;
            $this->invoice->save();
            $this->invoice = $this->invoice->fresh();

            $invitation = $this->invoice->invitations->first();

            $service = (new PdfService($invitation))->boot();
            $pdf = $service->getPdf();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/' . $design->name.'.pdf', $pdf);
            
        });
    

        \App\Models\Design::where('is_custom', false)->cursor()->each(function ($design) {


            $this->invoice->design_id = $design->id;
            $this->invoice->save();
            $this->invoice = $this->invoice->fresh();

            $invitation = $this->invoice->invitations->first();

            $service = (new PdfService($invitation, 'delivery_note'))->boot();
            $pdf = $service->getPdf();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/dn_' . $design->name.'.pdf', $pdf);

        });

    }

    public function testMaxInvoiceFields()
    {
        $max_settings = json_decode($this->max_pdf_variables);

        $settings = $this->company->settings;
        $settings->pdf_variables = $max_settings;

        $this->company->settings = $settings;
        $this->company->save();

        $this->invoice->company->settings->pdf_variables = $max_settings;

        \App\Models\Design::where('is_custom', false)->cursor()->each(function ($design) use ($max_settings) {


            $this->invoice->design_id = $design->id; 
            $this->invoice->client->settings->pdf_variables = $max_settings;
            $this->invoice->push();
            $this->invoice = $this->invoice->fresh();

            $invitation = $this->invoice->invitations->first();
            $invitation->setRelation('company', $this->company);

            $service = (new PdfService($invitation))->boot();
            $pdf = $service->getPdf();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/max_fields_' . $design->name.'.pdf', $pdf);

        });


    }

    public function testMinInvoiceFields()
    {
        $min_settings = json_decode($this->min_pdf_variables);

        $settings = $this->company->settings;
        $settings->pdf_variables = $min_settings;

        $this->company->settings = $settings;
        $this->company->save();

        $this->invoice->company->settings->pdf_variables = $min_settings;

        \App\Models\Design::where('is_custom', false)->cursor()->each(function ($design) use ($min_settings) {


            $this->invoice->design_id = $design->id;
            $this->invoice->client->settings = $min_settings;
            $this->invoice->push();
            $this->invoice = $this->invoice->fresh();

            $invitation = $this->invoice->invitations->first();
            $invitation = $invitation->fresh();

            $service = (new PdfService($invitation))->boot();
            $pdf = $service->getPdf();

            $this->assertNotNull($pdf);

            \Illuminate\Support\Facades\Storage::put('/pdf/min_fields_' . $design->name.'.pdf', $pdf);

        });


    }


    public function testStatementPdfGeneration()
    {

        $pdf = $this->client->service()->statement([
            'client_id' => $this->client->hashed_id,
            'start_date' => '2000-01-01',
            'end_date' => '2023-01-01',
            'show_aging_table' => true,
            'show_payments_table' => true,
            'status' => 'all'    
        ]);
    

        $this->assertNotNull($pdf);

        \Illuminate\Support\Facades\Storage::put('/pdf/statement.pdf', $pdf);


    }

    public function testPdfGeneration()
    {

        if(config('ninja.testvars.travis')) {
            $this->markTestSkipped();
        }

        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertNotNull($service->getPdf());

    }

    public function testHtmlGeneration()
    {

        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertIsString($service->getHtml());

    }

    public function testInitOfClass()
    {

        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertInstanceOf(PdfService::class, $service);

    }

    public function testEntityResolution()
    {

        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertInstanceOf(PdfConfiguration::class, $service->config);


    }

    public function testDefaultDesign()
    {
        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertEquals(2, $service->config->design->id);

    }

    public function testHtmlIsArray()
    {
        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertIsArray($service->html_variables);

    }

    public function testTemplateResolution()
    {
        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertIsString($service->designer->template);

    }

}
