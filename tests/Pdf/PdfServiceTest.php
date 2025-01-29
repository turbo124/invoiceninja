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
