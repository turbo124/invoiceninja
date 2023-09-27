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

namespace Tests\Feature\Template;

use Tests\TestCase;
use App\Utils\Ninja;
use App\Models\Design;
use App\Models\Invoice;
use App\Utils\HtmlEngine;
use Tests\MockAccountData;
use App\Services\PdfMaker\PdfMaker;
use Illuminate\Support\Facades\App;
use App\Jobs\Entity\CreateEntityPdf;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\Template\TemplateService;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 * @covers 
 */
class TemplateTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private string $body = '
            
                <ninja>
                    $company.name
                    <table class="min-w-full text-left text-sm font-light">
                        <thead class="border-b font-medium dark:border-neutral-500">
                            <tr class="text-sm leading-normal">
                                <th scope="col" class="px-6 py-4">Item #</th>
                                <th scope="col" class="px-6 py-4">Description</th>
                                <th scope="col" class="px-6 py-4">Ordered</th>
                                <th scope="col" class="px-6 py-4">Delivered</th>
                                <th scope="col" class="px-6 py-4">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                        {% for entity in invoices %}
                        {% for item in entity.line_items|filter(item => item.type_id == "1") %}
                            <tr class="border-b dark:border-neutral-500">
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.product_key }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.notes }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.quantity }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.quantity }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">0</td>
                            </tr>
                        {% endfor %}
                        {% endfor %}
                        </tbody>
                    </table>
                </ninja>
            
            ';

        private string $nested_body = '
            
                <ninja>
                    $company.name
                    <table class="min-w-full text-left text-sm font-light">
                        <thead class="border-b font-medium dark:border-neutral-500">
                            <tr class="text-sm leading-normal">
                                <th scope="col" class="px-6 py-4">Item #</th>
                                <th scope="col" class="px-6 py-4">Description</th>
                                <th scope="col" class="px-6 py-4">Ordered</th>
                                <th scope="col" class="px-6 py-4">Delivered</th>
                                <th scope="col" class="px-6 py-4">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                        {% for entity in invoices %}
                        Client Name: {{ entity.client.name }}
                        Client Name with variables = $client.name
                        {% for item in entity.line_items|filter(item => item.type_id == "1") %}
                            <tr class="border-b dark:border-neutral-500">
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.product_key }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.notes }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.quantity }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.quantity }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">0</td>
                            </tr>
                        {% endfor %}
                        {% endfor %}
                        </tbody>
                    </table>
                </ninja>
            
            ';

        private string $payments_body = '
            CoName: $company.name
            ClName: $client.name
            InNumber: $invoice.number
        <ninja>
            CoName: $company.name
            ClName: $client.name
            InNumber: $invoice.number
                <table class="min-w-full text-left text-sm font-light">
                    <thead class="border-b font-medium dark:border-neutral-500">
                        <tr class="text-sm leading-normal">
                            <th scope="col" class="px-6 py-4">Invoice #</th>
                            <th scope="col" class="px-6 py-4">Date</th>
                            <th scope="col" class="px-6 py-4">Due Date</th>
                            <th scope="col" class="px-6 py-4">Total</th>
                            <th scope="col" class="px-6 py-4">Transaction</th>
                            <th scope="col" class="px-6 py-4">Outstanding</th>
                        </tr>
                    </thead>

                    <tbody>
                    {% for invoice in invoices %}
                        <tr class="border-b dark:border-neutral-500">
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.number }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.date }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.due_date }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.amount|format_currency("EUR") }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium"></td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.balance|format_currency("EUR") }}</td>
                        </tr>

                        {% for payment in invoice.payments|filter(payment => payment.is_deleted == false) %}
                        
                            {% for pivot in payment.paymentables %}

                            <tr class="border-b dark:border-neutral-500">
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ payment.number }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ payment.date }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium"></td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">
                                {% if pivot.amount > 0 %}
                                    {{ pivot.amount|format_currency("EUR") }} - {{ payment.type.name }}
                                {% else %}
                                    ({{ pivot.refunded|format_currency("EUR") }})
                                {% endif %}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium"></td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium"></td>
                            </tr>

                            {% endfor %}
                        {% endfor %}
                    {% endfor%}
                    </tbody>
                </table>
                
            </ninja>
        ';

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
        
    }

    public function testVariableResolutionViaTransformersForPaymentsInStatements()
    {
        Invoice::factory()->count(20)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 100,
            'balance' => 100,
        ]);

        $i = Invoice::orderBy('id','desc')
                    ->where('client_id', $this->client->id)
                    ->where('status_id', 2)
                    ->cursor()
                    ->each(function ($i){
                        $i->service()->applyPaymentAmount(random_int(1,100));
                    });

        $invoices = Invoice::withTrashed()
            ->with('payments.type')
            ->where('is_deleted', false)
            ->where('company_id', $this->client->company_id)
            ->where('client_id', $this->client->id)
            ->whereIn('status_id', [2,3,4])
            ->orderBy('due_date', 'ASC')
            ->orderBy('date', 'ASC')
            ->cursor();

            $invoices->each(function ($i){

                $rand = [1,2,4,5,6,7,8,9,10,11,12,13,14,15,16,17,24,25,32,49,50];

                $i->payments()->each(function ($p) use ($rand){
                    shuffle($rand);
                    $p->type_id = $rand[0];
                    $p->save();
                    
                });
            });

            $design_model = Design::find(2);

            $replicated_design = $design_model->replicate();
            $design = $replicated_design->design;
            $design->body .= $this->payments_body;
            $replicated_design->design = $design;
            $replicated_design->is_custom = true;
            $replicated_design->is_template =true;
            $replicated_design->entities = 'client';
            $replicated_design->save();

            $data['invoices'] = $invoices;
            $ts = $replicated_design->service()->build($data);
        
            // nlog("results = ");
            // nlog($ts->getHtml());
            $this->assertNotNull($ts->getHtml());

    }

    public function testDoubleEntityNestedDataTemplateServiceBuild()
    {
        $design_model = Design::find(2);

        $replicated_design = $design_model->replicate();
        $design = $replicated_design->design;
        $design->body .= $this->nested_body;
        $replicated_design->design = $design;
        $replicated_design->is_custom = true;
        $replicated_design->save();

        $i2 = Invoice::factory()
        ->for($this->client)
        ->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status_id' => Invoice::STATUS_SENT,
            'design_id' => $replicated_design->id,
            'balance' => 100,
        ]);

        $data = [];
        $data['invoices'] = collect([$this->invoice, $i2]);

        $ts = $replicated_design->service()->build($data);
        
        // nlog("results = ");
        // nlog($ts->getHtml());
        $this->assertNotNull($ts->getHtml());
    }

    public function testDoubleEntityTemplateServiceBuild()
    {
        $design_model = Design::find(2);

        $replicated_design = $design_model->replicate();
        $design = $replicated_design->design;
        $design->body .= $this->body;
        $replicated_design->design = $design;
        $replicated_design->is_custom = true;
        $replicated_design->save();

        $i2 = Invoice::factory()
        ->for($this->client)
        ->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status_id' => Invoice::STATUS_SENT,
            'design_id' => $replicated_design->id,
            'balance' => 100,
        ]);

        $data = [];
        $data['invoices'] = collect([$this->invoice, $i2]);

        $ts = $replicated_design->service()->build($data);
        
        // nlog("results = ");
        // nlog($ts->getHtml());
        $this->assertNotNull($ts->getHtml());
    }

    public function testTemplateServiceBuild()
    {
        $design_model = Design::find(2);

        $replicated_design = $design_model->replicate();
        $design = $replicated_design->design;
        $design->body .= $this->body;
        $replicated_design->design = $design;
        $replicated_design->is_custom = true;
        $replicated_design->save();

        $data = [];
        $data['invoices'] = collect([$this->invoice]);

        $ts = $replicated_design->service()->build($data);
        
        // nlog("results = ");
        // nlog($ts->getHtml());
        $this->assertNotNull($ts->getHtml());
    }

    public function testTemplateService()
    {
        $design_model = Design::find(2);

        $replicated_design = $design_model->replicate();
        $design = $replicated_design->design;
        $design->body .= $this->body;
        $replicated_design->design = $design;
        $replicated_design->is_custom = true;
        $replicated_design->save();

        $this->assertNotNull($replicated_design->service());
        $this->assertInstanceOf(TemplateService::class, $replicated_design->service());
    }

    public function testTimingOnCleanDesign()
    {
        $design_model = Design::find(2);

        $replicated_design = $design_model->replicate();
        $design = $replicated_design->design;
        $design->body .= $this->body;
        $replicated_design->design = $design;
        $replicated_design->is_custom = true;
        $replicated_design->save();

        $entity_obj = \App\Models\Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_SENT,
            'design_id' => $replicated_design->id,
        ]);

        $i = \App\Models\InvoiceInvitation::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'invoice_id' => $entity_obj->id,
            'client_contact_id' => $this->client->contacts->first()->id,
        ]);

        $start = microtime(true);

        $pdf = (new CreateEntityPdf($i))->handle();

        $end = microtime(true);

        $this->assertNotNull($pdf);

        nlog("Twig + PDF Gen Time: " . $end-$start);

    }

    public function testStaticPdfGeneration()
    {
        $start = microtime(true);

        $pdf = (new CreateEntityPdf($this->invoice->invitations->first()))->handle();

        $end = microtime(true);

        $this->assertNotNull($pdf);

        nlog("Plain PDF Gen Time: " . $end-$start);
    }

    public function testTemplateGeneration()
    {
        $entity_obj = $this->invoice;
        
        $design = new Design();
        $design->design = json_decode(json_encode($this->invoice->company->settings->pdf_variables), true);
        $design->name = 'test';
        $design->is_active = true;
        $design->is_template = true;
        $design->is_custom = true;
        $design->user_id = $this->invoice->user_id;
        $design->company_id = $this->invoice->company_id;

            $design_object = new \stdClass;
            $design_object->includes = '';
            $design_object->header = '';
            $design_object->body = $this->body;
            $design_object->product = '';
            $design_object->task = '';
            $design_object->footer = '';

            $design->design = $design_object;

        $design->save();

        $start = microtime(true);

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($entity_obj->client->locale());
        $t->replace(Ninja::transformTranslations($entity_obj->client->getMergedSettings()));

        $html = new HtmlEngine($entity_obj->invitations()->first());

        $options = [
            'custom_partials' => json_decode(json_encode($design->design), true),
        ];
        $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
    
        $variables = $html->generateLabelsAndValues();

        $state = [
            'template' => $template->elements([
                'client' => $entity_obj->client,
                'entity' => $entity_obj,
                'pdf_variables' => (array) $entity_obj->company->settings->pdf_variables,
                '$product' => $design->design->product,
                'variables' => $variables,
            ]),
            'variables' => $variables,
            'options' => [
                'all_pages_header' => $entity_obj->client->getSetting('all_pages_header'),
                'all_pages_footer' => $entity_obj->client->getSetting('all_pages_footer'),
                'client' => $entity_obj->client,
                'entity' => $entity_obj,
                'variables' => $variables,
            ],
            'process_markdown' => $entity_obj->client->company->markdown_enabled,
        ];

        $maker = new PdfMaker($state);
        $maker
                ->design($template)
                ->build();

        $html = $maker->getCompiledHTML(true);

        $end = microtime(true);

        $this->assertNotNull($html);
        $this->assertStringContainsStringIgnoringCase($this->company->settings->name, $html);
 
        nlog("Twig Solo Gen Time: ". $end - $start);
    }

}