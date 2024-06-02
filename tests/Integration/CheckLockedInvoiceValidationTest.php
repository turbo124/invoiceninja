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

namespace Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use JsonSchema\Exception\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\ValidationRules\Invoice\LockedInvoiceRule
 */
class CheckLockedInvoiceValidationTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    protected function tearDown(): void
    {

        $this->account->forceDelete();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testValidationWorksForLockedInvoiceWhenOff()
    {
        $invoice_update = [
            'po_number' => 'test',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id), $invoice_update)
            ->assertStatus(200);

    }

}
