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

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Quote;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 *
 * @covers App\Http\Controllers\ProjectController
 */
class ProjectApiTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;
    use MockAccountData;

    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testProjectIncludesZeroCount(): void
    {

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/projects/{$this->project->hashed_id}?include=expenses,invoices,quotes");

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(0, count($arr['data']['invoices']));
        $this->assertEquals(0, count($arr['data']['expenses']));
        $this->assertEquals(0, count($arr['data']['quotes']));

    }

    public function testProjectIncludes(): void
    {
        $i = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->project->client_id,
            'project_id' => $this->project->id,
        ]);

        $e = Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->project->client_id,
            'project_id' => $this->project->id,
        ]);

        $q = Quote::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->project->client_id,
            'project_id' => $this->project->id,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/projects/{$this->project->hashed_id}?include=expenses,invoices,quotes");

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(1, count($arr['data']['invoices']));
        $this->assertEquals(1, count($arr['data']['expenses']));
        $this->assertEquals(1, count($arr['data']['quotes']));

    }

    public function testProjectValidationForBudgetedHoursPut(): void
    {

        $data = $this->project->toArray();
        $data['budgeted_hours'] = 'aa';

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/projects/{$this->project->hashed_id}", $data);

        $response->assertStatus(422);

    }

    public function testProjectValidationForBudgetedHoursPutNull(): void
    {

        $data = $this->project->toArray();
        $data['budgeted_hours'] = null;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/projects/{$this->project->hashed_id}", $data);

        $response->assertStatus(200);

    }

    public function testProjectValidationForBudgetedHoursPutEmpty(): void
    {

        $data = $this->project->toArray();
        $data['budgeted_hours'] = '';

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/projects/{$this->project->hashed_id}", $data);

        $response->assertStatus(200);

    }

    public function testProjectValidationForBudgetedHours(): void
    {

        $data = [
            'name' => $this->faker->firstName(),
            'client_id' => $this->client->hashed_id,
            'number' => 'duplicate',
            'budgeted_hours' => null,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/projects', $data);

        $response->assertStatus(200);

    }

    public function testProjectValidationForBudgetedHours2(): void
    {

        $data = [
            'name' => $this->faker->firstName(),
            'client_id' => $this->client->hashed_id,
            'number' => 'duplicate',
            'budgeted_hours' => 'a',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/projects', $data);

        $response->assertStatus(422);

    }

    public function testProjectValidationForBudgetedHours3(): void
    {

        $data = [
            'name' => $this->faker->firstName(),
            'client_id' => $this->client->hashed_id,
            'number' => 'duplicate',
            'budgeted_hours' => '',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/projects', $data);

        $response->assertStatus(200);

    }

    public function testProjectGetFilter(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/projects?filter=xx');

        $response->assertStatus(200);
    }

    public function testProjectGet(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/projects/'.$this->encodePrimaryKey($this->project->id));

        $response->assertStatus(200);
    }

    public function testProjectPost(): void
    {
        $data = [
            'name' => $this->faker->firstName(),
            'client_id' => $this->client->hashed_id,
            'number' => 'duplicate',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/projects', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/projects/'.$arr['data']['id'], $data)->assertStatus(200);

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/projects', $data);
        } catch (ValidationException $e) {
            $response->assertStatus(302);
        }
    }

    public function testProjectPostFilters(): void
    {
        $data = [
            'name' => 'Sherlock',
            'client_id' => $this->client->hashed_id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/projects', $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/projects?filter=Sherlock');

        $arr = $response->json();

        $this->assertEquals(1, count($arr['data']));
    }

    public function testProjectPut(): void
    {
        $data = [
            'name' => $this->faker->firstName(),
            'public_notes' => 'Coolio',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/projects/'.$this->encodePrimaryKey($this->project->id), $data);

        $response->assertStatus(200);
    }

    public function testProjectNotArchived(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/projects/'.$this->encodePrimaryKey($this->project->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testProjectArchived(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->project->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/projects/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testProjectRestored(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->project->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/projects/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testProjectDeleted(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->project->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/projects/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }
}
