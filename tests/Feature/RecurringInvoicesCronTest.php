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

namespace Tests\Feature;

use App\Models\RecurringInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Jobs\Cron\RecurringInvoicesCron
 */
class RecurringInvoicesCronTest extends TestCase
{
    use MockAccountData;
    protected function tearDown(): void
    {
        parent::tearDown();
        //$this->account->forceDelete();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testCountCorrectNumberOfRecurringInvoicesDue()
    {
        //spin up 5 valid and 1 invalid recurring invoices
        $recurring_invoices = RecurringInvoice::where('company_id', $this->company->id)
                    ->where('next_send_date', '<=', Carbon::now()->addMinutes(30))->get();

        $recurring_all = RecurringInvoice::where('company_id', $this->company->id)->get();

        $this->assertEquals(5, $recurring_invoices->count());

        $this->assertEquals(7, $recurring_all->count());
    }
}
