<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Models\Presenters\ClientPresenter
 */
class ClientPresenterTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testCompanyName()
    {
        $settings = $this->client->company->settings;

        $settings->name = 'test';
        $this->client->company->settings = $settings;
        $this->client->company->save();

        $this->client->getSetting('name');

        $merged_settings = $this->client->getMergedSettings();

        $name = $this->client->present()->company_name();

        $this->assertEquals('test', $merged_settings->name);
        $this->assertEquals('test', $name);
    }

    public function testCompanyAddress()
    {
        $this->assertNotNull($this->client->present()->company_address());
    }
}
