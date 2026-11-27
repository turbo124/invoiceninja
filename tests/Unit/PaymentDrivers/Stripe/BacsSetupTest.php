<?php

namespace Tests\Unit\PaymentDrivers\Stripe;

use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\PaymentDrivers\Stripe\BACS;
use App\PaymentDrivers\StripePaymentDriver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\Customer;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class BacsSetupTest extends TestCase
{
    private ClientInterface $previousHttpClient;
    private mixed $previousDispatcher;
    private string $previousConnection;
    private StripePaymentDriver $driver;
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousHttpClient = ApiRequestor::httpClient();
        $this->previousDispatcher = Model::getEventDispatcher();
        Model::unsetEventDispatcher();
        config([
            'ninja.environment' => 'hosted',
            'database.connections.bacs_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $this->previousConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('bacs_test');
        $schema = DB::connection()->getSchemaBuilder();
        $schema->create('clients', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id');
            $table->softDeletes();
        });
        $schema->create('client_gateway_tokens', function (Blueprint $table) {
            $table->increments('id');
            foreach (['company_id', 'client_id', 'company_gateway_id', 'gateway_type_id'] as $column) {
                $table->integer($column);
            }
            $table->string('token');
            $table->string('gateway_customer_reference');
            $table->text('meta');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('clients')->insert(['id' => 22, 'company_id' => 11]);
        $company = new Company();
        $company->setRawAttributes(['id' => 11]);
        $client = new Client();
        $client->setRawAttributes(['id' => 22, 'company_id' => 11]);
        $client->setRelation('company', $company);
        $client->setRelation('gateway_tokens', new Collection());
        $gateway = new CompanyGateway();
        $gateway->setRawAttributes(['id' => 33, 'company_id' => 11]);

        // Keep the actual Stripe operations and token storage; isolate driver initialization/customer discovery.
        $this->driver = Mockery::mock(StripePaymentDriver::class)->makePartial();
        $this->driver->client = $client;
        $this->driver->company_gateway = $gateway;
        $this->driver->stripe_connect = true;
        $this->driver->shouldReceive('init')->andReturnSelf();
        $this->driver->shouldReceive('findOrCreateCustomer')->andReturn(Customer::constructFrom([
            'id' => 'cus_bacs', 'object' => 'customer', 'livemode' => false,
        ]));
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient($this->previousHttpClient);
        if ($this->previousDispatcher) {
            Model::setEventDispatcher($this->previousDispatcher);
        }
        DB::setDefaultConnection($this->previousConnection);
        DB::purge('bacs_test');
        parent::tearDown();
    }

    public static function versions(): iterable
    {
        yield '2018' => ['2018-05-21'];
        yield 'Basil' => ['2025-03-31.basil'];
    }

    #[DataProvider('versions')]
    public function testCreatesCustomerScopedSetupSession(string $version): void
    {
        $this->mockStripe($version);
        $view = (new BACS($this->driver))->authorizeView([]);

        $this->assertSame('portal.ninja2020.gateways.stripe.bacs.authorize', $view->name());
        $this->assertSame('cs_bacs', $view->getData()['session']->id);
        $this->assertSame([['post', '/v1/checkout/sessions']], $this->requests);
        $this->assertSame(0, ClientGatewayToken::count());
    }

    #[DataProvider('versions')]
    public function testCallbackRetrievesExpandedSessionAndStoresPaymentMethod(string $version): void
    {
        $this->mockStripe($version);
        $bacs = new BACS($this->driver);
        $request = Request::create('/confirm', 'GET', ['session_id' => 'cs_bacs']);
        $response = $bacs->authorizeResponse($request);

        $this->assertSame(route('client.payment_methods.index'), $response->getTargetUrl());
        $this->assertSame([
            ['get', '/v1/checkout/sessions/cs_bacs'],
            ['get', '/v1/payment_methods/pm_bacs'],
            ['get', '/v1/mandates/mandate_bacs'],
        ], $this->requests);
        $token = ClientGatewayToken::sole();
        $this->assertSame('pm_bacs', $token->token);
        $this->assertSame('cus_bacs', $token->gateway_customer_reference);
        $this->assertSame(11, $token->company_id);
        $this->assertSame(22, $token->client_id);
        $this->assertSame(33, $token->company_gateway_id);
        $this->assertSame(GatewayType::BACS, $token->gateway_type_id);
        $this->assertSame('108800', $token->meta->brand);
        $this->assertSame('1234', $token->meta->last4);
        $this->assertSame('mandate_bacs', $token->meta->mandate_id);
        $this->assertSame('authorized', $token->meta->state);
        $saved = $token->getRawOriginal();

        $bacs->authorizeResponse($request);

        $this->assertSame(1, ClientGatewayToken::count());
        $this->assertSame($saved, $token->fresh()->getRawOriginal());
    }

    #[DataProvider('versions')]
    public function testCallbackWithoutSessionDoesNotCallStripeOrStoreToken(string $version): void
    {
        $this->mockStripe($version);
        $response = (new BACS($this->driver))->authorizeResponse(Request::create('/confirm'));

        $this->assertSame(route('client.payment_methods.index'), $response->getTargetUrl());
        $this->assertSame([], $this->requests);
        $this->assertSame(0, ClientGatewayToken::count());
    }

    private function mockStripe(string $version): void
    {
        $this->driver->stripe_connect_auth = [
            'api_key' => 'sk_test_bacs', 'stripe_account' => 'acct_bacs', 'stripe_version' => $version,
        ];
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->andReturnUsing(function ($verb, $url, $headers, $params) use ($version) {
            $this->assertContains('Stripe-Account: acct_bacs', $headers);
            $this->assertContains('Stripe-Version: '.$version, $headers);
            $path = parse_url($url, PHP_URL_PATH);
            $this->requests[] = [$verb, $path];
            $method = [
                'id' => 'pm_bacs', 'object' => 'payment_method', 'type' => 'bacs_debit',
                'customer' => 'cus_bacs', 'livemode' => false,
                'bacs_debit' => ['sort_code' => '108800', 'last4' => '1234'],
            ];
            if ($path === '/v1/checkout/sessions') {
                $this->assertSame('post', $verb);
                $this->assertSame('setup', $params['mode']);
                $this->assertSame(['bacs_debit'], $params['payment_method_types']);
                $this->assertSame('cus_bacs', $params['customer']);
                $this->assertSame(route('client.payment_methods.index'), $params['cancel_url']);
                $this->assertSame(str_replace(['%7B', '%7D'], ['{', '}'], route('client.payment_methods.confirm', [
                    'method' => GatewayType::BACS, 'session_id' => '{CHECKOUT_SESSION_ID}',
                ])), $params['success_url']);
                $body = ['id' => 'cs_bacs', 'object' => 'checkout.session', 'customer' => 'cus_bacs', 'mode' => 'setup'];
            } elseif ($path === '/v1/checkout/sessions/cs_bacs') {
                $this->assertSame('get', $verb);
                $this->assertSame(['setup_intent'], $params['expand']);
                $body = [
                    'id' => 'cs_bacs', 'object' => 'checkout.session', 'mode' => 'setup', 'status' => 'complete',
                    'customer' => 'cus_bacs', 'livemode' => false,
                    'setup_intent' => [
                        'id' => 'seti_bacs', 'object' => 'setup_intent', 'status' => 'succeeded',
                        'usage' => 'off_session', 'customer' => 'cus_bacs', 'livemode' => false,
                        'payment_method' => 'pm_bacs', 'mandate' => 'mandate_bacs',
                    ],
                ];
            } elseif ($path === '/v1/mandates/mandate_bacs') {
                $this->assertSame('get', $verb);
                $body = [
                    'id' => 'mandate_bacs', 'object' => 'mandate', 'status' => 'active', 'type' => 'multi_use',
                    'payment_method' => 'pm_bacs', 'livemode' => false, 'on_behalf_of' => null,
                ];
            } elseif ($path === '/v1/payment_methods/pm_bacs') {
                $this->assertSame('get', $verb);
                $body = $method;
            } else {
                throw new \LogicException('Unexpected Stripe request: '.$path);
            }

            return [json_encode($body, JSON_THROW_ON_ERROR), 200, []];
        });
        ApiRequestor::setHttpClient($http);
    }
}
