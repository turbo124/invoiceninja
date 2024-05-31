<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Export;

use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class ReportApiTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        // $this->withoutExceptionHandling();
        $this->makeTestData();

    }

    public function testActivityCSVExport(): void
    {
        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/activities', $data)
            ->assertStatus(200);

    }

    public function testUserSalesReportApiRoute(): void
    {
        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/user_sales_report', $data)
            ->assertStatus(200);

    }

    public function testTaxSummaryReportApiRoute(): void
    {
        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/tax_summary_report', $data)
            ->assertStatus(200);

    }

    public function testClientSalesReportApiRoute(): void
    {
        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/client_sales_report', $data)
            ->assertStatus(200);

    }

    public function testArDetailReportApiRoute(): void
    {
        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/ar_detail_report', $data)
            ->assertStatus(200);

    }

    public function testArSummaryReportApiRoute(): void
    {
        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/ar_summary_report', $data)
            ->assertStatus(200);

    }

    public function testClientBalanceReportApiRoute(): void
    {
        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/client_balance_report', $data)
            ->assertStatus(200);

    }
}
