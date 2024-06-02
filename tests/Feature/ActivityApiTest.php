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

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;
use Tests\TestDataProvider;

/**
 * @test
 * @covers App\Http\Controllers\ActivityController
 */
class ActivityApiTest extends TestCase
{
    public $company;
    public $token;

    protected function setUp(): void
    {
        parent::setUp();

        $data = (new \Tests\TestDataProvider())->init();

        $this->company = $data->company;
        $this->token = $data->token;

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->company->account->forceDelete();
    }

    public function testActivityEntity()
    {

        $invoice = $this->company->invoices()->first();

        $invoice->service()->markSent()->markPaid()->markDeleted()->handleRestore()->save();

        $data = [
            'entity' => 'invoice',
            'entity_id' => $invoice->hashed_id
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/activities/entity', $data);


        $response->assertStatus(200);

    }

    public function testActivityGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/activities/');

        $response->assertStatus(200);
    }

    public function testActivityGetWithReact()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/activities?react=true');

        $response->assertStatus(200);
    }
}
