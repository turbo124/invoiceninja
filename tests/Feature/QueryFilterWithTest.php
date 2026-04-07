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

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Tests for QueryFilters::with() method.
 *
 * Covers: single ID, multiple comma-separated IDs, non-ID property (product_key),
 * empty value, and ensuring the "with" record appears in results.
 */
class QueryFilterWithTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    // ──────────────────────────────────────────────────────────────
    //  Path 1: with_property == 'id', single hashed ID
    // ──────────────────────────────────────────────────────────────

    public function testWithSingleInvoiceId(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?with=' . $this->invoice->hashed_id);

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->invoice->hashed_id), 'Single with= should include the requested invoice');
    }

    public function testWithSingleClientId(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/clients?with=' . $this->client->hashed_id);

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->client->hashed_id), 'Single with= should include the requested client');
    }

    // ──────────────────────────────────────────────────────────────
    //  Path 2: with_property == 'id', multiple comma-separated IDs
    // ──────────────────────────────────────────────────────────────

    public function testWithMultipleInvoiceIds(): void
    {
        // Create a second invoice
        $invoice2 = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        $invoice2 = $invoice2->calc()->getInvoice();
        $invoice2->service()->createInvitations()->markSent()->save();

        $withParam = $this->invoice->hashed_id . ',' . $invoice2->hashed_id;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?with=' . $withParam);

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->invoice->hashed_id), 'Multi with= should include first invoice');
        $this->assertTrue($ids->contains($invoice2->hashed_id), 'Multi with= should include second invoice');
    }

    public function testWithMultipleClientIds(): void
    {
        $client2 = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $withParam = $this->client->hashed_id . ',' . $client2->hashed_id;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/clients?with=' . $withParam);

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->client->hashed_id), 'Multi with= should include first client');
        $this->assertTrue($ids->contains($client2->hashed_id), 'Multi with= should include second client');
    }

    // ──────────────────────────────────────────────────────────────
    //  Path 3: with_property == 'product_key' (ProductFilters)
    // ──────────────────────────────────────────────────────────────

    public function testWithProductKey(): void
    {
        $productKey = $this->product->product_key;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/products?with=' . urlencode($productKey));

        $response->assertStatus(200);

        $keys = collect($response->json('data'))->pluck('product_key');
        $this->assertTrue($keys->contains($productKey), 'with= on products should match by product_key');
    }

    // ──────────────────────────────────────────────────────────────
    //  Path 4: empty value — should return normal list, no error
    // ──────────────────────────────────────────────────────────────

    public function testWithEmptyValue(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?with=');

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────
    //  Edge: with= doesn't break other filters
    // ──────────────────────────────────────────────────────────────

    public function testWithCombinedWithStatusFilter(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?with=' . $this->invoice->hashed_id . '&status=active');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->invoice->hashed_id), 'with= combined with status filter should still include the with record');
    }

    // ──────────────────────────────────────────────────────────────
    //  Edge: with= param not present at all
    // ──────────────────────────────────────────────────────────────

    public function testWithoutWithParam(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices');

        $response->assertStatus(200);
    }
}
