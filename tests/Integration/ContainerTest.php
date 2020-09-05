<?php

namespace Tests\Integration;

use App\Models\Company;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class ContainerTest extends TestCase
{
    use MockAccountData;

    public function setUp() :void
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
