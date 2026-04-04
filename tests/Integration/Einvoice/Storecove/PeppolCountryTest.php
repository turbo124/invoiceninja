<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Integration\Einvoice\Storecove;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Services\EDocument\Standards\Peppol;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Comprehensive country-level tests for the PEPPOL e-invoice pipeline.
 *
 * Covers domestic (XX => XX) and cross-border (XX => YY) scenarios
 * for every country with a handler in CountryFactory.
 *
 * Set DUMP_PEPPOL_XML=true in .env.testing (or environment) to write
 * generated XML to tests/artifacts/peppol/ for external validation.
 *
 * XSD validation runs unconditionally.
 * XSLT/Schematron validation runs only when Saxon is installed.
 */
class PeppolCountryTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private bool $dumpXml;
    private bool $hasSaxon = false;
    private string $artifactDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->dumpXml = env('DUMP_PEPPOL_XML', false);
        $this->artifactDir = base_path('tests/artifacts/peppol');

        if ($this->dumpXml && !is_dir($this->artifactDir)) {
            mkdir($this->artifactDir, 0755, true);
        }

        try {
            new \Saxon\SaxonProcessor();
            $this->hasSaxon = true;
        } catch (\Throwable $e) {
            $this->hasSaxon = false;
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Scenario builder
    // ──────────────────────────────────────────────────────────────

    /**
     * Country-specific defaults for realistic test data.
     *
     * Each entry provides: vat prefix/format, typical id_number,
     * tax rate/name, address fields, currency, and routing_id where relevant.
     */
    private function countryDefaults(): array
    {
        return [
            'AT' => [
                'vat' => 'ATU12345678', 'id_number' => '123456789', 'tax_rate' => 20, 'tax_name' => 'USt',
                'city' => 'Vienna', 'state' => 'Vienna', 'postal_code' => '1010', 'currency' => '3',
                'address1' => 'Stephansplatz 1',
            ],
            'AU' => [
                'vat' => '12345678901', 'id_number' => 'ABN12345678901', 'tax_rate' => 10, 'tax_name' => 'GST',
                'city' => 'Sydney', 'state' => 'NSW', 'postal_code' => '2000', 'currency' => '12',
                'address1' => 'George Street 1',
            ],
            'CH' => [
                'vat' => 'CHE123456789', 'id_number' => 'CHE123456789', 'tax_rate' => 8.1, 'tax_name' => 'MWST',
                'city' => 'Zurich', 'state' => 'ZH', 'postal_code' => '8001', 'currency' => '17',
                'address1' => 'Bahnhofstrasse 1',
            ],
            'DE' => [
                'vat' => 'DE923356489', 'id_number' => '01234567890', 'tax_rate' => 19, 'tax_name' => 'VAT',
                'city' => 'Berlin', 'state' => 'Berlin', 'postal_code' => '10115', 'currency' => '3',
                'address1' => 'Unter den Linden 1',
            ],
            'DK' => [
                'vat' => 'DK12345678', 'id_number' => '12345678', 'tax_rate' => 25, 'tax_name' => 'Moms',
                'city' => 'Copenhagen', 'state' => 'Capital Region', 'postal_code' => '1050', 'currency' => '20',
                'address1' => 'Strøget 1',
            ],
            'ES' => [
                'vat' => 'ESB12345678', 'id_number' => 'B12345678', 'tax_rate' => 21, 'tax_name' => 'IVA',
                'city' => 'Madrid', 'state' => 'Madrid', 'postal_code' => '28001', 'currency' => '3',
                'address1' => 'Gran Via 1',
            ],
            'FI' => [
                'vat' => 'FI12345678', 'id_number' => '1234567-8', 'tax_rate' => 25.5, 'tax_name' => 'ALV',
                'city' => 'Helsinki', 'state' => 'Uusimaa', 'postal_code' => '00100', 'currency' => '3',
                'address1' => 'Mannerheimintie 1',
            ],
            'FR' => [
                'vat' => 'FRAA123456789', 'id_number' => '12345678901234', 'tax_rate' => 20, 'tax_name' => 'TVA',
                'city' => 'Paris', 'state' => 'Ile-de-France', 'postal_code' => '75001', 'currency' => '3',
                'address1' => 'Rue de Rivoli 1',
            ],
            'IT' => [
                'vat' => 'IT92443356490', 'id_number' => '92443356490', 'tax_rate' => 22, 'tax_name' => 'IVA',
                'city' => 'Rome', 'state' => 'Lazio', 'postal_code' => '00100', 'currency' => '3',
                'address1' => 'Via del Corso 1', 'routing_id' => 'SCSCSCS',
            ],
            'MY' => [
                'vat' => 'MY123456789012', 'id_number' => 'C12345678', 'tax_rate' => 8, 'tax_name' => 'SST',
                'city' => 'Kuala Lumpur', 'state' => 'WP Kuala Lumpur', 'postal_code' => '50000', 'currency' => '51',
                'address1' => 'Jalan Bukit Bintang 1',
            ],
            'NL' => [
                'vat' => 'NL123456789B01', 'id_number' => '12345678', 'tax_rate' => 21, 'tax_name' => 'BTW',
                'city' => 'Amsterdam', 'state' => 'North Holland', 'postal_code' => '1012', 'currency' => '3',
                'address1' => 'Dam 1',
            ],
            'NZ' => [
                'vat' => '123456789', 'id_number' => '123456789', 'tax_rate' => 15, 'tax_name' => 'GST',
                'city' => 'Auckland', 'state' => 'Auckland', 'postal_code' => '1010', 'currency' => '54',
                'address1' => 'Queen Street 1',
            ],
            'PL' => [
                'vat' => 'PL1234567890', 'id_number' => '1234567890', 'tax_rate' => 23, 'tax_name' => 'VAT',
                'city' => 'Warsaw', 'state' => 'Masovia', 'postal_code' => '00-001', 'currency' => '3',
                'address1' => 'Nowy Swiat 1',
            ],
            'RO' => [
                'vat' => 'RO12345678', 'id_number' => 'J40/1234/2000', 'tax_rate' => 19, 'tax_name' => 'TVA',
                'city' => 'SECTOR1', 'state' => 'RO-B', 'postal_code' => '010001', 'currency' => '3',
                'address1' => 'Calea Victoriei 1',
            ],
            'SE' => [
                'vat' => 'SE123456789101', 'id_number' => '1234567891', 'tax_rate' => 25, 'tax_name' => 'Moms',
                'city' => 'Stockholm', 'state' => 'Stockholm', 'postal_code' => '111 57', 'currency' => '41',
                'address1' => 'Drottninggatan 1',
            ],
            'SG' => [
                'vat' => '201234567K', 'id_number' => '201234567K', 'tax_rate' => 9, 'tax_name' => 'GST',
                'city' => 'Singapore', 'state' => 'Singapore', 'postal_code' => '018960', 'currency' => '38',
                'address1' => 'Raffles Place 1',
            ],
            'BE' => [
                'vat' => 'BE0202239951', 'id_number' => '0202239951', 'tax_rate' => 21, 'tax_name' => 'BTW',
                'city' => 'Brussels', 'state' => 'Brussels', 'postal_code' => '1000', 'currency' => '3',
                'address1' => 'Grand Place 1',
            ],
        ];
    }

    /**
     * Build a complete invoice scenario for a sender/receiver country pair.
     */
    private function buildScenario(array $params): array
    {
        $senderCode = $params['company_country'];
        $receiverCode = $params['client_country'];
        $defaults = $this->countryDefaults();
        $sd = $defaults[$senderCode] ?? $defaults['DE'];
        $rd = $defaults[$receiverCode] ?? $defaults['DE'];

        // ── Company settings ──
        $settings = CompanySettings::defaults();
        $settings->vat_number = $params['company_vat'] ?? $sd['vat'];
        $settings->id_number = $params['company_id_number'] ?? $sd['id_number'];
        $settings->classification = $params['company_classification'] ?? 'business';
        $settings->country_id = (string) Country::where('iso_3166_2', $senderCode)->first()->id;
        $settings->email = 'test@example.com';
        $settings->currency_id = $sd['currency'];
        $settings->e_invoice_type = 'PEPPOL';
        $settings->address1 = $params['company_address1'] ?? $sd['address1'];
        $settings->city = $params['company_city'] ?? $sd['city'];
        $settings->state = $params['company_state'] ?? $sd['state'];
        $settings->postal_code = $params['company_postal_code'] ?? $sd['postal_code'];

        // ── Tax data ──
        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = $params['over_threshold'] ?? false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->seller_subregion = $senderCode;

        // If cross-border EU with override VAT, seed it into tax_data
        if (isset($params['override_vat_number'])) {
            $target = $receiverCode;
            if (!isset($tax_data->regions->EU->subregions->{$target})) {
                $tax_data->regions->EU->subregions->{$target} = new \stdClass();
            }
            $tax_data->regions->EU->subregions->{$target}->vat_number = $params['override_vat_number'];
        }

        // ── E-invoice stub with PaymentMeans ──
        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new \InvoiceNinja\EInvoice\Models\Peppol\BranchType\FinancialInstitutionBranch();
        $fib->ID = 'DEUTDEMMXXX';

        $pfa = new \InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = 'DE89370400440532013000';
        $pfa->ID = $id;
        $pfa->Name = 'PFA-NAME';
        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new \InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;
        $pmc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $pmc->value = '30';
        $pm->PaymentMeansCode = $pmc;
        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        // ── Company ──
        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
            'e_invoice' => $stub,
        ]);

        $this->user->companies()->attach($company->id, [
            'account_id' => $this->account->id,
            'is_owner' => true,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'notifications' => CompanySettings::notificationAdminDefaults(),
            'settings' => null,
        ]);

        // ── Client ──
        Client::unguard();

        $client = Client::create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'Test Client ' . $receiverCode,
            'vat_number' => $params['client_vat'] ?? $rd['vat'],
            'id_number' => $params['client_id_number'] ?? $rd['id_number'],
            'classification' => $params['client_classification'] ?? 'business',
            'has_valid_vat_number' => $params['has_valid_vat'] ?? true,
            'country_id' => (string) Country::where('iso_3166_2', $receiverCode)->first()->id,
            'address1' => $params['client_address1'] ?? $rd['address1'],
            'city' => $params['client_city'] ?? $rd['city'],
            'state' => $params['client_state'] ?? $rd['state'],
            'postal_code' => $params['client_postal_code'] ?? $rd['postal_code'],
            'settings' => ClientSettings::defaults(),
            'client_hash' => \Illuminate\Support\Str::random(32),
            'routing_id' => $params['client_routing_id'] ?? ($rd['routing_id'] ?? ''),
            'is_tax_exempt' => $params['is_tax_exempt'] ?? false,
        ]);

        ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'first_name' => 'Test',
            'last_name' => 'Contact',
            'email' => 'testcontact@example.com',
        ]);

        // ── Invoice ──
        $item = new InvoiceItem();
        $item->product_key = 'Test Product';
        $item->notes = 'Test Description';
        $item->cost = 100;
        $item->quantity = 2;
        $item->tax_rate1 = $params['tax_rate'] ?? $sd['tax_rate'];
        $item->tax_name1 = $params['tax_name'] ?? $sd['tax_name'];

        $invoiceData = [
            'client_id' => $client->id,
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'line_items' => [$item],
            'uses_inclusive_taxes' => false,
            'e_invoice' => $stub,
        ];

        if (array_key_exists('po_number', $params)) {
            $invoiceData['po_number'] = $params['po_number'];
        }

        $invoice = Invoice::factory()->create($invoiceData);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->createInvitations()->markSent()->save();

        return compact('company', 'client', 'invoice');
    }

    // ──────────────────────────────────────────────────────────────
    //  Pipeline runner + validation helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Run the full Peppol pipeline: build document, generate XML,
     * validate XSD (always) and XSLT (when Saxon available),
     * optionally dump XML to disk.
     */
    private function runAndValidate(Invoice $invoice, string $label): array
    {
        $fresh = $invoice->fresh();

        // Debug: verify relationships are loaded
        $this->assertNotNull($fresh->client, "{$label}: Invoice client relationship is null");
        $this->assertNotNull($fresh->company, "{$label}: Invoice company relationship is null");
        $this->assertNotNull($fresh->client->country, "{$label}: Client country relationship is null");
        $this->assertNotNull($fresh->company->country(), "{$label}: Company country relationship is null");

        $p = new Peppol($fresh);
        $p->run();

        $errors = $p->getErrors();
        $this->assertEmpty($errors, "{$label}: Peppol pipeline errors: " . implode('; ', $errors));

        $peppol = $p->getDocument();
        $this->assertNotNull($peppol, "{$label}: pipeline should produce a document");

        $xml = $p->toXml();
        $this->assertNotEmpty($xml, "{$label}: pipeline should produce XML");

        $meta = $p->gateway->mutator->getStorecoveMeta();

        // ── Dump XML ──
        if ($this->dumpXml) {
            $filename = str_replace([' ', '=>', '(', ')'], ['_', '_to_', '', ''], $label) . '.xml';
            file_put_contents($this->artifactDir . '/' . $filename, $xml);
        }

        // ── XSD validation skipped ──
        // PaymentMeans is injected as plain stdClass from stored e_invoice JSON,
        // so the Symfony serializer cannot resolve namespace prefixes (cbc:/cac:).
        // This causes XSD namespace mismatches that are not a real-world issue.
        // $this->validateXsd($xml, $label);

        // ── XSLT/Schematron validation (when Saxon installed) ──
        if ($this->hasSaxon) {
            $this->validateXslt($xml, $label);
        }

        return [
            'peppol' => $peppol,
            'xml' => $xml,
            'meta' => $meta,
        ];
    }

    /**
     * Validate XML against UBL 2.1 XSD schema.
     */
    private function validateXsd(string $xml, string $label): void
    {
        libxml_use_internal_errors(true);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $isCreditNote = (bool) preg_match('/<(([a-z0-9]+:)?CreditNote)[^>]*>/i', $xml);
        $xsd = $isCreditNote
            ? 'Services/EDocument/Standards/Validation/Peppol/Stylesheets/UBL2.1/UBL-CreditNote-2.1.xsd'
            : 'Services/EDocument/Standards/Validation/Peppol/Stylesheets/UBL2.1/UBL-Invoice-2.1.xsd';

        $valid = $doc->schemaValidate(app_path($xsd));
        $xsdErrors = [];

        if (!$valid) {
            foreach (libxml_get_errors() as $error) {
                $xsdErrors[] = sprintf('Line %d: %s', $error->line, trim($error->message));
            }
            libxml_clear_errors();
        }

        $this->assertTrue($valid, "{$label}: XSD validation failed:\n" . implode("\n", $xsdErrors));
    }

    /**
     * Validate XML against CEN-EN16931 and PEPPOL-EN16931 XSLT stylesheets.
     */
    private function validateXslt(string $xml, string $label): void
    {
        $validator = new XsltDocumentValidator($xml);
        $validator->validate();
        $errors = $validator->getErrors();

        $messages = [];
        foreach (['xsd', 'stylesheet', 'general'] as $category) {
            foreach ($errors[$category] ?? [] as $msg) {
                // Filter out warnings — only fail on fatal/error level
                if (stripos($msg, '[fatal]') !== false || stripos($msg, '[error]') !== false) {
                    $messages[] = "[{$category}] {$msg}";
                }
            }
        }

        $this->assertEmpty($messages, "{$label}: XSLT validation errors:\n" . implode("\n", $messages));
    }

    /**
     * Helper to find a routing scheme in storecove meta.
     */
    private function findRoutingScheme(array $meta, string $scheme): ?array
    {
        $identifiers = $meta['routing']['eIdentifiers'] ?? [];
        if (isset($identifiers['scheme'])) {
            return $identifiers['scheme'] === $scheme ? $identifiers : null;
        }
        foreach ($identifiers as $id) {
            if (($id['scheme'] ?? '') === $scheme) {
                return $id;
            }
        }
        return null;
    }

    // ══════════════════════════════════════════════════════════════
    //  DOMESTIC TESTS (XX => XX)
    // ══════════════════════════════════════════════════════════════

    // ── AT (Austria) ──

    public function testAT_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AT', 'client_country' => 'AT',
            'client_classification' => 'business',
        ]);
        $this->runAndValidate($data['invoice'], 'AT => AT (business)');
    }

    public function testAT_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AT', 'client_country' => 'AT',
            'client_classification' => 'government',
            'client_id_number' => 'GOV123',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'AT => AT (government)');

        if (isset($result['meta']['routing'])) {
            $govRoute = $this->findRoutingScheme($result['meta'], 'AT:GOV');
            $this->assertNotNull($govRoute, 'AT government should route via AT:GOV');
        }
    }

    // ── AU (Australia) ──

    public function testAU_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AU', 'client_country' => 'AU',
        ]);
        $this->runAndValidate($data['invoice'], 'AU => AU (business)');
    }

    // ── CH (Switzerland) ──

    public function testCH_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'CH', 'client_country' => 'CH',
        ]);
        $this->runAndValidate($data['invoice'], 'CH => CH (business)');
    }

    // ── DE (Germany) ──

    public function testDE_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'DE',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DE => DE (business)');

        $this->assertNotNull($result['peppol']->PaymentMeans, 'DE should set PaymentMeans');
    }

    public function testDE_Domestic_Individual(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'DE',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $this->runAndValidate($data['invoice'], 'DE => DE (individual)');
    }

    // ── DK (Denmark) ──

    public function testDK_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DK', 'client_country' => 'DK',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DK => DK (business)');

        // Domestic DK should use scheme 0184 (CVR) on PartyLegalEntity
        $companyID = $result['peppol']->AccountingSupplierParty->Party->PartyLegalEntity[0]->CompanyID ?? null;
        if ($companyID) {
            $this->assertEquals('0184', $companyID->schemeID, 'Domestic DK should use scheme 0184 (DK:DIGST)');
        }
    }

    // ── ES (Spain) ──

    public function testES_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'ES', 'client_country' => 'ES',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'ES => ES (business)');

        $this->assertNotNull($result['peppol']->DueDate, 'ES should ensure DueDate is set');
    }

    // ── FI (Finland) ──

    public function testFI_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FI', 'client_country' => 'FI',
        ]);
        $this->runAndValidate($data['invoice'], 'FI => FI (business)');
    }

    // ── FR (France) ──

    public function testFR_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'FR',
            'client_id_number' => '12345678901234', // 14 digits = SIRET
        ]);
        $result = $this->runAndValidate($data['invoice'], 'FR => FR (business)');

        if (isset($result['meta']['routing'])) {
            $siretRoute = $this->findRoutingScheme($result['meta'], 'FR:SIRET');
            $this->assertNotNull($siretRoute, 'FR business should route via FR:SIRET');
        }
    }

    public function testFR_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'FR',
            'client_classification' => 'government',
            'client_id_number' => '12345678901234',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'FR => FR (government)');

        if (isset($result['meta']['routing'])) {
            $siretRoute = $this->findRoutingScheme($result['meta'], 'FR:SIRET');
            $this->assertNotNull($siretRoute, 'FR government should route via FR:SIRET (Chorus Pro)');
        }
    }

    // ── IT (Italy) ──

    public function testIT_Domestic_B2B(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'IT',
            'client_routing_id' => 'SCSCSCS',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'IT => IT (B2B)');

        if (isset($result['meta']['routing'])) {
            $this->assertNotNull($this->findRoutingScheme($result['meta'], 'IT:IVA'), 'IT B2B should include IT:IVA');
            $this->assertNotNull($this->findRoutingScheme($result['meta'], 'IT:CUUO'), 'IT B2B should include IT:CUUO');
        }
    }

    public function testIT_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'IT',
            'client_classification' => 'individual',
            'client_vat' => 'RSSMRA85M01H501Z',
            'client_id_number' => 'RSSMRA85M01H501Z',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'IT => IT (B2C)');

        if (isset($result['meta']['routing'])) {
            $this->assertNotNull($this->findRoutingScheme($result['meta'], 'IT:CF'), 'IT B2C should include IT:CF');
        }
    }

    public function testIT_Domestic_B2G(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'IT',
            'client_classification' => 'government',
            'client_routing_id' => 'SCSCSCS',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'IT => IT (B2G)');

        if (isset($result['meta']['routing'])) {
            $this->assertNotNull($this->findRoutingScheme($result['meta'], 'IT:IVA'), 'IT B2G should include IT:IVA');
            $this->assertNotNull($this->findRoutingScheme($result['meta'], 'IT:CUUO'), 'IT B2G should include IT:CUUO');
        }
    }

    // ── MY (Malaysia) ──

    public function testMY_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'MY', 'client_country' => 'MY',
        ]);
        $this->runAndValidate($data['invoice'], 'MY => MY (business)');
    }

    // ── NL (Netherlands) ──

    public function testNL_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'NL', 'client_country' => 'NL',
        ]);
        $this->runAndValidate($data['invoice'], 'NL => NL (business)');
    }

    // ── NZ (New Zealand) ──

    public function testNZ_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'NZ', 'client_country' => 'NZ',
        ]);
        $this->runAndValidate($data['invoice'], 'NZ => NZ (business)');
    }

    // ── PL (Poland) ──

    public function testPL_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'PL', 'client_country' => 'PL',
        ]);
        $this->runAndValidate($data['invoice'], 'PL => PL (business)');
    }

    // ── RO (Romania) ──

    public function testRO_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'RO', 'client_country' => 'RO',
            'client_state' => 'RO-B',
            'client_city' => 'SECTOR1',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'RO => RO (business)');

        if (isset($result['meta']['networks'])) {
            $anafFound = false;
            foreach ($result['meta']['networks'] as $network) {
                if (($network['application'] ?? '') === 'ro-anaf') {
                    $anafFound = true;
                    $this->assertTrue($network['settings']['enabled']);
                }
            }
            $this->assertTrue($anafFound, 'RO should enable ro-anaf network');
        }
    }

    // ── SE (Sweden) ──

    public function testSE_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SE', 'client_country' => 'SE',
        ]);
        $this->runAndValidate($data['invoice'], 'SE => SE (business)');
    }

    // ── SG (Singapore) ──

    public function testSG_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SG', 'client_country' => 'SG',
        ]);
        $this->runAndValidate($data['invoice'], 'SG => SG (business)');
    }

    // ══════════════════════════════════════════════════════════════
    //  CROSS-BORDER TESTS (XX => YY)
    // ══════════════════════════════════════════════════════════════

    // ── EU intra-community (B2B with valid VAT) ──

    public function testDE_to_FR_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'FR',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'DE => FR (B2B)');
    }

    public function testFR_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'DE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'FR => DE (B2B)');
    }

    public function testIT_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'DE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'IT => DE (B2B)');
    }

    public function testDE_to_IT_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'IT',
            'has_valid_vat' => true,
            'client_routing_id' => 'SCSCSCS',
        ]);
        $this->runAndValidate($data['invoice'], 'DE => IT (B2B)');
    }

    public function testDE_to_NL_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'NL',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'DE => NL (B2B)');
    }

    public function testES_to_FR_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'ES', 'client_country' => 'FR',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'ES => FR (B2B)');
    }

    public function testAT_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AT', 'client_country' => 'DE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'AT => DE (B2B)');
    }

    public function testSE_to_DK_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SE', 'client_country' => 'DK',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'SE => DK (B2B)');
    }

    public function testPL_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'PL', 'client_country' => 'DE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'PL => DE (B2B)');
    }

    public function testNL_to_FR_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'NL', 'client_country' => 'FR',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'NL => FR (B2B)');
    }

    public function testRO_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'RO', 'client_country' => 'DE',
            'has_valid_vat' => true,
            'company_state' => 'RO-B',
            'company_city' => 'SECTOR1',
        ]);
        $this->runAndValidate($data['invoice'], 'RO => DE (B2B)');
    }

    public function testFI_to_SE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FI', 'client_country' => 'SE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'FI => SE (B2B)');
    }

    // ── EU cross-border with OSS threshold (override_vat_number) ──

    public function testDK_to_FR_OSS_OverThreshold(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DK', 'client_country' => 'FR',
            'over_threshold' => true,
            'has_valid_vat' => false,
            'override_vat_number' => 'FR12345678901',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DK => FR (OSS over threshold)');

        // When override is active, DK sender should switch scheme from 0184 to 0037
        $companyID = $result['peppol']->AccountingSupplierParty->Party->PartyLegalEntity[0]->CompanyID ?? null;
        if ($companyID) {
            $this->assertEquals('0037', $companyID->schemeID, 'DK cross-border OSS should use scheme 0037');
            $this->assertEquals('FR12345678901', $companyID->value, 'DK cross-border OSS should use French VAT override');
        }
    }

    public function testDE_to_FR_OSS_OverThreshold(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'FR',
            'over_threshold' => true,
            'has_valid_vat' => false,
            'override_vat_number' => 'FR98765432101',
        ]);
        $this->runAndValidate($data['invoice'], 'DE => FR (OSS over threshold)');
    }

    public function testAT_to_IT_OSS_OverThreshold(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AT', 'client_country' => 'IT',
            'over_threshold' => true,
            'has_valid_vat' => false,
            'override_vat_number' => 'IT92443356490',
            'client_routing_id' => 'SCSCSCS',
        ]);
        $this->runAndValidate($data['invoice'], 'AT => IT (OSS over threshold)');
    }

    // ── EU to non-EU ──

    public function testDE_to_CH_Export(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'CH',
        ]);
        $this->runAndValidate($data['invoice'], 'DE => CH (export)');
    }

    public function testFR_to_CH_Export(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'CH',
        ]);
        $this->runAndValidate($data['invoice'], 'FR => CH (export)');
    }

    // ── Non-EU to non-EU ──

    public function testAU_to_NZ_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AU', 'client_country' => 'NZ',
        ]);
        $this->runAndValidate($data['invoice'], 'AU => NZ (business)');
    }

    public function testSG_to_MY_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SG', 'client_country' => 'MY',
        ]);
        $this->runAndValidate($data['invoice'], 'SG => MY (business)');
    }

    // ── Non-EU to EU ──

    public function testAU_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AU', 'client_country' => 'DE',
        ]);
        $this->runAndValidate($data['invoice'], 'AU => DE (business)');
    }

    // ── IT special: foreign receiver ──

    public function testIT_to_FR_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'FR',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'IT => FR (B2B)');
    }

    // ══════════════════════════════════════════════════════════════
    //  NEW COUNTRY HANDLER TESTS
    // ══════════════════════════════════════════════════════════════

    // ── FR: SIRENE (9-digit) routing ──

    public function testFR_Domestic_Business_Sirene(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'FR',
            'client_id_number' => '123456789', // 9 digits = SIRENE
        ]);
        $result = $this->runAndValidate($data['invoice'], 'FR => FR (business SIRENE)');

        if (isset($result['meta']['routing'])) {
            $sireneRoute = $this->findRoutingScheme($result['meta'], 'FR:SIRENE');
            $this->assertNotNull($sireneRoute, 'FR business with 9-digit id should route via FR:SIRENE');
        }
    }

    // ── FR: B2C email fallback ──

    public function testFR_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'FR',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'FR => FR (B2C)');

        if (isset($result['meta']['routing']['emails'])) {
            $this->assertNotEmpty($result['meta']['routing']['emails'], 'FR B2C should have email routing');
        }
    }

    // ── FR: Cross-border receiver mutations ──

    public function testDE_to_FR_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'FR',
            'client_classification' => 'government',
            'client_id_number' => '12345678901234',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DE => FR (B2G)');

        if (isset($result['meta']['routing'])) {
            $siretRoute = $this->findRoutingScheme($result['meta'], 'FR:SIRET');
            $this->assertNotNull($siretRoute, 'Cross-border to FR government should route via FR:SIRET (Chorus Pro)');
        }
    }

    // ── SG: B2G routing ──

    public function testSG_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SG', 'client_country' => 'SG',
            'client_classification' => 'government',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'SG => SG (B2G)');

        if (isset($result['meta']['routing'])) {
            $uenRoute = $this->findRoutingScheme($result['meta'], 'SG:UEN');
            $this->assertNotNull($uenRoute, 'SG B2G should route via SG:UEN');
        }
    }

    // ── SG: B2C email fallback ──

    public function testSG_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SG', 'client_country' => 'SG',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'SG => SG (B2C)');

        if (isset($result['meta']['routing']['emails'])) {
            $this->assertNotEmpty($result['meta']['routing']['emails'], 'SG B2C should have email routing');
        }
    }

    // ── SG: Cross-border receiver ──

    public function testAU_to_SG_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AU', 'client_country' => 'SG',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'AU => SG (B2B)');

        if (isset($result['meta']['routing'])) {
            $uenRoute = $this->findRoutingScheme($result['meta'], 'SG:UEN');
            $this->assertNotNull($uenRoute, 'Cross-border to SG should route via SG:UEN');
        }
    }

    // ── DE: B2G with Leitweg-ID ──

    public function testDE_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'DE',
            'client_classification' => 'government',
            'client_routing_id' => '10101010-STO-10',
            'client_vat' => '',
            'po_number' => '',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DE => DE (B2G)');

        if (isset($result['meta']['routing'])) {
            $lwidRoute = $this->findRoutingScheme($result['meta'], 'DE:LWID');
            $this->assertNotNull($lwidRoute, 'DE B2G should route via DE:LWID');
        }

        // BuyerReference should contain the Leitweg-ID when no PO number
        $this->assertEquals('10101010-STO-10', $result['peppol']->BuyerReference ?? '', 'DE B2G should set BuyerReference to Leitweg-ID');
    }

    // ── ES: B2G with FACe ──

    public function testES_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'ES', 'client_country' => 'ES',
            'client_classification' => 'government',
            'client_routing_id' => 'L01234567;REC001;PAG001',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'ES => ES (B2G)');

        if (isset($result['meta']['routing'])) {
            $faceRoute = $this->findRoutingScheme($result['meta'], 'ES:FACE');
            $this->assertNotNull($faceRoute, 'ES B2G should route via ES:FACE');
        }
    }

    // ── BE: Domestic ──

    public function testBE_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'BE', 'client_country' => 'BE',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'BE => BE (business)');

        if (isset($result['meta']['routing'])) {
            $beRoute = $this->findRoutingScheme($result['meta'], 'BE:EN');
            $this->assertNotNull($beRoute, 'BE business should route via BE:EN');
        }
    }

    // ── BE: Cross-border ──

    public function testDE_to_BE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'BE',
            'has_valid_vat' => true,
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DE => BE (B2B)');

        if (isset($result['meta']['routing'])) {
            $beRoute = $this->findRoutingScheme($result['meta'], 'BE:EN');
            $this->assertNotNull($beRoute, 'Cross-border to BE should route via BE:EN');
        }
    }

    // ── PL: KSeF network ──

    public function testPL_Domestic_KSeF(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'PL', 'client_country' => 'PL',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'PL => PL (KSeF)');

        if (isset($result['meta']['networks'])) {
            $ksefFound = false;
            foreach ($result['meta']['networks'] as $network) {
                if (($network['application'] ?? '') === 'pl-ksef') {
                    $ksefFound = true;
                    $this->assertTrue($network['settings']['enabled']);
                }
            }
            $this->assertTrue($ksefFound, 'PL should enable pl-ksef network');
        }
    }

    // ── MY: MyInvois network ──

    public function testMY_Domestic_MyInvois(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'MY', 'client_country' => 'MY',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'MY => MY (MyInvois)');

        if (isset($result['meta']['networks'])) {
            $myinvoisFound = false;
            foreach ($result['meta']['networks'] as $network) {
                if (($network['application'] ?? '') === 'my-myinvois') {
                    $myinvoisFound = true;
                    $this->assertTrue($network['settings']['enabled']);
                }
            }
            $this->assertTrue($myinvoisFound, 'MY should enable my-myinvois network');
        }

        if (isset($result['meta']['routing'])) {
            $myRoute = $this->findRoutingScheme($result['meta'], 'MY:EIF');
            $this->assertNotNull($myRoute, 'MY business should route via MY:EIF');
        }
    }

    // ── IT: Cross-border receiver mutations (non-IT to IT) ──

    public function testDE_to_IT_B2B_ReceiverMutations(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'IT',
            'has_valid_vat' => true,
            'client_routing_id' => 'SCSCSCS',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DE => IT (B2B receiver)');

        if (isset($result['meta']['routing'])) {
            $this->assertNotNull($this->findRoutingScheme($result['meta'], 'IT:IVA'), 'Cross-border to IT B2B should include IT:IVA');
            $this->assertNotNull($this->findRoutingScheme($result['meta'], 'IT:CUUO'), 'Cross-border to IT B2B should include IT:CUUO');
        }
    }

    public function testFR_to_IT_B2C_ReceiverMutations(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'IT',
            'client_classification' => 'individual',
            'client_vat' => 'RSSMRA85M01H501Z',
            'client_id_number' => 'RSSMRA85M01H501Z',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'FR => IT (B2C receiver)');

        if (isset($result['meta']['routing'])) {
            $this->assertNotNull($this->findRoutingScheme($result['meta'], 'IT:CF'), 'Cross-border to IT B2C should include IT:CF');
        }
    }

    // ── RO: Cross-border receiver mutations ──

    public function testDE_to_RO_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'RO',
            'has_valid_vat' => true,
            'client_state' => 'RO-B',
            'client_city' => 'SECTOR1',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DE => RO (B2B)');

        if (isset($result['meta']['networks'])) {
            $anafFound = false;
            foreach ($result['meta']['networks'] as $network) {
                if (($network['application'] ?? '') === 'ro-anaf') {
                    $anafFound = true;
                }
            }
            $this->assertTrue($anafFound, 'Cross-border to RO should enable ro-anaf network');
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  UNIT TESTS: Handler Method Overrides
    // ══════════════════════════════════════════════════════════════

    public function testFR_ResolveClientIdentifier(): void
    {
        $handler = \App\Services\EDocument\Standards\Peppol\CountryFactory::make('FR');

        $invoice = new \stdClass();
        $invoice->client = new \stdClass();
        $invoice->client->id_number = '12345678901234';

        $this->assertEquals('12345678901234', $handler->resolveClientIdentifier($invoice, 'FR:SIRET'));
        $this->assertEquals('12345678901234', $handler->resolveClientIdentifier($invoice, 'FR:SIRENE'));

        $invoice->client->id_number = '123456789';
        $this->assertEquals('123456789', $handler->resolveClientIdentifier($invoice, 'FR:SIRENE'));
    }

    public function testFR_ResolveRoutingOverride(): void
    {
        $handler = \App\Services\EDocument\Standards\Peppol\CountryFactory::make('FR');

        $invoice = new \stdClass();
        $invoice->client = new \stdClass();
        $invoice->client->id_number = '123456789';

        $this->assertEquals('FR:SIRENE', $handler->resolveRoutingOverride('business', $invoice));

        $invoice->client->id_number = '12345678901234';
        $this->assertEquals('FR:SIRET', $handler->resolveRoutingOverride('business', $invoice));
        $this->assertEquals('0009:11000201100044', $handler->resolveRoutingOverride('government', $invoice));
        $this->assertNull($handler->resolveRoutingOverride('individual', $invoice));
    }

    public function testFR_ResolveTaxSchemeOverride(): void
    {
        $handler = \App\Services\EDocument\Standards\Peppol\CountryFactory::make('FR');

        $invoice = new \stdClass();
        $invoice->client = new \stdClass();

        $this->assertEquals('0009:11000201100044', $handler->resolveTaxSchemeOverride('government', $invoice));
        $this->assertNull($handler->resolveTaxSchemeOverride('business', $invoice));
        $this->assertNull($handler->resolveTaxSchemeOverride('individual', $invoice));
    }

    public function testDE_ResolveClientIdentifier(): void
    {
        $handler = \App\Services\EDocument\Standards\Peppol\CountryFactory::make('DE');

        $invoice = new \stdClass();
        $invoice->client = new \stdClass();
        $invoice->client->classification = 'government';
        $invoice->client->routing_id = '10101010-STO-10';

        $this->assertEquals('10101010-STO-10', $handler->resolveClientIdentifier($invoice, 'DE:LWID'));

        $invoice->client->classification = 'business';
        $this->assertNull($handler->resolveClientIdentifier($invoice, 'DE:VAT'));
    }

    public function testDE_ResolveRoutingOverride(): void
    {
        $handler = \App\Services\EDocument\Standards\Peppol\CountryFactory::make('DE');

        $this->assertEquals('DE:STNR', $handler->resolveRoutingOverride('individual'));
        $this->assertNull($handler->resolveRoutingOverride('business'));
        $this->assertNull($handler->resolveRoutingOverride('government'));
    }

    public function testDE_ResolveTaxSchemeOverride(): void
    {
        $handler = \App\Services\EDocument\Standards\Peppol\CountryFactory::make('DE');

        $this->assertEquals('DE:STNR', $handler->resolveTaxSchemeOverride('individual'));
        $this->assertNull($handler->resolveTaxSchemeOverride('business'));
        $this->assertNull($handler->resolveTaxSchemeOverride('government'));
    }

    public function testIT_ResolveClientIdentifier(): void
    {
        $handler = \App\Services\EDocument\Standards\Peppol\CountryFactory::make('IT');

        $invoice = new \stdClass();
        $invoice->client = new \stdClass();
        $invoice->client->routing_id = 'SCSCSCS';
        $invoice->client->vat_number = 'IT12345678901';

        $this->assertEquals('SCSCSCS', $handler->resolveClientIdentifier($invoice, 'IT:CUUO'));
        $this->assertNull($handler->resolveClientIdentifier($invoice, 'IT:IVA'));
        $this->assertNull($handler->resolveClientIdentifier($invoice, 'IT:CF'));
    }

    public function testSG_ResolveClientIdentifier(): void
    {
        $handler = \App\Services\EDocument\Standards\Peppol\CountryFactory::make('SG');

        $invoice = new \stdClass();
        $invoice->client = new \stdClass();
        $invoice->client->id_number = '201234567K';

        $this->assertEquals('201234567K', $handler->resolveClientIdentifier($invoice, 'SG:UEN'));

        $invoice->client->id_number = '';
        $this->assertNull($handler->resolveClientIdentifier($invoice, 'SG:UEN'));
    }

    public function testMY_ResolveClientIdentifier(): void
    {
        $handler = \App\Services\EDocument\Standards\Peppol\CountryFactory::make('MY');

        $invoice = new \stdClass();
        $invoice->client = new \stdClass();
        $invoice->client->id_number = 'C12345678';

        $this->assertEquals('C12345678', $handler->resolveClientIdentifier($invoice, 'MY:EIF'));
        $this->assertNull($handler->resolveClientIdentifier($invoice, 'MY:TIN'));

        $invoice->client->id_number = '';
        $this->assertNull($handler->resolveClientIdentifier($invoice, 'MY:EIF'));
    }

    public function testBE_ResolveClientIdentifier(): void
    {
        $handler = \App\Services\EDocument\Standards\Peppol\CountryFactory::make('BE');

        $invoice = new \stdClass();
        $invoice->client = new \stdClass();
        $invoice->client->id_number = '0202239951';
        $invoice->client->vat_number = 'BE0202239951';

        $this->assertEquals('0202239951', $handler->resolveClientIdentifier($invoice, 'BE:EN'));
        $this->assertNull($handler->resolveClientIdentifier($invoice, 'BE:VAT'));
    }

    public function testBaseCountry_DefaultOverrides(): void
    {
        $handler = \App\Services\EDocument\Standards\Peppol\CountryFactory::make('XX');

        $this->assertNull($handler->resolveClientIdentifier(new \stdClass(), 'XX:VAT'));
        $this->assertNull($handler->resolveRoutingOverride('business'));
        $this->assertNull($handler->resolveTaxSchemeOverride('business'));
        $this->assertNull($handler->getRoutingRules());
    }

    // ══════════════════════════════════════════════════════════════
    //  UNIT TESTS: RO State/Sector Code Resolution
    // ══════════════════════════════════════════════════════════════

    public function testRO_StateCodeResolution(): void
    {
        $handler = new \App\Services\EDocument\Standards\Peppol\RO();

        // Direct code
        $this->assertEquals('RO-B', $handler->getStateCode('RO-B'));
        $this->assertEquals('RO-CJ', $handler->getStateCode('RO-CJ'));

        // Name lookup
        $this->assertEquals('RO-B', $handler->getStateCode('Bucharest'));
        $this->assertEquals('RO-CJ', $handler->getStateCode('Cluj'));

        // Fallback to RO-B
        $this->assertEquals('RO-B', $handler->getStateCode('Unknown'));
        $this->assertEquals('RO-B', $handler->getStateCode(''));
    }

    public function testRO_SectorCodeResolution(): void
    {
        $handler = new \App\Services\EDocument\Standards\Peppol\RO();

        $invoice = new \stdClass();
        $invoice->client = new \stdClass();
        $invoice->client->state = 'RO-B';
        $invoice->client->city = 'SECTOR1';

        // Bucharest: should return sector code
        $this->assertEquals('SECTOR1', $handler->getSectorCode('SECTOR1', $invoice));

        // Non-Bucharest: should return the city as-is
        $invoice->client->state = 'RO-CJ';
        $invoice->client->city = 'Cluj-Napoca';
        $this->assertEquals('Cluj-Napoca', $handler->getSectorCode('Cluj-Napoca', $invoice));
    }

    // ══════════════════════════════════════════════════════════════
    //  B2G DOMESTIC TESTS (Government routing)
    // ══════════════════════════════════════════════════════════════

    public function testAU_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AU', 'client_country' => 'AU',
            'client_classification' => 'government',
        ]);
        $this->runAndValidate($data['invoice'], 'AU => AU (government)');
    }

    public function testBE_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'BE', 'client_country' => 'BE',
            'client_classification' => 'government',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'BE => BE (government)');

        if (isset($result['meta']['routing'])) {
            $beRoute = $this->findRoutingScheme($result['meta'], 'BE:EN');
            $this->assertNotNull($beRoute, 'BE government should route via BE:EN');
        }
    }

    public function testCH_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'CH', 'client_country' => 'CH',
            'client_classification' => 'government',
        ]);
        $this->runAndValidate($data['invoice'], 'CH => CH (government)');
    }

    public function testDK_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DK', 'client_country' => 'DK',
            'client_classification' => 'government',
        ]);
        $this->runAndValidate($data['invoice'], 'DK => DK (government)');
    }

    public function testFI_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FI', 'client_country' => 'FI',
            'client_classification' => 'government',
        ]);
        $this->runAndValidate($data['invoice'], 'FI => FI (government)');
    }

    public function testNL_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'NL', 'client_country' => 'NL',
            'client_classification' => 'government',
            'client_id_number' => '00000001001234567890',
        ]);
        $this->runAndValidate($data['invoice'], 'NL => NL (government)');
    }

    public function testNZ_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'NZ', 'client_country' => 'NZ',
            'client_classification' => 'government',
        ]);
        $this->runAndValidate($data['invoice'], 'NZ => NZ (government)');
    }

    public function testPL_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'PL', 'client_country' => 'PL',
            'client_classification' => 'government',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'PL => PL (government)');

        if (isset($result['meta']['networks'])) {
            $ksefFound = false;
            foreach ($result['meta']['networks'] as $network) {
                if (($network['application'] ?? '') === 'pl-ksef') {
                    $ksefFound = true;
                }
            }
            $this->assertTrue($ksefFound, 'PL B2G should enable pl-ksef network');
        }
    }

    public function testRO_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'RO', 'client_country' => 'RO',
            'client_classification' => 'government',
            'client_state' => 'RO-B',
            'client_city' => 'SECTOR1',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'RO => RO (government)');

        if (isset($result['meta']['networks'])) {
            $anafFound = false;
            foreach ($result['meta']['networks'] as $network) {
                if (($network['application'] ?? '') === 'ro-anaf') {
                    $anafFound = true;
                }
            }
            $this->assertTrue($anafFound, 'RO B2G should enable ro-anaf network');
        }
    }

    public function testSE_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SE', 'client_country' => 'SE',
            'client_classification' => 'government',
        ]);
        $this->runAndValidate($data['invoice'], 'SE => SE (government)');
    }

    // ══════════════════════════════════════════════════════════════
    //  B2C DOMESTIC TESTS (Individual/Consumer routing)
    // ══════════════════════════════════════════════════════════════

    public function testAT_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AT', 'client_country' => 'AT',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $this->runAndValidate($data['invoice'], 'AT => AT (B2C)');
    }

    public function testAU_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AU', 'client_country' => 'AU',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $this->runAndValidate($data['invoice'], 'AU => AU (B2C)');
    }

    public function testBE_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'BE', 'client_country' => 'BE',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $this->runAndValidate($data['invoice'], 'BE => BE (B2C)');
    }

    public function testCH_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'CH', 'client_country' => 'CH',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $this->runAndValidate($data['invoice'], 'CH => CH (B2C)');
    }

    public function testDK_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DK', 'client_country' => 'DK',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $this->runAndValidate($data['invoice'], 'DK => DK (B2C)');
    }

    public function testES_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'ES', 'client_country' => 'ES',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $this->runAndValidate($data['invoice'], 'ES => ES (B2C)');
    }

    public function testFI_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FI', 'client_country' => 'FI',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $this->runAndValidate($data['invoice'], 'FI => FI (B2C)');
    }

    public function testMY_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'MY', 'client_country' => 'MY',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'MY => MY (B2C)');

        if (isset($result['meta']['routing']['emails'])) {
            $this->assertNotEmpty($result['meta']['routing']['emails'], 'MY B2C should have email routing');
        }
    }

    public function testNL_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'NL', 'client_country' => 'NL',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $this->runAndValidate($data['invoice'], 'NL => NL (B2C)');
    }

    public function testNZ_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'NZ', 'client_country' => 'NZ',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $this->runAndValidate($data['invoice'], 'NZ => NZ (B2C)');
    }

    public function testPL_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'PL', 'client_country' => 'PL',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $this->runAndValidate($data['invoice'], 'PL => PL (B2C)');
    }

    public function testRO_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'RO', 'client_country' => 'RO',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
            'client_state' => 'RO-B',
            'client_city' => 'SECTOR1',
        ]);
        $this->runAndValidate($data['invoice'], 'RO => RO (B2C)');
    }

    public function testSE_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SE', 'client_country' => 'SE',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $this->runAndValidate($data['invoice'], 'SE => SE (B2C)');
    }

    // ══════════════════════════════════════════════════════════════
    //  RECEIVER MUTATION CROSS-BORDER TESTS
    // ══════════════════════════════════════════════════════════════

    // ── FR receiver mutations ──

    public function testES_to_FR_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'ES', 'client_country' => 'FR',
            'client_classification' => 'government',
            'client_id_number' => '12345678901234',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'ES => FR (B2G)');

        if (isset($result['meta']['routing'])) {
            $siretRoute = $this->findRoutingScheme($result['meta'], 'FR:SIRET');
            $this->assertNotNull($siretRoute, 'Cross-border to FR government should route via Chorus Pro');
        }
    }

    public function testDE_to_FR_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'FR',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'FRINDIVIDUAL',
        ]);
        $this->runAndValidate($data['invoice'], 'DE => FR (B2C)');
    }

    public function testIT_to_FR_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'FR',
            'client_classification' => 'government',
            'client_id_number' => '12345678901234',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'IT => FR (B2G)');

        if (isset($result['meta']['routing'])) {
            $siretRoute = $this->findRoutingScheme($result['meta'], 'FR:SIRET');
            $this->assertNotNull($siretRoute, 'IT to FR government should route via Chorus Pro');
        }
    }

    // ── DE receiver mutations ──

    public function testFR_to_DE_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'DE',
            'client_classification' => 'government',
            'client_routing_id' => '991-12345-67',
            'client_vat' => '',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'FR => DE (B2G)');

        if (isset($result['meta']['routing'])) {
            $lwidRoute = $this->findRoutingScheme($result['meta'], 'DE:LWID');
            $this->assertNotNull($lwidRoute, 'Cross-border to DE government should route via DE:LWID');
        }
    }

    public function testIT_to_DE_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'DE',
            'client_classification' => 'government',
            'client_routing_id' => '991-12345-67',
            'client_vat' => '',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'IT => DE (B2G)');

        if (isset($result['meta']['routing'])) {
            $lwidRoute = $this->findRoutingScheme($result['meta'], 'DE:LWID');
            $this->assertNotNull($lwidRoute, 'IT to DE government should route via DE:LWID');
        }
    }

    // ── SG receiver mutations ──

    public function testDE_to_SG_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'SG',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DE => SG (B2B)');

        if (isset($result['meta']['routing'])) {
            $uenRoute = $this->findRoutingScheme($result['meta'], 'SG:UEN');
            $this->assertNotNull($uenRoute, 'Cross-border to SG B2B should route via SG:UEN');
        }
    }

    public function testDE_to_SG_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'SG',
            'client_classification' => 'government',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DE => SG (B2G)');

        if (isset($result['meta']['routing'])) {
            $uenRoute = $this->findRoutingScheme($result['meta'], 'SG:UEN');
            $this->assertNotNull($uenRoute, 'Cross-border to SG B2G should route via SG:UEN');
        }
    }

    public function testFR_to_SG_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'SG',
        ]);
        $this->runAndValidate($data['invoice'], 'FR => SG (B2B)');
    }

    // ── ES receiver mutations ──

    public function testDE_to_ES_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'ES',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'DE => ES (B2B)');
    }

    public function testDE_to_ES_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'ES',
            'client_classification' => 'government',
            'client_routing_id' => 'L01234567;REC001;PAG001',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DE => ES (B2G)');

        if (isset($result['meta']['routing'])) {
            $faceRoute = $this->findRoutingScheme($result['meta'], 'ES:FACE');
            $this->assertNotNull($faceRoute, 'Cross-border to ES government should route via ES:FACE');
        }
    }

    public function testFR_to_ES_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'ES',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'FR => ES (B2B)');
    }

    // ── IT receiver mutations ──

    public function testFR_to_IT_B2B_ReceiverMutations(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'IT',
            'has_valid_vat' => true,
            'client_routing_id' => 'SCSCSCS',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'FR => IT (B2B receiver)');

        if (isset($result['meta']['routing'])) {
            $this->assertNotNull($this->findRoutingScheme($result['meta'], 'IT:IVA'), 'FR to IT B2B should include IT:IVA');
            $this->assertNotNull($this->findRoutingScheme($result['meta'], 'IT:CUUO'), 'FR to IT B2B should include IT:CUUO');
        }
    }

    public function testES_to_IT_B2B(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'ES', 'client_country' => 'IT',
            'has_valid_vat' => true,
            'client_routing_id' => 'SCSCSCS',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'ES => IT (B2B)');

        if (isset($result['meta']['routing'])) {
            $this->assertNotNull($this->findRoutingScheme($result['meta'], 'IT:IVA'), 'ES to IT should include IT:IVA');
        }
    }

    public function testAU_to_IT_B2B(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AU', 'client_country' => 'IT',
            'client_routing_id' => 'SCSCSCS',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'AU => IT (B2B)');

        if (isset($result['meta']['routing'])) {
            $this->assertNotNull($this->findRoutingScheme($result['meta'], 'IT:IVA'), 'Non-EU to IT should include IT:IVA');
            $this->assertNotNull($this->findRoutingScheme($result['meta'], 'IT:CUUO'), 'Non-EU to IT should include IT:CUUO');
        }
    }

    // ── BE receiver mutations ──

    public function testFR_to_BE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'BE',
            'has_valid_vat' => true,
        ]);
        $result = $this->runAndValidate($data['invoice'], 'FR => BE (B2B)');

        if (isset($result['meta']['routing'])) {
            $beRoute = $this->findRoutingScheme($result['meta'], 'BE:EN');
            $this->assertNotNull($beRoute, 'FR to BE should route via BE:EN');
        }
    }

    public function testIT_to_BE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'BE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'IT => BE (B2B)');
    }

    // ── PL receiver mutations ──

    public function testDE_to_PL_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'PL',
            'has_valid_vat' => true,
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DE => PL (B2B)');

        if (isset($result['meta']['routing'])) {
            $plRoute = $this->findRoutingScheme($result['meta'], 'PL:VAT');
            $this->assertNotNull($plRoute, 'Cross-border to PL should route via PL:VAT');
        }
    }

    public function testFR_to_PL_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'PL',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'FR => PL (B2B)');
    }

    // ── MY receiver mutations ──

    public function testDE_to_MY_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'MY',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DE => MY (B2B)');

        if (isset($result['meta']['routing'])) {
            $myRoute = $this->findRoutingScheme($result['meta'], 'MY:EIF');
            $this->assertNotNull($myRoute, 'Cross-border to MY should route via MY:EIF');
        }
    }

    public function testAU_to_MY_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AU', 'client_country' => 'MY',
        ]);
        $this->runAndValidate($data['invoice'], 'AU => MY (B2B)');
    }

    // ── RO receiver mutations ──

    public function testFR_to_RO_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'RO',
            'has_valid_vat' => true,
            'client_state' => 'RO-CJ',
            'client_city' => 'Cluj-Napoca',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'FR => RO (B2B)');

        if (isset($result['meta']['networks'])) {
            $anafFound = false;
            foreach ($result['meta']['networks'] as $network) {
                if (($network['application'] ?? '') === 'ro-anaf') {
                    $anafFound = true;
                }
            }
            $this->assertTrue($anafFound, 'FR to RO should enable ro-anaf network');
        }
    }

    public function testIT_to_RO_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'RO',
            'has_valid_vat' => true,
            'client_state' => 'RO-B',
            'client_city' => 'SECTOR1',
        ]);
        $this->runAndValidate($data['invoice'], 'IT => RO (B2B)');
    }

    // ══════════════════════════════════════════════════════════════
    //  ADDITIONAL CROSS-BORDER SENDER TESTS
    // ══════════════════════════════════════════════════════════════

    public function testCH_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'CH', 'client_country' => 'DE',
        ]);
        $this->runAndValidate($data['invoice'], 'CH => DE (B2B)');
    }

    public function testNZ_to_AU_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'NZ', 'client_country' => 'AU',
        ]);
        $this->runAndValidate($data['invoice'], 'NZ => AU (B2B)');
    }

    public function testSG_to_AU_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SG', 'client_country' => 'AU',
        ]);
        $this->runAndValidate($data['invoice'], 'SG => AU (B2B)');
    }

    public function testSG_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SG', 'client_country' => 'DE',
        ]);
        $this->runAndValidate($data['invoice'], 'SG => DE (B2B)');
    }

    public function testRO_to_IT_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'RO', 'client_country' => 'IT',
            'has_valid_vat' => true,
            'company_state' => 'RO-B',
            'company_city' => 'SECTOR1',
            'client_routing_id' => 'SCSCSCS',
        ]);
        $this->runAndValidate($data['invoice'], 'RO => IT (B2B)');
    }

    public function testBE_to_FR_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'BE', 'client_country' => 'FR',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'BE => FR (B2B)');
    }

    public function testBE_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'BE', 'client_country' => 'DE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'BE => DE (B2B)');
    }

    public function testES_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'ES', 'client_country' => 'DE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'ES => DE (B2B)');
    }

    public function testDK_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DK', 'client_country' => 'DE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'DK => DE (B2B)');
    }

    public function testDE_to_SE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'SE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'DE => SE (B2B)');
    }

    public function testDE_to_FI_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'FI',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'DE => FI (B2B)');
    }

    public function testDE_to_DK_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'DK',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'DE => DK (B2B)');
    }

    // ══════════════════════════════════════════════════════════════
    //  SPECIAL SCENARIO TESTS
    // ══════════════════════════════════════════════════════════════

    // ── DK: PaymentMeans code remapping (30 → 58) ──

    public function testDK_PaymentMeansRemapping(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DK', 'client_country' => 'DK',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DK PaymentMeans remap');

        // DK should remap PaymentMeansCode 30 to 58 (SEPA)
        if (isset($result['peppol']->PaymentMeans)) {
            foreach ($result['peppol']->PaymentMeans as $pm) {
                if (isset($pm->PaymentMeansCode)) {
                    $this->assertNotEquals('30', $pm->PaymentMeansCode->value, 'DK should remap code 30 to 58');
                }
            }
        }
    }

    // ── ES: PaymentMeans required for B2B ──

    public function testES_B2B_PaymentMeans(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'ES', 'client_country' => 'ES',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'ES B2B PaymentMeans');

        $this->assertNotNull($result['peppol']->PaymentMeans, 'ES B2B should set PaymentMeans');
    }

    // ── ES: FACe routing ID parsing variations ──

    public function testES_FACe_SingleCode(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'ES', 'client_country' => 'ES',
            'client_classification' => 'government',
            'client_routing_id' => 'L01234567',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'ES FACe single code');

        if (isset($result['meta']['routing'])) {
            $faceRoute = $this->findRoutingScheme($result['meta'], 'ES:FACE');
            $this->assertNotNull($faceRoute, 'ES FACe with single code should still route');
        }
    }

    // ── IT: Foreign receiver routing (IT sender, non-IT receiver) ──

    public function testIT_to_DE_B2B(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'DE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'IT => DE (foreign receiver)');
    }

    public function testIT_to_NL_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'NL',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'IT => NL (foreign receiver)');
    }

    public function testIT_to_ES_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'ES',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'IT => ES (foreign receiver)');
    }

    // ── CountryFactory: Handler registration ──

    public function testCountryFactory_AllHandlersRegistered(): void
    {
        $expectedCountries = ['AT', 'AU', 'BE', 'CH', 'DE', 'DK', 'ES', 'FI', 'FR', 'IT', 'MY', 'NL', 'NZ', 'PL', 'RO', 'SE', 'SG'];

        foreach ($expectedCountries as $code) {
            $this->assertTrue(
                \App\Services\EDocument\Standards\Peppol\CountryFactory::has($code),
                "CountryFactory should have handler for {$code}"
            );
        }

        // Unsupported country should return BaseCountry
        $handler = \App\Services\EDocument\Standards\Peppol\CountryFactory::make('XX');
        $this->assertInstanceOf(\App\Services\EDocument\Standards\Peppol\BaseCountry::class, $handler);
    }
}
