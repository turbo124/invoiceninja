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

namespace Tests\Integration;

use App\Models\Credit;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Utils\HtmlEngine;
use App\Utils\Traits\MakesHash;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 */
class HtmlGenerationTest extends TestCase
{
    use MockAccountData;
    use MakesHash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testHtmlOutput()
    {
        $this->client->fresh();

        $html = $this->generateHtml($this->invoice->fresh());

        $this->assertNotNull($html);
    }

}
