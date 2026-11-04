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

use App\Factory\CompanyGatewayFactory;
use App\Factory\CompanyUserFactory;
use App\Factory\GroupSettingFactory;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProductController;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Backup;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyLedger;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Design;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\SystemLog;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Webhook;
use App\Transformers\ArraySerializer;
use App\Transformers\CompanyTransformer;
use App\Transformers\EntityTransformer;
use App\Transformers\ProductTransformer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

class IncludePermissionsTest extends TestCase
{
    use DatabaseTransactions;

    private Account $account;
    private Company $company;
    private Company $second_company;
    private User $admin_user;
    private User $restricted_user;
    private string $admin_token;
    private string $restricted_token;
    private Client $client;
    private Collection $clients;
    private ClientContact $contact;
    private Project $project;
    private Invoice $invoice;
    private Task $task;
    private Payment $payment;
    private BankIntegration $bank_integration;
    private BankTransaction $bank_transaction;
    private ?string $fixture_connection = null;

    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseTransactions registered its rollback first, including on failure.
        $this->beforeApplicationDestroyed(fn () => $this->assertFixtureWasRolledBack());
    }

    protected function connectionsToTransact(): array
    {
        return [$this->fixture_connection ??= config('database.default')];
    }

    private function assertFixtureWasRolledBack(): void
    {
        $connection = DB::connection($this->fixture_connection);

        foreach ([
            'account', 'company', 'second_company', 'admin_user', 'restricted_user', 'client', 'contact',
            'project', 'invoice', 'task', 'payment', 'bank_integration', 'bank_transaction',
        ] as $property) {
            if (! isset($this->{$property})) {
                continue;
            }

            $record = $this->{$property};
            $this->assertFalse(
                $connection->table($record->getTable())->where($record->getKeyName(), $record->getKey())->exists(),
                "Fixture {$property} was not rolled back."
            );
        }

        if (isset($this->company)) {
            foreach (['company_user', 'company_tokens', 'clients', 'company_gateways', 'client_contacts', 'webhooks', 'system_logs', 'company_ledgers'] as $table) {
                $this->assertFalse($connection->table($table)->where('company_id', $this->company->id)->exists());
            }
        }

        if (isset($this->payment)) {
            $this->assertFalse($connection->table('paymentables')->where('payment_id', $this->payment->id)->exists());
        }
    }

    private function createFixture(string $permissions): void
    {
        $this->account = Account::factory()->create(['num_users' => 2]);
        $this->company = Company::factory()->create(['account_id' => $this->account->id]);

        $this->account->default_company_id = $this->company->id;
        $this->account->save();

        $this->admin_user = $this->createUser('[]', true);
        $this->restricted_user = $this->createUser($permissions);
        $this->admin_token = $this->createToken($this->admin_user);
        $this->restricted_token = $this->createToken($this->restricted_user);

        $this->createAdminData();
    }

    private function createUser(string $permissions, bool $is_admin = false): User
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => Str::uuid().'@example.test',
            'confirmation_code' => Str::random(32),
        ]);

        $company_user = CompanyUserFactory::create($user->id, $this->company->id, $this->account->id);
        $company_user->is_owner = false;
        $company_user->is_admin = $is_admin;
        $company_user->is_locked = false;
        $company_user->permissions = $permissions;
        $company_user->save();

        return $user;
    }

    private function createToken(User $user, ?Company $company = null): string
    {
        $company ??= $this->company;

        $company_token = new CompanyToken();
        $company_token->account_id = $this->account->id;
        $company_token->company_id = $company->id;
        $company_token->user_id = $user->id;
        $company_token->name = 'Include permissions test token';
        $company_token->token = Str::random(64);
        $company_token->is_system = true;
        $company_token->save();

        return $company_token->token;
    }

    private function createAdminData(): void
    {
        $admin_attributes = [
            'company_id' => $this->company->id,
            'user_id' => $this->admin_user->id,
        ];

        $this->client = Client::factory()->create(array_merge($admin_attributes, [
            'balance' => 125,
            'paid_to_date' => 50,
            'credit_balance' => 25,
            'payment_balance' => 10,
        ]));
        $this->clients = Client::factory()->count(2)->create($admin_attributes)->prepend($this->client);

        $this->contact = ClientContact::factory()->create(array_merge($admin_attributes, [
            'client_id' => $this->client->id,
            'is_primary' => true,
        ]));

        $this->project = Project::factory()->create(array_merge($admin_attributes, [
            'client_id' => $this->client->id,
        ]));

        $this->invoice = Invoice::factory()->create(array_merge($admin_attributes, [
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'amount' => 175,
            'balance' => 125,
            'paid_to_date' => 50,
        ]));

        $this->task = Task::factory()->create(array_merge($admin_attributes, [
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'invoice_id' => $this->invoice->id,
        ]));

        $this->payment = Payment::factory()->create(array_merge($admin_attributes, [
            'client_id' => $this->client->id,
            'amount' => 50,
        ]));
        $this->payment->invoices()->attach($this->invoice->id, [
            'amount' => 50,
            'refunded' => 0,
        ]);

        $this->bank_integration = BankIntegration::factory()->create(array_merge($admin_attributes, [
            'account_id' => $this->account->id,
            'balance' => 1000,
        ]));

        $this->bank_transaction = BankTransaction::factory()->create(array_merge($admin_attributes, [
            'bank_integration_id' => $this->bank_integration->id,
        ]));
    }

    public function testFixtureBuildsAccountAndCompany(): void
    {
        $this->createFixture('[]');

        $account = $this->account->fresh();
        $company = $this->company->fresh();

        $this->assertSame($company->id, $account->default_company_id);
        $this->assertSame($account->id, $company->account_id);
        $this->assertEquals(2, $account->num_users);
        $this->assertSame($this->fixture_connection, $company->db);
        $this->assertSame([$company->id], $account->companies()->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$this->admin_user->id, $this->restricted_user->id],
            $account->users()->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$this->admin_user->id, $this->restricted_user->id],
            $company->users()->pluck('users.id')->all()
        );
    }

    public function testFixtureCreatesAdminUserWithFullPermissions(): void
    {
        $this->createFixture('[]');

        $company_user = $this->company->company_users()->where('user_id', $this->admin_user->id)->firstOrFail();

        $this->assertTrue((bool) $company_user->is_admin);
        $this->assertFalse((bool) $company_user->is_owner);
        $this->assertFalse((bool) $company_user->is_locked);
        $this->assertSame('[]', $company_user->permissions);
        $this->assertSame($this->account->id, $company_user->account_id);
        $this->assertTrue($this->admin_user->hasPermission('view_client'));
        $this->assertTrue($this->admin_user->hasPermission('edit_invoice'));
    }

    public static function restrictedPermissions(): array
    {
        return [
            'no permissions' => ['[]'],
            'view clients' => ['["view_client"]'],
            'multiple permissions' => ['["view_client","create_invoice"]'],
            'comma separated permissions' => ['view_client,create_invoice'],
        ];
    }

    #[DataProvider('restrictedPermissions')]
    public function testFixturePreservesRestrictedUserPermissions(string $permissions): void
    {
        $this->createFixture($permissions);

        $company_user = $this->company->company_users()->where('user_id', $this->restricted_user->id)->firstOrFail();

        $this->assertFalse((bool) $company_user->is_admin);
        $this->assertFalse((bool) $company_user->is_owner);
        $this->assertFalse((bool) $company_user->is_locked);
        $this->assertSame($permissions, $company_user->permissions);
        $this->assertSame($this->account->id, $this->restricted_user->account_id);
        $this->assertSame($this->account->id, $company_user->account_id);
    }

    public static function fixtureUsers(): array
    {
        return [
            'admin' => ['admin_user', 'admin_token'],
            'restricted user' => ['restricted_user', 'restricted_token'],
        ];
    }

    #[DataProvider('fixtureUsers')]
    public function testFixtureTokensAuthenticateBothUsers(string $user_property, string $token_property): void
    {
        $this->createFixture('[]');

        $this->assertNotSame($this->admin_token, $this->restricted_token);
        $this->assertSame(2, $this->company->tokens()->count());

        $token = CompanyToken::where('token', $this->{$token_property})->firstOrFail();
        $this->assertSame($this->{$user_property}->id, $token->user_id);
        $this->assertSame($this->company->id, $token->company_id);
        $this->assertSame($this->account->id, $token->account_id);
        $this->assertTrue($token->is_system);

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->{$token_property},
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id)
            ->assertOk()
            ->assertJsonPath('data.id', $this->company->hashed_id);
    }

    public function testFixtureCreatesAdminOwnedRecords(): void
    {
        $this->createFixture('[]');

        foreach ([
            ...$this->clients, $this->contact, $this->project, $this->invoice,
            $this->task, $this->payment, $this->bank_integration, $this->bank_transaction,
        ] as $record) {
            $record = $record->fresh();
            $this->assertSame($this->admin_user->id, $record->user_id);
            $this->assertSame($this->company->id, $record->company_id);

            if (array_key_exists('assigned_user_id', $record->getAttributes())) {
                $this->assertNull($record->assigned_user_id);
            }
        }

        $this->assertEquals(125, $this->client->balance);
        $this->assertEquals(50, $this->client->paid_to_date);
        $this->assertEquals(25, $this->client->credit_balance);
        $this->assertEquals(10, $this->client->payment_balance);
        $this->assertNotEmpty($this->client->client_hash);
        $this->assertEquals(1000, $this->bank_integration->balance);
    }

    public function testFixtureLinksRelatedRecords(): void
    {
        $this->createFixture('[]');

        $this->assertSame($this->client->id, $this->contact->client_id);
        $this->assertTrue((bool) $this->contact->is_primary);
        $this->assertSame($this->client->id, $this->project->client_id);
        $this->assertSame($this->client->id, $this->invoice->client_id);
        $this->assertSame($this->project->id, $this->invoice->project_id);
        $this->assertSame($this->client->id, $this->task->client_id);
        $this->assertSame($this->project->id, $this->task->project_id);
        $this->assertSame($this->invoice->id, $this->task->invoice_id);
        $this->assertSame($this->client->id, $this->payment->client_id);
        $this->assertSame($this->account->id, $this->bank_integration->account_id);
        $this->assertSame($this->bank_integration->id, $this->bank_transaction->bank_integration_id);

        $this->assertSame(1, $this->payment->invoices()->count());
        $invoice = $this->payment->invoices()->firstOrFail();
        $this->assertSame($this->invoice->id, $invoice->id);
        $this->assertEquals(50, $invoice->pivot->amount);
        $this->assertEquals(0, $invoice->pivot->refunded);
    }

    public function testFixtureCleanupRunsAfterAnException(): void
    {
        $this->createFixture('[]');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fixture cleanup check');

        throw new RuntimeException('Fixture cleanup check');
    }

    public function testFixtureCleanupRunsAfterFailedAssertion(): void
    {
        $this->createFixture('[]');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Fixture assertion cleanup check');

        $this->fail('Fixture assertion cleanup check');
    }

    public function testFixtureCleanupUsesOriginalConnection(): void
    {
        $this->createFixture('[]');

        // Teardown must roll back the fixture connection even if a request changes the default.
        config(['database.default' => 'include-permissions-unused']);
    }

    public function testCompanyIncludesRespectEntityPermissions(): void
    {
        $this->createFixture('');

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->admin_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include=clients');

        $response->assertOk()
            ->assertJsonPath('data.id', $this->company->hashed_id)
            ->assertJsonCount(3, 'data.clients');

        $this->assertEqualsCanonicalizing(
            $this->clients->map(fn ($client) => $client->hashed_id)->all(),
            array_column($response->json('data.clients'), 'id')
        );
    }

    public function testCompanyIncludesReturnNoUnrelatedClientsWithoutPermissions(): void
    {
        $this->createFixture('');

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include=clients');

        $response->assertOk()
            ->assertJsonPath('data.id', $this->company->hashed_id)
            ->assertJsonCount(0, 'data.clients');

        $this->assertArrayHasKey('documents', $response->json('data'));
    }

    public function testCompanyListNeverChecksViewCompanyPermission(): void
    {
        $this->createFixture('');
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id)->assertOk();

        $user = \Mockery::mock($this->restricted_user);
        $user->shouldNotReceive('hasPermission')->with('view_company');
        auth()->setUser($user);

        $controller = app(CompanyController::class);
        $response = (new ReflectionMethod($controller, 'listResponse'))->invoke(
            $controller, Company::query()->whereKey($this->company->id)
        );

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($this->company->hashed_id, $data['data'][0]['id']);
    }

    private function assertCompanyListHasNoIncludes(TestResponse $response, string $serializer = 'array'): void
    {
        $response->assertOk();
        $companies = $response->json($serializer === 'json' ? 'data.data' : 'data');
        $this->assertNotEmpty($companies);
        $transformer = new CompanyTransformer();
        $includes = array_unique(array_merge($transformer->getDefaultIncludes(), $transformer->getAvailableIncludes()));

        foreach ($companies as $company) {
            $this->assertArrayHasKey('id', $company);
            $attributes = $serializer === 'json' ? $company['attributes'] : $company;
            $this->assertArrayHasKey('settings', $attributes);
            foreach ($includes as $include) {
                $this->assertArrayNotHasKey($include, $attributes);
            }
            if ($serializer === 'json') {
                $this->assertArrayNotHasKey('relationships', $company);
            }
        }

        if ($serializer === 'json') {
            $this->assertArrayNotHasKey('included', $response->json('data'));
        }
    }

    public static function companyListIncludeRequests(): array
    {
        $transformer = new CompanyTransformer();
        $all_includes = implode(',', array_unique(array_merge(
            $transformer->getDefaultIncludes(), $transformer->getAvailableIncludes()
        )));
        $cases = [];

        foreach (self::fixtureUsers() as $role => [$user_property, $token_property]) {
            foreach (['array', 'json'] as $serializer) {
                foreach ([
                    'defaults' => [[], false],
                    'explicit documents' => [['include' => 'documents'], false],
                    'all declared includes' => [['include' => $all_includes], false],
                    'nested includes' => [['include' => 'company_user.company.clients,clients.contacts,invoices.payments,bank_integrations.bank_transactions'], false],
                    'first load' => [['first_load' => 'true', 'include' => 'clients'], false],
                    'large first load' => [['first_load' => 'true', 'include' => 'clients'], true],
                ] as $context => [$parameters, $is_large]) {
                    $cases[$role.' '.$serializer.' '.$context] = [$user_property, $token_property, $serializer, $parameters, $is_large];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('companyListIncludeRequests')]
    public function testCompanyListExcludesAllRelationsWithoutQueryingThem(
        string $user_property,
        string $token_property,
        string $serializer,
        array $parameters,
        bool $is_large
    ): void {
        $this->createFixture('');
        $this->company->is_large = $is_large;
        $this->company->save();
        $this->second_company = Company::factory()->create(['account_id' => $this->account->id]);
        $membership = CompanyUserFactory::create($this->{$user_property}->id, $this->second_company->id, $this->account->id);
        $membership->is_admin = false;
        $membership->is_owner = false;
        $membership->is_locked = false;
        $membership->permissions = '';
        $membership->save();

        $foreign_client = Client::factory()->create([
            'company_id' => $this->second_company->id,
            'user_id' => $this->admin_user->id,
        ]);
        $document = $this->company->documents()->save(Document::factory()->make([
            'company_id' => $this->company->id,
            'user_id' => $this->admin_user->id,
        ]));
        $this->beforeApplicationDestroyed(function () use ($foreign_client, $document) {
            foreach ([$foreign_client, $document] as $record) {
                $this->assertFalse(DB::connection($this->fixture_connection)
                    ->table($record->getTable())->where('id', $record->id)->exists());
            }
        });

        if ($serializer === 'json') {
            $parameters['serializer'] = 'json';
        }
        $connection = DB::connection($this->fixture_connection);
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->{$token_property},
            ])->getJson('/api/v1/companies?'.http_build_query($parameters));
            $queries = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }

        $this->assertCompanyListHasNoIncludes($response, $serializer);
        $companies = $response->json($serializer === 'json' ? 'data.data' : 'data');
        $this->assertEqualsCanonicalizing(
            [$this->company->hashed_id, $this->second_company->hashed_id],
            array_column($companies, 'id')
        );
        $response->assertJsonPath('meta.pagination.total', 2);

        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression(
                '/\b(?:from|join)\s+[`"]?(clients|invoices|payments|documents|company_gateways|webhooks|system_logs|company_ledgers|designs|payment_terms)\b/i',
                $query['query']
            );
        }
    }

    public function testCompanyListIgnoresForcedIncludes(): void
    {
        $this->createFixture('');
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->admin_token,
        ])->getJson('/api/v1/companies')->assertOk();

        $controller = app(CompanyController::class);
        $controller->forced_includes = ['documents', 'clients', 'company_user.company.clients'];
        $response = TestResponse::fromBaseResponse($controller->index());

        $this->assertCompanyListHasNoIncludes($response);
    }

    public static function companyEntityPermissions(): array
    {
        $cases = [];

        foreach ([
            'clients' => Client::class,
            'credits' => Credit::class,
            'expenses' => Expense::class,
            'invoices' => Invoice::class,
            'payments' => Payment::class,
            'products' => Product::class,
            'projects' => Project::class,
            'purchase_orders' => PurchaseOrder::class,
            'quotes' => Quote::class,
            'recurring_expenses' => RecurringExpense::class,
            'recurring_invoices' => RecurringInvoice::class,
            'tasks' => Task::class,
            'vendors' => Vendor::class,
        ] as $include => $model_class) {
            $entity = Str::snake(class_basename($model_class));

            foreach ([
                'without permissions' => ['', false, false],
                'with create permission' => [json_encode(['create_'.$entity]), false, false],
                'with view permission' => [json_encode(['view_'.$entity]), true, false],
                'admin' => ['', true, true],
            ] as $role => [$permissions, $can_view_all, $is_admin]) {
                foreach (['item' => true, 'list' => false, 'refresh' => true] as $response_type => $is_item) {
                    foreach (['array', 'json'] as $serializer) {
                        if ($response_type === 'refresh' && $serializer === 'json') {
                            continue;
                        }

                        $cases[$include.' '.$role.' '.$response_type.' '.$serializer] = [
                            $include, $model_class, $permissions, $can_view_all, $is_admin, $is_item, $serializer,
                            $response_type === 'refresh',
                        ];
                    }
                }
            }
        }

        return $cases;
    }

    #[DataProvider('companyEntityPermissions')]
    public function testCompanyIncludesRespectOwnershipAssignmentAndViewPermissions(
        string $include,
        string $model_class,
        string $permissions,
        bool $can_view_all,
        bool $is_admin,
        bool $is_item,
        string $serializer,
        bool $is_refresh
    ): void {
        $this->createFixture($permissions);
        $this->withoutMiddleware(ThrottleRequests::class);

        $attributes = [
            'company_id' => $this->company->id,
            'user_id' => $this->admin_user->id,
            'assigned_user_id' => null,
        ];

        if (! in_array($model_class, [Client::class, Product::class, Vendor::class], true)) {
            $attributes['client_id'] = $this->client->id;
        }

        $unrelated_entity = $model_class::factory()->create($attributes);
        $owned_entity = $model_class::factory()->create(array_replace($attributes, [
            'user_id' => $this->restricted_user->id,
        ]));
        $assigned_entity = $model_class::factory()->create(array_replace($attributes, [
            'assigned_user_id' => $this->restricted_user->id,
        ]));

        $this->second_company = Company::factory()->create(['account_id' => $this->account->id]);
        $foreign_entity = $model_class::factory()->create(array_replace($attributes, [
            'company_id' => $this->second_company->id,
            'assigned_user_id' => $this->restricted_user->id,
        ]));

        $expected_ids = $can_view_all
            ? $this->company->{$include}()->get()->map(fn ($entity) => $entity->hashed_id)->all()
            : [$owned_entity->hashed_id, $assigned_entity->hashed_id];

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $is_admin ? $this->admin_token : $this->restricted_token,
        ]);
        $response = $is_refresh
            ? $this->postJson('/api/v1/refresh?current_company=true&updated_at=0')
            : $this->getJson('/api/v1/companies'.($is_item ? '/'.$this->company->hashed_id : '').'?include='.$include.($serializer === 'json' ? '&serializer=json' : ''));

        $response->assertOk();
        if (! $is_item) {
            $this->assertCompanyListHasNoIncludes($response, $serializer);
            $data = $response->json($serializer === 'json' ? 'data.data' : 'data');
            $this->assertSame([$this->company->hashed_id], array_column($data, 'id'));

            return;
        }

        $data = $response->json($serializer === 'json' ? 'data.data' : 'data');
        $company_data = $is_refresh ? $response->json('data.0.company') : $data;
        $this->assertNotNull($company_data, 'The response must contain the active company.');
        $records = $serializer === 'json' ? data_get($company_data, 'relationships.'.$include.'.data') : $company_data[$include];
        $this->assertIsArray($records);
        $this->assertCount(count($expected_ids), $records);

        $actual_ids = array_column($records, 'id');
        $this->assertEqualsCanonicalizing($expected_ids, $actual_ids);
        $this->assertNotContains($foreign_entity->hashed_id, $actual_ids);

        if ($serializer === 'json') {
            $included_ids = collect($response->json('data.included', []))
                ->where('type', $model_class)->pluck('id')->all();
            foreach ($expected_ids as $expected_id) {
                $this->assertContains($expected_id, $included_ids);
            }
            if ($is_item) {
                $this->assertNotContains($foreign_entity->hashed_id, $included_ids);
            }
            if (! $can_view_all) {
                $this->assertNotContains($unrelated_entity->hashed_id, $included_ids);
            }
        }

        if (! $can_view_all) {
            $this->assertNotContains($unrelated_entity->hashed_id, $actual_ids);
        }
    }

    public static function generalClientPermissions(): array
    {
        return [
            'edit client' => ['["edit_client"]'],
            'view all' => ['["view_all"]'],
            'edit all' => ['["edit_all"]'],
        ];
    }

    #[DataProvider('generalClientPermissions')]
    public function testCompanyIncludesRecognizeGeneralPermissionOverrides(string $permissions): void
    {
        $this->createFixture($permissions);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include=clients');

        $response->assertOk()->assertJsonCount(3, 'data.clients');
        $this->assertEqualsCanonicalizing(
            $this->clients->map(fn ($client) => $client->hashed_id)->all(),
            array_column($response->json('data.clients'), 'id')
        );
    }

    public function testCompanyIncludesApplyPermissionsToEachRequestedEntity(): void
    {
        $this->createFixture('["view_invoice"]');

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include=clients,invoices')
            ->assertOk()
            ->assertJsonCount(0, 'data.clients')
            ->assertJsonCount(1, 'data.invoices')
            ->assertJsonPath('data.invoices.0.id', $this->invoice->hashed_id);
    }

    public function testCompanyItemFiltersTheParentCollectionForADottedInclude(): void
    {
        $this->createFixture('');
        $this->client->update(['assigned_user_id' => $this->restricted_user->id]);

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include=clients.contacts')
            ->assertOk()
            ->assertJsonCount(1, 'data.clients')
            ->assertJsonPath('data.clients.0.id', $this->client->hashed_id)
            ->assertJsonCount(1, 'data.clients.0.contacts')
            ->assertJsonPath('data.clients.0.contacts.0.id', $this->contact->hashed_id);
    }

    public function testCurrentCompanyIncludesRespectEntityPermissions(): void
    {
        $this->createFixture('');
        $this->client->update(['assigned_user_id' => $this->restricted_user->id]);

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->postJson('/api/v1/companies/current?include=clients')
            ->assertOk()
            ->assertJsonPath('data.id', $this->company->hashed_id)
            ->assertJsonCount(1, 'data.clients')
            ->assertJsonPath('data.clients.0.id', $this->client->hashed_id);
    }

    public function testCompanyForcedIncludesReplaceUnrestrictedPreloadedCollections(): void
    {
        $this->createFixture('');
        $this->client->update(['assigned_user_id' => $this->restricted_user->id]);

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id)->assertOk();

        $company = $this->company->fresh()->load('clients');
        $this->assertCount(3, $company->clients);

        $controller = app(CompanyController::class);
        $controller->forced_includes = ['clients'];

        $response = (new ReflectionMethod($controller, 'itemResponse'))->invoke($controller, $company);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(1, $data['data']['clients']);
        $this->assertSame($this->client->hashed_id, $data['data']['clients'][0]['id']);
    }

    public function testCompanyItemDoesNotQueryUnrequestedEntityCollections(): void
    {
        $this->createFixture('');
        $connection = DB::connection($this->fixture_connection);
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->restricted_token,
            ])->getJson('/api/v1/companies/'.$this->company->hashed_id);

            $queries = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }

        $response->assertOk()->assertJsonMissingPath('data.clients');
        $this->assertNotEmpty($queries);

        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression(
                '/\bfrom\s+[`"]?(clients|credits|expenses|invoices|payments|products|projects|purchase_orders|quotes|recurring_expenses|recurring_invoices|tasks|vendors|bank_integrations|bank_transactions|company_gateways)\b/i',
                $query['query']
            );
        }
    }

    public static function companyCreatorPermissions(): array
    {
        return [
            'admin' => ['', 'admin', true],
            'no permissions' => ['', 'restricted', false],
            'view all' => ['["view_all"]', 'restricted', false],
            'edit all' => ['["edit_all"]', 'restricted', false],
            'owner without admin flag' => ['', 'owner', false],
        ];
    }

    #[DataProvider('companyCreatorPermissions')]
    public function testCompanyGatewayIncludesRespectAdminAndCreator(string $permissions, string $role, bool $can_view_all): void
    {
        $this->createFixture($permissions);

        if ($role === 'owner') {
            $this->company->company_users()->where('user_id', $this->restricted_user->id)->update([
                'is_owner' => true,
            ]);
        }

        $admin_gateway = CompanyGatewayFactory::create($this->company->id, $this->admin_user->id);
        $admin_gateway->gateway_key = '3b6621f970ab18887c4f6dca78d3f8bb';
        $admin_gateway->save();

        $owned_gateway = $admin_gateway->replicate();
        $owned_gateway->user_id = $this->restricted_user->id;
        $owned_gateway->save();

        $this->second_company = Company::factory()->create(['account_id' => $this->account->id]);
        $foreign_gateway = $owned_gateway->replicate();
        $foreign_gateway->company_id = $this->second_company->id;
        $foreign_gateway->save();
        $this->beforeApplicationDestroyed(function () use ($foreign_gateway) {
            $this->assertFalse(DB::connection($this->fixture_connection)
                ->table('company_gateways')->where('id', $foreign_gateway->id)->exists());
        });

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $role === 'admin' ? $this->admin_token : $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include=company_gateways.gateway');

        $expected_ids = $can_view_all
            ? [$admin_gateway->hashed_id, $owned_gateway->hashed_id]
            : [$owned_gateway->hashed_id];

        $response->assertOk()->assertJsonCount(count($expected_ids), 'data.company_gateways');
        $this->assertEqualsCanonicalizing($expected_ids, array_column($response->json('data.company_gateways'), 'id'));

        $nested_response = $this->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include=company_user.company.company_gateways.gateway')->assertOk();
        $this->assertEqualsCanonicalizing($expected_ids, array_column($nested_response->json('data.company_user.company.company_gateways'), 'id'));

        // Forced includes must also replace an unrestricted, preloaded collection.
        $this->getJson('/api/v1/companies/'.$this->company->hashed_id)
            ->assertOk()->assertJsonMissingPath('data.company_gateways');
        $company = $this->company->fresh()->load('company_gateways');
        $controller = app(CompanyController::class);
        $controller->forced_includes = ['company_gateways'];
        $response = (new ReflectionMethod($controller, 'itemResponse'))->invoke($controller, $company);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertEqualsCanonicalizing($expected_ids, array_column($data['data']['company_gateways'], 'id'));

        if (! $can_view_all) {
            $owned_gateway->user_id = $this->admin_user->id;
            $owned_gateway->save();

            $this->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include=company_gateways')
                ->assertOk()->assertJsonCount(0, 'data.company_gateways');
        }
    }

    #[DataProvider('companyCreatorPermissions')]
    public function testCompanySensitiveIncludesRespectAdminAndCreator(string $permissions, string $role, bool $can_view_all): void
    {
        $this->createFixture($permissions);
        $this->withoutMiddleware(ThrottleRequests::class);

        if ($role === 'owner') {
            $this->company->company_users()->where('user_id', $this->restricted_user->id)->update(['is_owner' => true]);
        }

        $this->second_company = Company::factory()->create(['account_id' => $this->account->id]);
        $foreign_client = Client::factory()->create([
            'company_id' => $this->second_company->id,
            'user_id' => $this->restricted_user->id,
        ]);

        $records = [
            'webhooks' => new Webhook([
                'event_id' => Webhook::EVENT_CREATE_CLIENT,
                'target_url' => 'https://example.test/webhook',
            ]),
            'contacts' => $this->contact,
            'system_logs' => new SystemLog(['log' => ['message' => 'Include permission fixture']]),
            'ledger' => new CompanyLedger([
                'client_id' => $this->client->id,
                'company_ledgerable_type' => Client::class,
                'company_ledgerable_id' => $this->client->id,
                'notes' => 'Admin ledger entry',
            ]),
        ];
        $expected = [];
        $owned_records = [];

        foreach ($records as $include => $record) {
            $record->company_id = $this->company->id;
            $record->user_id = $this->admin_user->id;
            $record->save();

            $owned_record = $record->replicate();
            $owned_record->user_id = $this->restricted_user->id;
            if ($include === 'ledger') {
                $owned_record->notes = 'Owned ledger entry';
            }
            $owned_record->save();
            $owned_records[] = $owned_record;

            $foreign_record = $owned_record->replicate();
            $foreign_record->company_id = $this->second_company->id;
            if ($include === 'contacts' || $include === 'ledger') {
                $foreign_record->client_id = $foreign_client->id;
            }
            if ($include === 'ledger') {
                $foreign_record->company_ledgerable_id = $foreign_client->id;
            }
            $foreign_record->save();
            $this->beforeApplicationDestroyed(function () use ($foreign_record) {
                $this->assertFalse(DB::connection($this->fixture_connection)
                    ->table($foreign_record->getTable())->where('id', $foreign_record->id)->exists());
            });

            $admin_value = $include === 'ledger' ? $record->notes : $record->encodePrimaryKey($record->id);
            $owned_value = $include === 'ledger' ? $owned_record->notes : $owned_record->encodePrimaryKey($owned_record->id);
            $expected[$include] = $can_view_all
                ? [$admin_value, $owned_value]
                : [$owned_value];
        }

        $includes = implode(',', array_keys($records));
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $role === 'admin' ? $this->admin_token : $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include='.$includes)->assertOk();

        $nested_includes = implode(',', array_map(fn ($include) => 'company_user.company.'.$include, array_keys($records)));
        $nested_response = $this->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include='.$nested_includes)->assertOk();

        $refresh = $this->postJson('/api/v1/refresh?current_company=true&updated_at=0')->assertOk();
        foreach (['webhooks', 'system_logs'] as $include) {
            $this->assertEqualsCanonicalizing($expected[$include], array_column($refresh->json('data.0.company.'.$include), 'id'), $include);
        }

        $this->getJson('/api/v1/companies/'.$this->company->hashed_id)->assertOk();
        $company = $this->company->fresh()->load(array_keys($records));
        $controller = app(CompanyController::class);
        $controller->forced_includes = array_keys($records);
        $forced_response = (new ReflectionMethod($controller, 'itemResponse'))->invoke($controller, $company);
        $forced_data = json_decode($forced_response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        foreach ($expected as $include => $values) {
            $field = $include === 'ledger' ? 'notes' : 'id';
            $this->assertEqualsCanonicalizing($values, array_column($response->json('data.'.$include), $field), $include);
            $this->assertEqualsCanonicalizing($values, array_column($nested_response->json('data.company_user.company.'.$include), $field), $include);
            $this->assertEqualsCanonicalizing($values, array_column($forced_data['data'][$include], $field), $include);
        }

        if (! $can_view_all) {
            foreach ($owned_records as $record) {
                $record->user_id = $this->admin_user->id;
                $record->save();
            }

            $response = $this->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include='.$includes)->assertOk();
            foreach (array_keys($records) as $include) {
                $response->assertJsonCount(0, 'data.'.$include);
            }

            $this->postJson('/api/v1/refresh?current_company=true&updated_at=0')
                ->assertOk()
                ->assertJsonCount(0, 'data.0.company.webhooks')
                ->assertJsonCount(0, 'data.0.company.system_logs');
        }
    }

    #[DataProvider('companyCreatorPermissions')]
    public function testCompanyActivitiesAndSubscriptionsMatchRefreshOwnership(string $permissions, string $role, bool $can_view_all): void
    {
        $this->createFixture($permissions);
        $this->withoutMiddleware(ThrottleRequests::class);

        if ($role === 'owner') {
            $this->company->company_users()->where('user_id', $this->restricted_user->id)->update(['is_owner' => true]);
        }

        $this->second_company = Company::factory()->create(['account_id' => $this->account->id]);
        $records = [
            'activities' => (new Activity())->forceFill([
                'activity_type_id' => 1,
                'ip' => '127.0.0.1',
                'notes' => 'Activity permission fixture',
            ]),
            'subscriptions' => \App\Models\Subscription::factory()->make(),
        ];
        $expected = [];

        foreach ($records as $include => $record) {
            $record->company_id = $this->company->id;
            $record->user_id = $this->admin_user->id;
            $record->save();

            $owned_record = $record->replicate();
            $owned_record->user_id = $this->restricted_user->id;
            if ($include === 'subscriptions') {
                $owned_record->name = 'Owned subscription';
            }
            $owned_record->save();

            $foreign_record = $owned_record->replicate();
            $foreign_record->company_id = $this->second_company->id;
            $foreign_record->save();

            $this->beforeApplicationDestroyed(function () use ($record, $owned_record, $foreign_record) {
                foreach ([$record, $owned_record, $foreign_record] as $fixture_record) {
                    $this->assertFalse(DB::connection($this->fixture_connection)
                        ->table($fixture_record->getTable())->where('id', $fixture_record->id)->exists());
                }
            });

            $expected[$include] = $can_view_all
                ? [$record->hashed_id, $owned_record->hashed_id]
                : [$owned_record->hashed_id];
        }

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $role === 'admin' ? $this->admin_token : $this->restricted_token,
        ]);

        $responses = [
            [$this->postJson('/api/v1/refresh?current_company=true&updated_at=0'), 'data.0.company'],
            [$this->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include=activities,subscriptions'), 'data'],
            [$this->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include=company_user.company.activities,company_user.company.subscriptions'), 'data.company_user.company'],
        ];

        foreach ($responses as [$response, $path]) {
            $response->assertOk();
            foreach ($expected as $include => $ids) {
                $this->assertEqualsCanonicalizing($ids, array_column($response->json($path.'.'.$include), 'id'), $path.'.'.$include);
            }
        }
    }

    public static function bankIncludePermissions(): array
    {
        return [
            'integrations without permissions' => ['bank_integrations', '', false],
            'integrations with create permission' => ['bank_integrations', '["create_bank_transaction"]', false],
            'integrations with view permission' => ['bank_integrations', '["view_bank_transaction"]', true],
            'transactions without permissions' => ['bank_transactions', '', false],
            'transactions with create permission' => ['bank_transactions', '["create_bank_transaction"]', false],
            'transactions with view permission' => ['bank_transactions', '["view_bank_transaction"]', true],
        ];
    }

    #[DataProvider('bankIncludePermissions')]
    public function testCompanyBankIncludesRespectCreatorPermissions(string $include, string $permissions, bool $can_view_all): void
    {
        $this->createFixture($permissions);

        $source = $include === 'bank_integrations' ? $this->bank_integration : $this->bank_transaction;
        $owned_entity = $source->replicate();
        $owned_entity->user_id = $this->restricted_user->id;
        $owned_entity->save();

        $this->second_company = Company::factory()->create(['account_id' => $this->account->id]);
        $foreign_entity = $source->replicate();
        $foreign_entity->company_id = $this->second_company->id;
        $foreign_entity->user_id = $this->restricted_user->id;
        $foreign_entity->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include='.$include);

        $expected_ids = $can_view_all
            ? [$source->hashed_id, $owned_entity->hashed_id]
            : [$owned_entity->hashed_id];

        $response->assertOk()->assertJsonCount(count($expected_ids), 'data.'.$include);
        $this->assertEqualsCanonicalizing(
            $expected_ids,
            array_column($response->json('data.'.$include), 'id')
        );
    }

    public function testClientListIgnoresUnsupportedNestedInvoiceIncludes(): void
    {
        $this->createFixture('');

        $this->assertTrue($this->client->invoices()->whereKey($this->invoice->id)->exists());
        $this->assertTrue($this->invoice->payments()->whereKey($this->payment->id)->exists());

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->admin_token,
        ])->getJson('/api/v1/clients?include=invoices.payments');

        $response->assertOk()->assertJsonCount(3, 'data');

        $this->assertEqualsCanonicalizing(
            $this->clients->map(fn ($client) => $client->hashed_id)->all(),
            array_column($response->json('data'), 'id')
        );

        foreach ($response->json('data') as $client_data) {
            $this->assertArrayNotHasKey('invoices', $client_data);
            $this->assertArrayNotHasKey('payments', $client_data);
        }
    }

    #[DataProvider('companyResponseProvider')]
    public function testCompanyResponsesOnlyIncludeNestedPaymentsOnItems(bool $is_item, string $data_path): void
    {
        $this->createFixture('');

        $endpoint = '/api/v1/companies'.($is_item ? '/'.$this->company->hashed_id : '');
        $headers = [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->admin_token,
        ];

        $response = $this->withHeaders($headers)->getJson($endpoint.'?include=invoices');

        if (! $is_item) {
            $this->assertCompanyListHasNoIncludes($response);
            $this->assertCompanyListHasNoIncludes(
                $this->withHeaders($headers)->getJson($endpoint.'?include=invoices.payments')
            );

            return;
        }

        $response->assertOk()
            ->assertJsonPath($data_path.'.id', $this->company->hashed_id)
            ->assertJsonCount(1, $data_path.'.invoices')
            ->assertJsonPath($data_path.'.invoices.0.id', $this->invoice->hashed_id);

        $this->assertArrayNotHasKey('payments', $response->json($data_path.'.invoices.0'));

        $response = $this->withHeaders($headers)->getJson($endpoint.'?include=invoices.payments');

        $response->assertOk()
            ->assertJsonPath($data_path.'.id', $this->company->hashed_id)
            ->assertJsonCount(1, $data_path.'.invoices')
            ->assertJsonPath($data_path.'.invoices.0.id', $this->invoice->hashed_id)
            ->assertJsonCount(1, $data_path.'.invoices.0.payments')
            ->assertJsonPath($data_path.'.invoices.0.payments.0.id', $this->payment->hashed_id);
    }

    public static function companyResponseProvider(): array
    {
        return [
            'list' => [false, 'data.0'],
            'item' => [true, 'data'],
        ];
    }

    #[DataProvider('removedTransformerIncludeContexts')]
    public function testRemovedTransformerIncludesCannotBeRequested(string $token_property, string $include, string $parent_path, string $removed_key, bool $is_collection): void
    {
        $this->createFixture('');

        if ($token_property === 'restricted_token' && $parent_path === 'data.bank_integrations') {
            $this->bank_integration->user_id = $this->restricted_user->id;
            $this->bank_integration->save();
        }

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->{$token_property},
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include='.$include);

        $response->assertOk()->assertJsonPath('data.id', $this->company->hashed_id);

        $parents = $is_collection ? $response->json($parent_path) : [$response->json($parent_path)];
        $this->assertNotEmpty($parents);

        foreach ($parents as $parent) {
            $this->assertIsArray($parent);
            $this->assertArrayHasKey('id', $parent);
            $this->assertArrayNotHasKey($removed_key, $parent);
        }
    }

    public static function removedTransformerIncludeContexts(): array
    {
        $paths = [
            'account default company' => ['account.default_company.clients', 'data.account', 'default_company', false],
            'account memberships' => ['account.company_users.company.clients', 'data.account', 'company_users', false],
            'user companies' => ['users.companies.clients', 'data.users', 'companies', true],
            'user memberships' => ['users.company_users.company.clients', 'data.users', 'company_users', true],
            'bank integration account' => ['bank_integrations.account.company_users.company.clients', 'data.bank_integrations', 'account', true],
            'bank integration company' => ['bank_integrations.company.clients', 'data.bank_integrations', 'company', true],
        ];
        $contexts = [];

        foreach (self::fixtureUsers() as $role => [, $token_property]) {
            foreach ($paths as $name => $path) {
                $contexts[$role.' '.$name] = [$token_property, ...$path];
            }
        }

        return $contexts;
    }

    private function loginFixtureUser(User $user, bool $is_react): TestResponse
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Session::start();

        $user->password = Hash::make('include-permissions-login');
        $user->save();

        $headers = ['X-API-SECRET' => config('ninja.api_secret')];

        if ($is_react) {
            $headers['X-React'] = 'true';
        }

        return $this->withHeaders($headers)->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'include-permissions-login',
        ]);
    }

    private function assertBootstrapMembership(array $data, User $user, Company $company, string $token): void
    {
        $membership = CompanyUser::where('user_id', $user->id)->where('company_id', $company->id)->firstOrFail();

        $this->assertSame((bool) $membership->is_admin, $data['is_admin']);
        $this->assertSame((bool) $membership->is_owner, $data['is_owner']);
        $this->assertSame((bool) $membership->is_locked, $data['is_locked']);
        $this->assertSame($membership->permissions, $data['permissions']);
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayHasKey('notifications', $data);

        $this->assertSame($this->account->hashed_id, $data['account']['id']);
        $this->assertSame($this->company->hashed_id, $data['account']['default_company_id']);
        $this->assertArrayHasKey('plan', $data['account']);
        $this->assertArrayNotHasKey('default_company', $data['account']);
        $this->assertArrayNotHasKey('company_users', $data['account']);

        $this->assertSame($company->hashed_id, $data['company']['id']);
        $this->assertArrayHasKey('settings', $data['company']);
        $this->assertSame($user->hashed_id, $data['user']['id']);
        $this->assertSame($user->email, $data['user']['email']);
        $this->assertSame($data['permissions'], $data['user']['company_user']['permissions']);
        $this->assertSame($data['is_admin'], $data['user']['company_user']['is_admin']);
        $this->assertArrayHasKey('settings', $data['user']['company_user']);
        $this->assertArrayHasKey('notifications', $data['user']['company_user']);
        $this->assertArrayNotHasKey('companies', $data['user']);
        $this->assertArrayNotHasKey('company_users', $data['user']);

        $this->assertSame($token, $data['token']['token']);
        $this->assertSame($user->hashed_id, $data['token']['user_id']);
        $this->assertTrue($data['token']['is_system']);
        $this->assertDatabaseHas('company_tokens', [
            'token' => $token,
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        foreach ($data['company']['bank_integrations'] ?? [] as $bank_integration) {
            $this->assertArrayNotHasKey('account', $bank_integration);
            $this->assertArrayNotHasKey('company', $bank_integration);
        }
    }

    #[DataProvider('loginIncludeContexts')]
    public function testLoginPreservesRequiredIncludes(string $user_property, string $token_property, bool $is_react, bool $is_large): void
    {
        $this->createFixture('["view_client"]');
        $this->company->is_large = $is_large;
        $this->company->save();

        $response = $this->loginFixtureUser($this->{$user_property}, $is_react);

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company.is_large', true);

        $this->assertBootstrapMembership($response->json('data.0'), $this->{$user_property}, $this->company, $this->{$token_property});
    }

    public static function loginIncludeContexts(): array
    {
        $contexts = [];

        foreach (self::fixtureUsers() as $role => [$user_property, $token_property]) {
            foreach (['flutter small company' => [false, false], 'flutter large' => [false, true], 'react' => [true, false]] as $frontend => [$is_react, $is_large]) {
                $contexts[$role.' '.$frontend] = [$user_property, $token_property, $is_react, $is_large];
            }
        }

        return $contexts;
    }

    #[DataProvider('refreshIncludeContexts')]
    public function testRefreshPreservesRequiredIncludes(string $user_property, string $token_property, string $endpoint): void
    {
        $this->createFixture('["view_client"]');
        $this->withoutMiddleware(ThrottleRequests::class);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->{$token_property},
        ])->postJson('/api/v1/'.$endpoint.'?current_company=true');

        $response->assertOk()->assertJsonCount(1, 'data');

        $this->assertBootstrapMembership($response->json('data.0'), $this->{$user_property}, $this->company, $this->{$token_property});
    }

    #[DataProvider('refreshScopeValues')]
    public function testRefreshScopeAndDiscovery(?string $value, int $expected_count): void
    {
        $this->createFixture('');
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->second_company = Company::factory()->create(['account_id' => $this->account->id]);
        $membership = CompanyUserFactory::create($this->admin_user->id, $this->second_company->id, $this->account->id);
        $membership->is_admin = false;
        $membership->is_owner = false;
        $membership->is_locked = false;
        $membership->permissions = '';
        $membership->save();

        $client = Client::factory()->create([
            'company_id' => $this->second_company->id,
            'user_id' => $this->restricted_user->id,
            'assigned_user_id' => null,
        ]);
        $this->beforeApplicationDestroyed(function () use ($client) {
            $this->assertFalse(DB::connection($this->fixture_connection)->table('clients')->where('id', $client->id)->exists());
        });

        $parameters = ['updated_at' => 0, 'first_load' => 'true'];
        if ($value !== null) {
            $parameters['current_company'] = $value;
        }

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->admin_token,
        ])->postJson('/api/v1/refresh?'.http_build_query($parameters));

        $response->assertOk()->assertJsonCount($expected_count, 'data');
        $expected_ids = $expected_count === 1
            ? [$this->company->hashed_id]
            : [$this->company->hashed_id, $this->second_company->hashed_id];
        $this->assertEqualsCanonicalizing($expected_ids, collect($response->json('data'))->pluck('company.id')->all());

        foreach ($response->json('data') as $membership_data) {
            $this->assertNotContains($client->hashed_id, array_column($membership_data['company']['clients'] ?? [], 'id'));
        }
    }

    public static function refreshScopeValues(): array
    {
        return [
            'true scopes' => ['true', 1],
            '1 scopes' => ['1', 1],
            'on scopes' => ['on', 1],
            'yes scopes' => ['yes', 1],
            'omitted discovers' => [null, 2],
            'false discovers for native Flutter' => ['false', 2],
        ];
    }

    public static function refreshIncludeContexts(): array
    {
        $contexts = [];

        foreach (self::fixtureUsers() as $role => [$user_property, $token_property]) {
            foreach (['refresh', 'refresh_react'] as $endpoint) {
                $contexts[$role.' '.$endpoint] = [$user_property, $token_property, $endpoint];
            }
        }

        return $contexts;
    }

    #[DataProvider('multiCompanyBootstrapEndpoints')]
    public function testBootstrapPreservesEachCompanyMembership(string $endpoint): void
    {
        $this->createFixture('');
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->second_company = Company::factory()->create(['account_id' => $this->account->id]);
        $membership = CompanyUserFactory::create($this->admin_user->id, $this->second_company->id, $this->account->id);
        $membership->is_admin = false;
        $membership->is_owner = false;
        $membership->is_locked = false;
        $membership->permissions = '["view_invoice"]';
        $membership->save();
        $second_token = $this->createToken($this->admin_user, $this->second_company);

        $response = $endpoint === 'login'
            ? $this->loginFixtureUser($this->admin_user, true)
            : $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->admin_token,
            ])->postJson('/api/v1/'.$endpoint);

        $response->assertOk()->assertJsonCount(2, 'data');

        $memberships = collect($response->json('data'))->keyBy('company.id');
        $this->assertEqualsCanonicalizing([$this->company->hashed_id, $this->second_company->hashed_id], $memberships->keys()->all());

        $this->assertBootstrapMembership($memberships[$this->company->hashed_id], $this->admin_user, $this->company, $this->admin_token);
        $this->assertBootstrapMembership($memberships[$this->second_company->hashed_id], $this->admin_user, $this->second_company, $second_token);
    }

    public static function multiCompanyBootstrapEndpoints(): array
    {
        return [
            'login' => ['login'],
            'flutter refresh' => ['refresh'],
            'react refresh' => ['refresh_react'],
        ];
    }

    public static function clientFinancialPermissions(): array
    {
        return [
            'view client' => ['["view_client"]', 'restricted_token', true],
            'create invoice does not override' => ['["view_client","create_invoice"]', 'restricted_token', true],
            'view invoice overrides' => ['["view_client","view_invoice"]', 'restricted_token', false],
            'edit client overrides' => ['["edit_client"]', 'restricted_token', false],
            'view all overrides' => ['["view_all"]', 'restricted_token', false],
            'edit all overrides' => ['["edit_all"]', 'restricted_token', false],
            'admin' => ['', 'admin_token', false],
        ];
    }

    #[DataProvider('clientFinancialPermissions')]
    public function testIncludedClientFinancialFieldsRespectPermissions(string $permissions, string $token_property, bool $redacted): void
    {
        $this->createFixture($permissions);
        $this->withoutMiddleware(ThrottleRequests::class);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->{$token_property},
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include=clients');

        $response->assertOk()->assertJsonCount(3, 'data.clients');
        $client_data = collect($response->json('data.clients'))->firstWhere('id', $this->client->hashed_id);
        $refresh = $this->postJson('/api/v1/refresh?current_company=true&updated_at=0')
            ->assertOk()->assertJsonCount(3, 'data.0.company.clients');
        $refresh_client_data = collect($refresh->json('data.0.company.clients'))->firstWhere('id', $this->client->hashed_id);

        foreach (['balance' => 125, 'paid_to_date' => 50, 'credit_balance' => 25] as $field => $value) {
            $this->assertTrue(is_int($client_data[$field]) || is_float($client_data[$field]));
            $this->assertEquals($redacted ? 0 : $value, $client_data[$field]);
            $this->assertSame($client_data[$field], $refresh_client_data[$field]);
            $this->assertEquals($value, $this->client->fresh()->{$field});
        }

        $this->assertSame($redacted ? '' : $this->client->client_hash, $client_data['client_hash']);
        $this->assertSame($client_data['client_hash'], $refresh_client_data['client_hash']);
        // Preserve the current exclusion list; payment_balance is not excluded.
        $this->assertEquals(10, $client_data['payment_balance']);
    }

    public static function bankBalancePermissions(): array
    {
        return [
            'admin' => ['', 'admin', false],
            'owner' => ['', 'owner', false],
            'owner with bank permission' => ['["view_bank_transaction"]', 'owner', false],
            'creator without permissions' => ['', 'restricted', false],
            'create bank transaction' => ['["create_bank_transaction"]', 'restricted', true],
            'edit bank transaction' => ['["edit_bank_transaction"]', 'restricted', true],
            'view bank transaction' => ['["view_bank_transaction"]', 'restricted', true],
            'view all' => ['["view_all"]', 'restricted', true],
        ];
    }

    #[DataProvider('bankBalancePermissions')]
    public function testIncludedBankIntegrationBalanceRespectsPermissions(string $permissions, string $role, bool $redacted): void
    {
        $this->createFixture($permissions);
        $this->withoutMiddleware(ThrottleRequests::class);

        if ($role === 'restricted') {
            $this->bank_integration->user_id = $this->restricted_user->id;
            $this->bank_integration->save();
        } elseif ($role === 'owner') {
            $this->company->company_users()->where('user_id', $this->admin_user->id)->update([
                'is_admin' => false,
                'is_owner' => true,
            ]);
        }

        $token = $role === 'restricted' ? $this->restricted_token : $this->admin_token;

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include=bank_integrations')
            ->assertOk()
            ->assertJsonCount(1, 'data.bank_integrations')
            ->assertJsonPath('data.bank_integrations.0.id', $this->bank_integration->hashed_id)
            ->assertJsonPath('data.bank_integrations.0.balance', $redacted ? 0 : 1000);

        $this->assertEquals(1000, $this->bank_integration->fresh()->balance);

        $this->postJson('/api/v1/refresh?current_company=true&updated_at=0')
            ->assertOk()
            ->assertJsonCount(1, 'data.0.company.bank_integrations')
            ->assertJsonPath('data.0.company.bank_integrations.0.id', $this->bank_integration->hashed_id)
            ->assertJsonPath('data.0.company.bank_integrations.0.balance', $redacted ? 0 : 1000);
    }

    private function applyDottedCompanyIncludes(Builder $query, array $includes): void
    {
        $controller = app(CompanyController::class);
        $manager = (new ReflectionProperty($controller, 'manager'))->getValue($controller);
        $manager->parseIncludes($includes);
        $transformer_class = 'App\\Transformers\\'.class_basename($query->getModel()).'Transformer';

        (new ReflectionMethod($controller, 'eagerLoadResponseIncludes'))->invoke(
            $controller, $query, new $transformer_class('array')
        );
    }

    private function transformIncludedRecord($record, string $include, EntityTransformer $transformer): array
    {
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $manager->parseIncludes($include);

        return $manager->createData(new Item($record, $transformer))->toArray();
    }

    public static function dottedCompanyPermissions(): array
    {
        return [
            'no permissions' => ['', 'restricted_token', false],
            'create permission' => ['["create_client"]', 'restricted_token', false],
            'view permission' => ['["view_client"]', 'restricted_token', true],
            'admin' => ['', 'admin_token', true],
        ];
    }

    #[DataProvider('dottedCompanyPermissions')]
    public function testNestedIncludesRespectOwnershipAndAssignment(string $permissions, string $token_property, bool $can_view_all): void
    {
        $this->createFixture($permissions);
        $this->client->forceFill(['user_id' => $this->restricted_user->id])->save();
        $assigned_client = $this->clients[1];
        $assigned_client->update(['assigned_user_id' => $this->restricted_user->id]);
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->restricted_user->id,
        ]);
        $include = 'company.clients.contacts';

        // The endpoint and the shared eager-load helper must enforce the same rules.
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->{$token_property},
        ])->getJson('/api/v1/products?include='.$include)
            ->assertOk()->assertJsonCount($can_view_all ? 3 : 2, 'data.0.company.clients');

        $query = Product::query()->whereKey($product->id);
        $this->applyDottedCompanyIncludes($query, [$include]);
        $connection = DB::connection($this->fixture_connection);
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $loaded_product = $query->firstOrFail();
            $queries_before = $connection->getQueryLog();
            $data = $this->transformIncludedRecord($loaded_product, $include, new ProductTransformer('array'));
            $queries_after = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }

        $expected_ids = $can_view_all
            ? $this->clients->pluck('hashed_id')->all()
            : [$this->client->hashed_id, $assigned_client->hashed_id];
        $this->assertEqualsCanonicalizing($expected_ids, $loaded_product->company->clients->pluck('hashed_id')->all());
        $this->assertEqualsCanonicalizing($expected_ids, array_column($data['company']['clients'], 'id'));
        $client_queries = fn (array $queries) => array_values(array_filter(
            $queries,
            fn (array $query) => str_contains($query['query'], 'from `clients`')
        ));
        $this->assertCount(1, $client_queries($queries_before));
        $this->assertSame($client_queries($queries_before), $client_queries($queries_after));
        if (! $can_view_all) {
            $this->assertStringContainsString('`clients`.`user_id` = ?', $client_queries($queries_before)[0]['query']);
            $this->assertStringContainsString('or `clients`.`assigned_user_id` = ?', $client_queries($queries_before)[0]['query']);
        }
    }

    public function testDottedIncludesPreservesExistingDateConstraints(): void
    {
        $this->createFixture('');
        $this->client->forceFill(['user_id' => $this->restricted_user->id, 'updated_at' => now()->subDays(2)])->save();
        $assigned_client = $this->clients[1];
        $assigned_client->update(['assigned_user_id' => $this->restricted_user->id]);
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id)->assertOk();

        $query = CompanyUser::query()->where('company_id', $this->company->id)->where('user_id', $this->restricted_user->id)
            ->with(['company.clients' => fn ($query) => $query->where('clients.updated_at', '>=', now()->subDay())]);
        $this->applyDottedCompanyIncludes($query, ['company.clients.contacts']);

        $this->assertSame([$assigned_client->id], $query->firstOrFail()->company->clients->modelKeys());
    }

    public function testDottedIncludesPreserveConstraintsThroughListResponse(): void
    {
        $this->createFixture('');
        $this->client->forceFill(['user_id' => $this->restricted_user->id])->save();
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->restricted_user->id,
        ]);
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/products?include=company.clients.contacts')->assertOk();

        $query = Product::query()->whereKey($product->id);
        $this->applyDottedCompanyIncludes($query, ['company.clients.contacts']);
        $this->assertSame([$this->client->id], (clone $query)->firstOrFail()->company->clients->modelKeys());

        // listResponse must compose its constraints with those already on the builder.
        $controller = app(ProductController::class);
        $response = (new ReflectionMethod($controller, 'listResponse'))->invoke($controller, $query);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$this->client->hashed_id], array_column($data['data'][0]['company']['clients'], 'id'));
    }

    public function testDottedIncludesResolvesCompanyUserAlias(): void
    {
        $this->createFixture('');
        $this->client->update(['assigned_user_id' => $this->restricted_user->id]);
        $include = 'company_user.company.clients';
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include='.$include)
            ->assertOk()->assertJsonCount(1, 'data.company_user.company.clients');

        try {
            Company::query()->whereKey($this->company->id)->with($include)->firstOrFail();
            $this->fail('The transformer alias should not be an Eloquent relationship.');
        } catch (RelationNotFoundException $exception) {
            $this->assertStringContainsString('company_user', $exception->getMessage());
        }

        $query = Company::query()->whereKey($this->company->id)->with([
            'company_users' => fn ($query) => $query->where('user_id', $this->restricted_user->id),
        ]);
        $this->applyDottedCompanyIncludes($query, [$include]);
        $company = $query->firstOrFail();
        $this->assertCount(1, $company->company_users);
        $data = $this->transformIncludedRecord($company, $include, new CompanyTransformer('array'));
        $this->assertSame([$this->client->hashed_id], array_column($data['company_user']['company']['clients'], 'id'));
    }

    public function testDottedIncludesDistinguishesCompanyContactsFromClientDefaultContacts(): void
    {
        $this->createFixture('');
        $this->client->forceFill(['user_id' => $this->restricted_user->id])->save();
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id)->assertOk();

        $query = Company::query()->whereKey($this->company->id);
        $this->applyDottedCompanyIncludes($query, ['clients', 'contacts']);
        $company = $query->firstOrFail();
        $this->assertTrue($company->clients->first()->relationLoaded('contacts'));
        $data = $this->transformIncludedRecord($company, 'clients,contacts', new CompanyTransformer('array'));

        $this->assertSame([], $data['contacts']);
        // Client.contacts is loaded by the model and included by default, without an explicit dotted path.
        $this->assertSame($this->contact->hashed_id, $data['clients'][0]['contacts'][0]['id']);
    }

    public function testDottedIncludesStillNeedsTargetCompanyPermissionContext(): void
    {
        $this->createFixture('');
        $this->second_company = Company::factory()->create(['account_id' => $this->account->id]);
        $membership = CompanyUserFactory::create($this->admin_user->id, $this->second_company->id, $this->account->id);
        $membership->is_admin = false;
        $membership->is_owner = false;
        $membership->is_locked = false;
        $membership->permissions = '';
        $membership->save();
        $foreign_client = Client::factory()->create([
            'company_id' => $this->second_company->id,
            'user_id' => $this->restricted_user->id,
        ]);
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->admin_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id)->assertOk();

        $query = CompanyUser::query()->where('user_id', $this->admin_user->id)->whereIn('company_id', [$this->company->id, $this->second_company->id]);
        $this->applyDottedCompanyIncludes($query, ['company.clients']);
        $memberships = $query->get()->keyBy('company_id');

        $this->assertFalse((bool) $memberships[$this->second_company->id]->is_admin);
        // Characterisation: path filtering alone retains the active-company admin bypass.
        $this->assertSame([$foreign_client->id], $memberships[$this->second_company->id]->company->clients->modelKeys());
    }

    public function testDottedIncludesDoNotQueryUnsupportedNestedRelations(): void
    {
        $this->createFixture('');
        $this->client->forceFill(['user_id' => $this->restricted_user->id])->save();
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id)->assertOk();

        $query = Company::query()->whereKey($this->company->id);
        $this->applyDottedCompanyIncludes($query, ['clients.invoices']);
        $company = $query->firstOrFail();
        $this->assertFalse($company->clients->first()->relationLoaded('invoices'));
        $data = $this->transformIncludedRecord($company, 'clients.invoices', new CompanyTransformer('array'));
        $this->assertArrayNotHasKey('invoices', $data['clients'][0]);
    }

    public function testDottedIncludesMustConstrainTheRelationActuallyReadByTheTransformer(): void
    {
        $this->createFixture('');
        $admin_token = $this->company->tokens()->where('user_id', $this->admin_user->id)->firstOrFail()->replicate();
        $admin_token->token = Str::random(64);
        $admin_token->is_system = false;
        $admin_token->save();
        $owned_token = $admin_token->replicate();
        $owned_token->token = Str::random(64);
        $owned_token->user_id = $this->restricted_user->id;
        $owned_token->save();

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id)->assertOk();

        // An illustrative constraint: the token policy itself is outside this prototype.
        $constraint = fn ($query) => $query->where('company_tokens.user_id', $this->restricted_user->id);
        $company = Company::query()->whereKey($this->company->id)->with(['tokens' => $constraint])->firstOrFail();
        $this->assertFalse($company->tokens->contains('id', $admin_token->id));
        $this->assertFalse($company->relationLoaded('tokens_hashed'));
        $data = $this->transformIncludedRecord($company, 'tokens', new CompanyTransformer('array'));
        $this->assertCount(2, $data['tokens']);

        $company = Company::query()->whereKey($this->company->id)->with(['tokens_hashed' => $constraint])->firstOrFail();
        $data = $this->transformIncludedRecord($company, 'tokens', new CompanyTransformer('array'));
        $this->assertSame([$owned_token->hashed_id], array_column($data['tokens'], 'id'));
    }

    public function testDottedIncludesMustIncludeForcedPaths(): void
    {
        $this->createFixture('');
        $this->client->forceFill(['user_id' => $this->restricted_user->id])->save();
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->restricted_user->id,
        ]);
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/products')->assertOk();

        $controller = app(ProductController::class);
        $controller->forced_includes = ['company.clients'];
        $query = Product::query()->whereKey($product->id);
        $this->applyDottedCompanyIncludes($query, []);
        $response = (new ReflectionMethod($controller, 'listResponse'))->invoke($controller, $query);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$this->client->hashed_id], array_column($data['data'][0]['company']['clients'], 'id'));

        $query = Product::query()->whereKey($product->id);
        $this->applyDottedCompanyIncludes($query, $controller->forced_includes);
        $response = (new ReflectionMethod($controller, 'listResponse'))->invoke($controller, $query);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$this->client->hashed_id], array_column($data['data'][0]['company']['clients'], 'id'));
    }

    public function testDottedIncludesReplaceUnrestrictedNestedPreloadsOnItemResponse(): void
    {
        $this->createFixture('');
        $this->client->forceFill(['user_id' => $this->restricted_user->id])->save();
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/companies/'.$this->company->hashed_id.'?include=company_user.company.clients')->assertOk();

        $company = $this->company->fresh()->load('company_users.company.clients');
        $this->assertCount(3, $company->company_users->first()->company->clients);
        $controller = app(CompanyController::class);
        $response = (new ReflectionMethod($controller, 'itemResponse'))->invoke($controller, $company);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$this->client->hashed_id], array_column($data['data']['company_user']['company']['clients'], 'id'));
    }

    public static function groupSettingsIncludeRoutes(): array
    {
        return [
            'client list' => ['/api/v1/clients?include=group_settings', 'data.*.group_settings'],
            'project client reference' => ['/api/v1/projects?include=client.group_settings', 'data.*.client.group_settings'],
            'company client collection' => ['/api/v1/companies/{company}?include=clients.group_settings', 'data.clients.*.group_settings'],
            'company groups' => ['/api/v1/companies/{company}?include=groups', 'data.groups'],
            'group settings list' => ['/api/v1/group_settings', 'data'],
        ];
    }

    #[DataProvider('groupSettingsIncludeRoutes')]
    public function testGroupSettingsIncludesResolveDefaultDocumentsWithoutAParentId(string $endpoint, string $group_path): void
    {
        $this->createFixture('');
        $group = GroupSettingFactory::create($this->company->id, $this->admin_user->id);
        $group->name = 'Include regression group';
        $group->save();
        $other_group = GroupSettingFactory::create($this->company->id, $this->admin_user->id);
        $other_group->name = 'Other group';
        $other_group->save();
        $this->client->group_settings_id = $group->id;
        $this->client->save();

        $document = $group->documents()->save(Document::factory()->make([
            'company_id' => $this->company->id,
            'user_id' => $this->admin_user->id,
            'assigned_user_id' => $this->admin_user->id,
        ]));
        $other_document = $other_group->documents()->save(Document::factory()->make([
            'company_id' => $this->company->id,
            'user_id' => $this->admin_user->id,
            'assigned_user_id' => $this->admin_user->id,
        ]));
        $this->beforeApplicationDestroyed(function () use ($group, $other_group, $document, $other_document) {
            foreach ([$group, $other_group, $document, $other_document] as $record) {
                $this->assertFalse(DB::connection($this->fixture_connection)
                    ->table($record->getTable())->where('id', $record->id)->exists());
            }
        });

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->admin_token,
        ])->getJson(str_replace('{company}', $this->company->hashed_id, $endpoint))->assertOk();

        $group_data = collect(data_get($response->json(), $group_path))->filter()->firstWhere('id', $group->hashed_id);
        $this->assertNotNull($group_data);
        $this->assertSame('Include regression group', $group_data['name']);
        $this->assertSame([$document->hashed_id], array_column($group_data['documents'], 'id'));
        $this->assertSame([$document->id], $group->documents()->pluck('id')->all());
    }

    public static function gatewayLogIncludeRoutes(): array
    {
        return [
            'gateway item' => ['/api/v1/company_gateways/{gateway}?include=system_logs', 'data.system_logs'],
            'gateway list' => ['/api/v1/company_gateways?include=system_logs', 'data.0.system_logs'],
            'company gateway include' => ['/api/v1/companies/{company}?include=company_gateways.system_logs', 'data.company_gateways.0.system_logs'],
        ];
    }

    #[DataProvider('gatewayLogIncludeRoutes')]
    public function testComputedGatewayLogsPreserveCompanyTypeAndLimit(string $endpoint, string $log_path): void
    {
        $this->createFixture('');
        $gateway = CompanyGatewayFactory::create($this->company->id, $this->admin_user->id);
        $gateway->gateway_key = '3b6621f970ab18887c4f6dca78d3f8bb';
        $gateway->save();
        $type_id = $gateway->gateway_consts[$gateway->gateway_key];
        $expected_ids = [];
        for ($index = 0; $index < 52; $index++) {
            $log = SystemLog::create([
                'company_id' => $this->company->id,
                'user_id' => $this->admin_user->id,
                'type_id' => $type_id,
                'log' => ['message' => 'Matching gateway fixture'],
            ]);
            array_unshift($expected_ids, $log->encodePrimaryKey($log->id));
        }
        SystemLog::create([
            'company_id' => $this->company->id,
            'type_id' => $type_id + 1,
            'log' => ['message' => 'Other gateway type'],
        ]);
        $this->second_company = Company::factory()->create(['account_id' => $this->account->id]);
        SystemLog::create([
            'company_id' => $this->second_company->id,
            'type_id' => $type_id,
            'log' => ['message' => 'Other company'],
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->admin_token,
        ])->getJson(str_replace(['{gateway}', '{company}'], [$gateway->hashed_id, $this->company->hashed_id], $endpoint))->assertOk();

        $this->assertSame(array_slice($expected_ids, 0, 50), array_column($response->json($log_path), 'id'));
    }

    #[DataProvider('companyResponseProvider')]
    public function testCompanyCatalogIncludesAreRestrictedToItemResponses(bool $is_item, string $data_path): void
    {
        $this->createFixture('');
        $this->second_company = Company::factory()->create(['account_id' => $this->account->id]);
        $expected = [];
        foreach (['designs' => Design::class, 'payment_terms' => PaymentTerm::class] as $include => $model_class) {
            foreach (['shared' => null, 'owned' => $this->company->id, 'foreign' => $this->second_company->id] as $kind => $company_id) {
                $record = new $model_class();
                $record->company_id = $company_id;
                $record->user_id = $this->admin_user->id;
                $record->name = 'Include catalog '.$kind;
                if ($record instanceof Design) {
                    $record->design = new \stdClass();
                    $record->is_active = true;
                } else {
                    $record->num_days = 37;
                }
                $record->save();
                $expected[$include][$kind] = $record->hashed_id;
                $this->beforeApplicationDestroyed(function () use ($record) {
                    $this->assertFalse(DB::connection($this->fixture_connection)
                        ->table($record->getTable())->where('id', $record->id)->exists());
                });
            }
        }

        $endpoint = '/api/v1/companies'.($is_item ? '/'.$this->company->hashed_id : '');
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->admin_token,
        ])->getJson($endpoint.'?include=designs,payment_terms')->assertOk();
        if (! $is_item) {
            $this->assertCompanyListHasNoIncludes($response);

            return;
        }

        $data = $is_item ? $response->json('data') : collect($response->json('data'))->firstWhere('id', $this->company->hashed_id);
        foreach ($expected as $include => $ids) {
            $returned_ids = array_column($data[$include], 'id');
            $this->assertContains($ids['shared'], $returned_ids);
            $this->assertContains($ids['owned'], $returned_ids);
            $this->assertNotContains($ids['foreign'], $returned_ids);
        }
    }

    public function testActivityHistoryAndQuoteUseTheirDeclaredTransformers(): void
    {
        $this->createFixture('');
        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin_user->id,
            'client_id' => $this->client->id,
        ]);
        $activity = new Activity();
        $activity->company_id = $this->company->id;
        $activity->user_id = $this->admin_user->id;
        $activity->quote_id = $quote->id;
        $activity->activity_type_id = 1;
        $activity->ip = '127.0.0.1';
        $activity->notes = 'Include history fixture';
        $activity->save();
        $backup = new Backup();
        $backup->activity_id = $activity->id;
        $backup->amount = 12.5;
        $backup->save();
        $this->beforeApplicationDestroyed(function () use ($activity, $backup, $quote) {
            foreach ([$activity, $backup, $quote] as $record) {
                $this->assertFalse(DB::connection($this->fixture_connection)
                    ->table($record->getTable())->where('id', $record->id)->exists());
            }
        });

        $include = 'history.activity,quote';
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->admin_token,
        ])->getJson('/api/v1/activities?include='.$include)->assertOk();
        $data = collect($response->json('data'))->firstWhere('id', $activity->hashed_id);
        $this->assertSame($backup->hashed_id, $data['history']['id']);
        $this->assertSame($activity->hashed_id, $data['history']['activity']['id']);
        $this->assertSame($quote->hashed_id, $data['quote']['id']);

        $query = Activity::query()->whereKey($activity->id);
        $this->applyDottedCompanyIncludes($query, explode(',', $include));
        $loaded_activity = $query->firstOrFail();
        $this->assertTrue($loaded_activity->relationLoaded('backup'));
        $this->assertTrue($loaded_activity->backup->relationLoaded('activity'));
        $this->assertInstanceOf(Quote::class, $loaded_activity->quote);
    }

    public static function paymentTypeIncludeRoutes(): array
    {
        return [
            'payment item' => ['/api/v1/payments/{payment}?include=type', 'data.type'],
            'payment list' => ['/api/v1/payments?include=type', 'data.0.type'],
            'company payments include' => ['/api/v1/companies/{company}?include=payments.type', 'data.payments.0.type'],
        ];
    }

    #[DataProvider('paymentTypeIncludeRoutes')]
    public function testComputedPaymentTypePreservesTranslatedName(string $endpoint, string $type_path): void
    {
        $this->createFixture('');
        $this->payment->type_id = \App\Models\PaymentType::VISA;
        $this->payment->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->admin_token,
        ])->getJson(str_replace(['{payment}', '{company}'], [$this->payment->hashed_id, $this->company->hashed_id], $endpoint))->assertOk();

        $this->assertSame(['name' => $this->payment->translatedType()], $response->json($type_path));
    }

    public function testItemResponsePreservesUnrelatedPreloadedReferences(): void
    {
        $this->createFixture('');
        $this->client->forceFill(['user_id' => $this->restricted_user->id])->save();
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/clients/'.$this->client->hashed_id)->assertOk();

        $client = $this->client->fresh()->setRelation('contacts', new Collection());
        $controller = app(ClientController::class);
        $response = (new ReflectionMethod($controller, 'itemResponse'))->invoke($controller, $client);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([], $data['data']['contacts']);
    }

    public static function crossCompanyAdminMemberships(): array
    {
        return [
            'admin A, restricted B' => [true, false],
            'restricted A, admin B' => [false, true],
        ];
    }

    #[DataProvider('crossCompanyAdminMemberships')]
    public function testProposedMembershipScopeUsesEachCompanyAdminFlag(bool $admin_a, bool $admin_b): void
    {
        $this->createFixture('');
        $this->company->company_users()->where('user_id', $this->admin_user->id)->update(['is_admin' => $admin_a]);
        $this->second_company = Company::factory()->create(['account_id' => $this->account->id]);
        $membership = CompanyUserFactory::create($this->admin_user->id, $this->second_company->id, $this->account->id);
        $membership->is_admin = $admin_b;
        $membership->is_owner = false;
        $membership->is_locked = false;
        $membership->permissions = '';
        $membership->save();

        $expected = [];
        $company_a_ids = [];
        foreach ([[$this->company, $admin_a], [$this->second_company, $admin_b]] as [$company, $is_admin]) {
            foreach ([$this->admin_user, $this->restricted_user] as $creator) {
                $gateway = CompanyGatewayFactory::create($company->id, $creator->id);
                $gateway->gateway_key = '3b6621f970ab18887c4f6dca78d3f8bb';
                $gateway->save();
                if ($is_admin || $creator->id === $this->admin_user->id) {
                    $expected[] = $gateway->id;
                    if ($company->id === $this->company->id) {
                        $company_a_ids[] = $gateway->id;
                    }
                }
                $this->beforeApplicationDestroyed(function () use ($gateway) {
                    $this->assertFalse(DB::connection($this->fixture_connection)
                        ->table('company_gateways')->where('id', $gateway->id)->exists());
                });
            }
        }

        // Proposed option only; the production permission helper still uses the active membership.
        $query = CompanyGateway::query()->whereIn('company_id', [$this->company->id, $this->second_company->id]);
        $owner_column = $query->getModel()->qualifyColumn('user_id');
        $query->whereHas('company.company_users', function ($query) use ($owner_column) {
            $query->where('company_user.user_id', $this->admin_user->id)
                ->whereNull('company_user.deleted_at')
                ->where(fn ($query) => $query->where('company_user.is_locked', false)->orWhereNull('company_user.is_locked'))
                ->where(fn ($query) => $query->where('company_user.is_admin', true)
                    ->orWhereColumn($owner_column, 'company_user.user_id'));
        });

        $this->assertEqualsCanonicalizing($expected, (clone $query)->pluck('id')->all());
        $membership_query = CompanyUser::query()->where('company_id', $this->second_company->id)->where('user_id', $this->admin_user->id);
        (clone $membership_query)->update(['is_locked' => true]);
        $this->assertEqualsCanonicalizing($company_a_ids, (clone $query)->pluck('id')->all());
        (clone $membership_query)->update(['is_locked' => false, 'deleted_at' => now()]);
        $this->assertEqualsCanonicalizing($company_a_ids, (clone $query)->pluck('id')->all());
    }

    public static function parentProfileIncludes(): array
    {
        $cases = [];
        foreach (['task', 'project'] as $entity) {
            foreach (['user', 'assigned_user'] as $include) {
                foreach ([false, true] as $is_item) {
                    $cases[$entity.'.'.$include.($is_item ? ' item' : ' list')] = [$entity, $include, $is_item];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('parentProfileIncludes')]
    public function testAuthorizedParentsReturnProfilesWithoutMembershipTraversal(string $entity, string $include, bool $is_item): void
    {
        $this->createFixture('');
        $record = $this->{$entity};
        $record->forceFill([
            'user_id' => $include === 'user' ? $this->admin_user->id : $this->restricted_user->id,
            'assigned_user_id' => $include === 'user' ? $this->restricted_user->id : $this->admin_user->id,
        ])->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ])->getJson('/api/v1/'.$entity.'s'.($is_item ? '/'.$record->hashed_id : '')
            .'?include='.$include.'.company_user.company.clients')->assertOk();

        $data = $response->json($is_item ? 'data' : 'data.0');
        $this->assertSame($record->hashed_id, $data['id']);
        $profile = $data[$include];
        $this->assertSame($this->admin_user->hashed_id, $profile['id']);
        $this->assertSame($this->admin_user->email, $profile['email']);
        $this->assertSame(0, $profile['last_login']);
        $this->assertFalse($profile['has_password']);
        $this->assertFalse($profile['google_2fa_secret']);
        $this->assertSame([], $profile['settings']);
        foreach (['company_user', 'company_users', 'companies', 'account', 'token'] as $relation) {
            $this->assertArrayNotHasKey($relation, $profile);
        }

        $query = $record->newQuery()->whereKey($record->id);
        $this->applyDottedCompanyIncludes($query, [$include.'.company_user.company.clients']);
        $loaded_record = $query->firstOrFail();
        $this->assertTrue($loaded_record->relationLoaded($include));
        $this->assertFalse($loaded_record->{$include}->relationLoaded('company_users'));
    }

    public static function relatedReferenceRoutes(): array
    {
        return [
            'task project' => ['tasks', 'project'],
            'task client' => ['tasks', 'client'],
            'task invoice' => ['tasks', 'invoice'],
            'expense project' => ['expenses', 'project'],
            'expense client' => ['expenses', 'client'],
            'expense vendor' => ['expenses', 'vendor'],
            'expense invoice' => ['expenses', 'invoice'],
            'recurring expense client' => ['recurring_expenses', 'client'],
            'recurring expense vendor' => ['recurring_expenses', 'vendor'],
            'project client' => ['projects', 'client'],
            'invoice client' => ['invoices', 'client'],
            'payment client' => ['payments', 'client'],
        ];
    }

    public static function restrictedRelatedReferenceCases(): array
    {
        $cases = [];
        foreach (self::relatedReferenceRoutes() as $label => [$route, $relation]) {
            foreach (['owned', 'assigned', 'view'] as $access) {
                foreach (['item' => true, 'list' => false] as $response_type => $is_item) {
                    $cases[$label.' '.$access.' '.$response_type] = [$route, $relation, $access, $is_item];
                }
            }
        }

        return $cases;
    }

    public static function authorizedRelatedReferenceCases(): array
    {
        $cases = [];
        foreach (self::relatedReferenceRoutes() as $label => [$route, $relation]) {
            foreach (['view', 'owned', 'assigned', 'admin'] as $access) {
                $cases[$label.' '.$access] = [$route, $relation, $access];
            }
        }

        return $cases;
    }

    /**
     * Limited reference transformers retain identifiers and display labels.
     * Sensitive fields may be omitted, null, empty strings or numeric zero;
     * sensitive child includes may also be empty collections.
     * Rates remain undecided: both UIs use project task rates.
     */
    #[DataProvider('restrictedRelatedReferenceCases')]
    public function testRelatedReferenceRetainsLabelButRedactsSensitiveFields(string $route, string $relation, string $access, bool $is_item): void
    {
        [$parent, $related] = $this->createRelatedReferenceFixture(
            $route, $relation, $access, $access === 'view' ? json_encode(['view_'.Str::singular($route)]) : ''
        );
        $this->getJson('/api/v1/'.Str::plural($relation).'/'.$related->hashed_id)->assertStatus(401)->assertJson(['message' => 'This action is unauthorized.']);

        $data = $this->relatedReferenceResponse($route, $parent, $is_item, $relation);
        $this->assertRelatedReferenceLabel($data[$relation], $related);
        $this->assertRelatedReferenceIsRedacted($data[$relation], $related);
    }

    #[DataProvider('authorizedRelatedReferenceCases')]
    public function testRelatedReferencePreservesIndependentAccess(string $route, string $relation, string $access): void
    {
        [$parent, $related] = $this->createRelatedReferenceFixture(
            $route, $relation, 'assigned', $access === 'view' ? json_encode(['view_'.$relation]) : ''
        );
        if ($access === 'owned' || $access === 'assigned') {
            $related->forceFill([$access === 'owned' ? 'user_id' : 'assigned_user_id' => $this->restricted_user->id])->save();
        } elseif ($access === 'admin') {
            $this->withHeaders(['X-API-TOKEN' => $this->admin_token]);
        }

        $direct = $this->getJson('/api/v1/'.Str::plural($relation).'/'.$related->hashed_id)->assertOk()->json('data');
        $data = $this->relatedReferenceResponse($route, $parent, true, $relation);
        $this->assertRelatedReferenceLabel($data[$relation], $related);
        $this->assertSame($related->private_notes, $data[$relation]['private_notes']);

        // View permissions may still redact client financials. Includes must not
        // reveal fields that the direct endpoint deliberately hides.
        foreach ($this->relatedReferenceSensitiveFields($related) as $field) {
            $this->assertSame($direct[$field] ?? null, $data[$relation][$field] ?? null, $relation.'.'.$field);
        }
    }

    #[DataProvider('relatedReferenceRoutes')]
    public function testRelatedReferenceCreatePermissionDoesNotGrantFullAccess(string $route, string $relation): void
    {
        [$parent, $related] = $this->createRelatedReferenceFixture($route, $relation, 'assigned', json_encode(['create_'.$relation]));
        $this->getJson('/api/v1/'.Str::plural($relation).'/'.$related->hashed_id)
            ->assertStatus(401)->assertJson(['message' => 'This action is unauthorized.']);

        $data = $this->relatedReferenceResponse($route, $parent, true, $relation);
        $this->assertRelatedReferenceLabel($data[$relation], $related);
        $this->assertRelatedReferenceIsRedacted($data[$relation], $related);
    }

    #[DataProvider('relatedReferenceRoutes')]
    public function testRelatedReferenceDoesNotGrantParentAccess(string $route, string $relation): void
    {
        [$parent] = $this->createRelatedReferenceFixture($route, $relation, 'assigned', json_encode(['view_'.$relation]));
        $parent->update(['user_id' => $this->admin_user->id, 'assigned_user_id' => null]);

        $this->getJson('/api/v1/'.$route.'/'.$parent->hashed_id.'?include='.$relation)->assertStatus(401)->assertJson(['message' => 'This action is unauthorized.']);
        $response = $this->getJson('/api/v1/'.$route.'?include='.$relation)->assertOk();
        $this->assertNotContains($parent->hashed_id, array_column($response->json('data'), 'id'));
    }

    public static function optionalRelatedReferenceRoutes(): array
    {
        return array_filter(self::relatedReferenceRoutes(), fn ($case) => ! in_array($case[0], ['invoices', 'payments'], true));
    }

    public static function unnamedClientReferenceRoutes(): array
    {
        return array_filter(self::relatedReferenceRoutes(), fn ($case) => $case[1] === 'client');
    }

    #[DataProvider('unnamedClientReferenceRoutes')]
    public function testRelatedReferenceUnnamedClientRetainsDisplayLabel(string $route, string $relation): void
    {
        [$parent, $client] = $this->createRelatedReferenceFixture($route, $relation);
        $client->update(['name' => '']);
        $this->contact->update([
            'first_name' => 'Display',
            'last_name' => 'Contact',
            'email' => 'reference-label@example.test',
        ]);

        foreach ([true, false] as $is_item) {
            $data = $this->relatedReferenceResponse($route, $parent, $is_item, 'client');
            $this->assertSame($client->hashed_id, $data['client']['id']);
            $this->assertSame('', $data['client']['name']);
            $this->assertSame('Display Contact', $data['client']['display_name']);
        }
    }

    #[DataProvider('optionalRelatedReferenceRoutes')]
    public function testRelatedReferenceMayBeMissing(string $route, string $relation): void
    {
        [$parent] = $this->createRelatedReferenceFixture($route, $relation);
        $parent->update([$relation.'_id' => null]);

        foreach ([true, false] as $is_item) {
            $data = $this->relatedReferenceResponse($route, $parent, $is_item, $relation);
            $this->assertSame('', $data[$relation.'_id']);
            $this->assertNull($data[$relation] ?? null);
        }
    }

    public static function ownedAndAssignedReferenceResponses(): array
    {
        return [
            'owned item' => ['owned', true],
            'owned list' => ['owned', false],
            'assigned item' => ['assigned', true],
            'assigned list' => ['assigned', false],
        ];
    }

    #[DataProvider('ownedAndAssignedReferenceResponses')]
    public function testRelatedReferenceDefaultTaskProjectIsLimited(string $access, bool $is_item): void
    {
        [$task, $project] = $this->createRelatedReferenceFixture('tasks', 'project', $access);
        $data = $this->relatedReferenceResponse('tasks', $task, $is_item);
        $this->assertRelatedReferenceLabel($data['project'], $project);
        $this->assertRelatedReferenceIsRedacted($data['project'], $project);
    }

    #[DataProvider('relatedReferenceRoutes')]
    public function testRelatedReferenceDoesNotExposeDefaultDocuments(string $route, string $relation): void
    {
        [$parent, $related] = $this->createRelatedReferenceFixture($route, $relation);
        $document = $related->documents()->save(Document::factory()->make([
            'company_id' => $this->company->id,
            'user_id' => $this->admin_user->id,
            'assigned_user_id' => $this->admin_user->id,
            'name' => 'Private reference document.pdf',
            'is_public' => false,
        ]));
        $this->assertRelatedReferenceFixtureCleanup([$document]);

        $data = $this->relatedReferenceResponse($route, $parent, true, $relation);
        $this->assertRelatedReferenceLabel($data[$relation], $related);
        $this->assertEmpty($data[$relation]['documents'] ?? [], 'A reference must not grant access to its default documents.');
    }

    public static function nestedProjectReferenceCases(): array
    {
        $cases = [];
        foreach (['tasks', 'expenses'] as $route) {
            foreach (['tasks' => Task::class, 'expenses' => Expense::class, 'invoices' => Invoice::class] as $include => $model_class) {
                foreach ([true, false] as $is_item) {
                    $cases[$route.' project.'.$include.($is_item ? ' item' : ' list')] = [$route, $include, $model_class, $is_item];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('nestedProjectReferenceCases')]
    public function testRelatedReferenceCannotExpandProjectCollections(string $route, string $include, string $model_class, bool $is_item): void
    {
        [$parent, $project] = $this->createRelatedReferenceFixture($route, 'project');
        $sibling = $model_class::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin_user->id,
            'assigned_user_id' => null,
            'client_id' => $this->client->id,
            'project_id' => $project->id,
        ]);
        $this->assertRelatedReferenceFixtureCleanup([$sibling]);
        $this->getJson('/api/v1/'.$include.'/'.$sibling->hashed_id)->assertStatus(401)->assertJson(['message' => 'This action is unauthorized.']);

        $data = $this->relatedReferenceResponse($route, $parent, $is_item, 'project.'.$include);
        $this->assertRelatedReferenceLabel($data['project'], $project);
        $this->assertEmpty($data['project'][$include] ?? [], 'A project reference must not expose its '.$include.' collection.');
    }

    public static function taskAndExpenseReferenceResponses(): array
    {
        return [
            'task item' => ['tasks', true],
            'task list' => ['tasks', false],
            'expense item' => ['expenses', true],
            'expense list' => ['expenses', false],
        ];
    }

    #[DataProvider('taskAndExpenseReferenceResponses')]
    public function testRelatedReferenceInvoiceAllocationsRequireIndependentAccess(string $route, bool $is_item): void
    {
        [$parent, $invoice] = $this->createRelatedReferenceFixture($route, 'invoice');
        $data = $this->relatedReferenceResponse($route, $parent, $is_item, 'invoice.paymentables');
        $this->assertRelatedReferenceLabel($data['invoice'], $invoice);
        $this->assertEmpty($data['invoice']['paymentables'] ?? [], 'An invoice reference must not disclose payment allocations.');
    }

    #[DataProvider('taskAndExpenseReferenceResponses')]
    public function testRelatedReferenceAuthorizedInvoiceRetainsAllocations(string $route, bool $is_item): void
    {
        [$parent, $invoice] = $this->createRelatedReferenceFixture($route, 'invoice', 'assigned', '["view_invoice"]');
        $data = $this->relatedReferenceResponse($route, $parent, $is_item, 'invoice.paymentables');
        $this->assertRelatedReferenceLabel($data['invoice'], $invoice);
        $allocation = collect($data['invoice']['paymentables'])->firstWhere('payment_id', $this->payment->hashed_id);
        $this->assertNotNull($allocation);
        $this->assertSame($invoice->hashed_id, $allocation['invoice_id']);
        $this->assertEquals(50, $allocation['amount']);
    }

    #[DataProvider('ownedAndAssignedReferenceResponses')]
    public function testRelatedReferenceOwnedPaymentRetainsAllocationIdentifiers(string $access, bool $is_item): void
    {
        [$payment] = $this->createRelatedReferenceFixture('payments', 'client', $access);
        $this->getJson('/api/v1/invoices/'.$this->invoice->hashed_id)
            ->assertStatus(401)->assertJson(['message' => 'This action is unauthorized.']);

        $data = $this->relatedReferenceResponse('payments', $payment, $is_item);
        $allocation = collect($data['paymentables'])->firstWhere('invoice_id', $this->invoice->hashed_id);
        $this->assertNotNull($allocation);
        $this->assertSame($payment->hashed_id, $allocation['payment_id']);
        $this->assertEquals(50, $allocation['amount']);
        $this->assertEmpty($allocation['private_notes'] ?? null);
        $this->assertEmpty($data['invoices'] ?? null);
    }

    #[DataProvider('taskAndExpenseReferenceResponses')]
    public function testRelatedReferenceJsonSerializerPreservesLabelAndRedaction(string $route, bool $is_item): void
    {
        [$parent, $project] = $this->createRelatedReferenceFixture($route, 'project');
        $response = $this->getJson('/api/v1/'.$route.($is_item ? '/'.$parent->hashed_id : '').'?include=project&serializer=json')->assertOk();
        $parent_data = $is_item
            ? $response->json('data.data')
            : collect($response->json('data.data'))->firstWhere('id', $parent->hashed_id);
        $this->assertSame($project->hashed_id, data_get($parent_data, 'relationships.project.data.id'));
        $reference = collect($response->json('data.included'))->firstWhere('id', $project->hashed_id);
        $this->assertNotNull($reference);
        $this->assertSame($project->name, $reference['attributes']['name']);
        $this->assertRelatedReferenceIsRedacted($reference['attributes'], $project);
    }

    private function createRelatedReferenceFixture(string $route, string $relation, string $access = 'assigned', string $permissions = ''): array
    {
        $this->createFixture($permissions);
        $this->withoutMiddleware(ThrottleRequests::class);
        $attributes = ['company_id' => $this->company->id, 'user_id' => $this->admin_user->id, 'assigned_user_id' => null];
        $related = $relation === 'vendor' ? Vendor::factory()->create($attributes) : $this->{$relation};
        $related->forceFill(array_merge($attributes, [
            $relation === 'invoice' ? 'number' : 'name' => 'Reference '.ucfirst($relation),
            'private_notes' => 'Private '.$relation.' reference notes',
        ]));
        if ($relation === 'project') {
            $related->budgeted_hours = 80;
            $related->budgeted_amount = 9876;
            $related->task_rate = 125;
        }
        $related->save();

        $parent = match ($route) {
            'tasks' => $this->task,
            'projects' => $this->project,
            'invoices' => $this->invoice,
            'payments' => $this->payment,
            'expenses' => Expense::factory()->create($attributes),
            'recurring_expenses' => RecurringExpense::factory()->create($attributes),
        };
        $parent->forceFill([
            $relation.'_id' => $related->id,
            'user_id' => $access === 'owned' ? $this->restricted_user->id : $this->admin_user->id,
            'assigned_user_id' => $access === 'assigned' ? $this->restricted_user->id : null,
        ])->save();
        $this->assertRelatedReferenceFixtureCleanup([$parent, $related]);
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->restricted_token,
        ]);

        return [$parent, $related];
    }

    private function relatedReferenceResponse(string $route, Model $parent, bool $is_item, string $include = ''): array
    {
        $response = $this->getJson('/api/v1/'.$route.($is_item ? '/'.$parent->hashed_id : '')
            .($include === '' ? '' : '?include='.$include))->assertOk();
        $data = $is_item ? $response->json('data') : collect($response->json('data'))->firstWhere('id', $parent->hashed_id);
        $this->assertNotNull($data, 'The authorized parent must remain in the response.');
        $this->assertSame($parent->hashed_id, $data['id']);

        return $data;
    }

    private function assertRelatedReferenceLabel(array $reference, Model $related): void
    {
        $label = $related instanceof Invoice ? 'number' : 'name';
        $this->assertSame($related->hashed_id, $reference['id']);
        $this->assertSame($related->{$label}, $reference[$label]);
        if ($related instanceof Client || $related instanceof Vendor) {
            $this->assertSame($related->present()->name(), $reference['display_name']);
        }
    }

    private function relatedReferenceSensitiveFields(Model $related): array
    {
        return match (true) {
            $related instanceof Project => ['private_notes', 'budgeted_hours', 'budgeted_amount'],
            $related instanceof Client => ['private_notes', 'balance', 'paid_to_date', 'credit_balance', 'payment_balance', 'client_hash'],
            $related instanceof Invoice => ['private_notes', 'amount', 'balance', 'paid_to_date'],
            default => ['private_notes'],
        };
    }

    private function assertRelatedReferenceIsRedacted(array $reference, Model $related): void
    {
        $exposed_fields = array_filter($this->relatedReferenceSensitiveFields($related),
            fn ($field) => ! in_array($reference[$field] ?? null, [null, '', 0, 0.0], true));
        $this->assertSame([], array_values($exposed_fields), 'Sensitive reference fields exposed: '.implode(', ', $exposed_fields));
    }

    private function assertRelatedReferenceFixtureCleanup(array $records): void
    {
        $this->beforeApplicationDestroyed(function () use ($records) {
            foreach ($records as $record) {
                $this->assertFalse(DB::connection($this->fixture_connection)
                    ->table($record->getTable())->where('id', $record->id)->exists());
            }
        });
    }
}
