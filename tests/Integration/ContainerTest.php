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

use Tests\TestCase;
use App\Models\Company;
use Tests\MockAccountData;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 */
class ContainerTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        app()->instance(Company::class, $this->company);
    }

    public function testBindingWorks()
    {
        $resolved_company = resolve(Company::class);

        $this->assertNotNull($resolved_company);

        $this->assertEquals($this->account->id, $resolved_company->account_id);
    }
}
