<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Events\Vendor\VendorContactLoggedIn;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 *
 * @covers App\Http\Controllers\VendorController
 */
class VendorApiTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;
    use MockAccountData;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testVendorContactCreation(): void
    {
        $data = [
            'name' => 'hewwo',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/vendors', $data);

        $arr = $response->json();

        $this->assertEquals('hewwo', $arr['data']['name']);
        $this->assertEquals(1, count($arr['data']['contacts']));
    }

    public function testVendorLoggedInEvents(): void
    {
        $v = \App\Models\Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $vc = \App\Models\VendorContact::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'vendor_id' => $v->id,
        ]);

        $this->assertNull($v->last_login);
        $this->assertNull($vc->last_login);

        Event::fake();
        event(new VendorContactLoggedIn($vc, $this->company, Ninja::eventVars()));

        Event::assertDispatched(VendorContactLoggedIn::class);

    }

    public function testVendorLocale(): void
    {
        $v = \App\Models\Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $this->assertNotNull($v->locale());
    }

    public function testVendorLocaleEn(): void
    {
        $v = \App\Models\Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'language_id' => '1',
        ]);

        $this->assertEquals('en', $v->locale());
    }

    public function testVendorLocaleEnCompanyFallback(): void
    {
        $settings = $this->company->settings;
        $settings->language_id = '2';

        $c = \App\Models\Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $v = \App\Models\Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $c->id,
        ]);

        $this->assertEquals('it', $v->locale());
    }

    public function testVendorGetFilter(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/vendors?filter=xx');

        $response->assertStatus(200);
    }

    public function testAddVendorLanguage200(): void
    {
        $data = [
            'name' => $this->faker->firstName(),
            'language_id' => 2,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/vendors', $data)->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals('2', $arr['data']['language_id']);

        $id = $arr['data']['id'];

        $data = [
            'language_id' => 3,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/vendors/{$id}", $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals('3', $arr['data']['language_id']);

    }

    public function testAddVendorLanguage422(): void
    {
        $data = [
            'name' => $this->faker->firstName(),
            'language_id' => '4431',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/vendors', $data)->assertStatus(422);

    }

    public function testAddVendorLanguage(): void
    {
        $data = [
            'name' => $this->faker->firstName(),
            'language_id' => '1',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/vendors', $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals('1', $arr['data']['language_id']);
    }

    public function testAddVendorToInvoice(): void
    {
        $data = [
            'name' => $this->faker->firstName(),
            'language_id' => '',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/vendors', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $vendor_id = $arr['data']['id'];

        $data = [
            'vendor_id' => $vendor_id,
            'client_id' => $this->client->hashed_id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($arr['data']['vendor_id'], $vendor_id);
    }

    public function testAddVendorToRecurringInvoice(): void
    {
        $data = [
            'name' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/vendors', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $vendor_id = $arr['data']['id'];

        $data = [
            'vendor_id' => $vendor_id,
            'client_id' => $this->client->hashed_id,
            'frequency_id' => 1,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_invoices', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($arr['data']['vendor_id'], $vendor_id);
    }

    public function testAddVendorToQuote(): void
    {
        $data = [
            'name' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/vendors', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $vendor_id = $arr['data']['id'];

        $data = [
            'vendor_id' => $vendor_id,
            'client_id' => $this->client->hashed_id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/quotes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($arr['data']['vendor_id'], $vendor_id);
    }

    public function testAddVendorToCredit(): void
    {
        $data = [
            'name' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/vendors', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $vendor_id = $arr['data']['id'];

        $data = [
            'vendor_id' => $vendor_id,
            'client_id' => $this->client->hashed_id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($arr['data']['vendor_id'], $vendor_id);
    }

    public function testVendorPost(): void
    {
        $data = [
            'name' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/vendors', $data);

        $response->assertStatus(200);
    }

    public function testVendorPut(): void
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'number' => 'wiggles',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/vendors/'.$this->encodePrimaryKey($this->vendor->id), $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('Coolio', $arr['data']['id_number']);
        $this->assertEquals('wiggles', $arr['data']['number']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/vendors/'.$this->encodePrimaryKey($this->vendor->id), $data);

        $response->assertStatus(200);

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/vendors/', $data);
        } catch (ValidationException $e) {
            $response->assertStatus(302);
        }
    }

    public function testVendorGet(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/vendors/'.$this->encodePrimaryKey($this->vendor->id));

        $response->assertStatus(200);
    }

    public function testVendorNotArchived(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/vendors/'.$this->encodePrimaryKey($this->vendor->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testVendorArchived(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->vendor->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/vendors/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testVendorRestored(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->vendor->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/vendors/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testVendorDeleted(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->vendor->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/vendors/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }
}
