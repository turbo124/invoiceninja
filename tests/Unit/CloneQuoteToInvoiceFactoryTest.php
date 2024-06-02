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

namespace Tests\Unit;

use App\Factory\CloneQuoteToInvoiceFactory;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class CloneQuoteToInvoiceFactoryTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    protected function tearDown(): void
    {

        $this->account->forceDelete();
        parent::tearDown();
    }

    public function testCloneProperties()
    {
        $invoice = CloneQuoteToInvoiceFactory::create($this->quote, $this->quote->user_id);

        $this->assertNull($invoice->due_date);
        $this->assertNull($invoice->partial_due_date);
        $this->assertNull($invoice->number);
    }

    public function testQuoteToInvoiceConversionService()
    {
        $invoice = $this->quote->service()->convertToInvoice();

        $this->assertTrue($invoice instanceof Invoice);
    }
}
