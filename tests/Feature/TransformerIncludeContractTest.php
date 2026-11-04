<?php

namespace Tests\Feature;

use App\Http\Controllers\BaseController;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Transformers\CompanyTransformer;
use App\Transformers\EntityTransformer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Tests\TestCase;

class TransformerIncludeContractTest extends TestCase
{
    public static function eagerLoadConstraintCases(): array
    {
        return [
            'untouched existing path' => [true, false],
            'overlapping path' => [true, true],
            'new path' => [false, true],
        ];
    }

    #[DataProvider('eagerLoadConstraintCases')]
    public function testEagerLoadConstraintsAreAppliedOnce(bool $has_existing, bool $include_clients): void
    {
        DB::connection()->beforeExecuting(static function () {
            throw new LogicException('Composing eager-load constraints must not query the database.');
        });
        $user = \Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes(['id' => 42]);
        if ($include_clients) {
            $user->shouldReceive('isAdmin')->once()->andReturn(false);
            $user->shouldReceive('hasPermission')->with('view_client')->once()->andReturn(false);
            $user->shouldReceive('hasExcludedPermissions')->once()->andReturn(false);
        } else {
            $user->shouldNotReceive('isAdmin');
        }
        auth()->setUser($user);

        $original_calls = 0;
        $query = Company::query();
        if ($has_existing) {
            $query->with(['clients' => function ($query) use (&$original_calls) {
                $original_calls++;
                $query->join('users as include_creator', 'include_creator.id', '=', 'clients.user_id')
                    ->where('clients.updated_at', '>=', '2026-01-01 00:00:00');
            }]);
        }

        $controller = app(BaseController::class);
        $manager = (new ReflectionProperty($controller, 'manager'))->getValue($controller);
        $manager->parseIncludes($include_clients ? ['clients'] : []);
        (new ReflectionMethod($controller, 'eagerLoadResponseIncludes'))->invoke(
            $controller, $query, new CompanyTransformer('array')
        );

        $client_query = Client::query();
        $query->getEagerLoads()['clients']($client_query);

        $this->assertSame($has_existing ? 1 : 0, $original_calls);
        $this->assertCount($has_existing ? 1 : 0, $client_query->getQuery()->joins ?? []);
        $expected_bindings = $has_existing ? ['2026-01-01 00:00:00'] : [];
        if ($include_clients) {
            array_push($expected_bindings, 42, 42);
            $this->assertStringContainsString('`clients`.`user_id` = ? or `clients`.`assigned_user_id` = ?', $client_query->toSql());
        }
        $this->assertSame($expected_bindings, $client_query->getBindings());
    }

    public static function declaredIncludes(): array
    {
        $cases = [];
        $directory = dirname(__DIR__, 2).'/app/Transformers/';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Transformer.php')) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($directory), -4);
            $transformer_class = 'App\\Transformers\\'.str_replace('/', '\\', $relative);
            if (! is_subclass_of($transformer_class, EntityTransformer::class)) {
                continue;
            }

            $type = (new ReflectionMethod($transformer_class, 'transform'))->getParameters()[0]->getType();
            if (! $type instanceof ReflectionNamedType || ! is_subclass_of($type->getName(), Model::class)) {
                throw new LogicException('Declare the model type for '.$transformer_class.'::transform');
            }

            $transformer = new $transformer_class('array');
            foreach (array_unique(array_merge($transformer->getDefaultIncludes(), $transformer->getAvailableIncludes())) as $include) {
                $cases[$relative.'.'.$include] = [$transformer_class, $type->getName(), $include];
            }
        }

        return $cases;
    }

    #[DataProvider('declaredIncludes')]
    public function testDeclaredIncludeMatchesItsRelationAndTransformer(string $transformer_class, string $model_class, string $include): void
    {
        // Planning and consuming a declared preload must not query live data.
        DB::connection()->beforeExecuting(static function () {
            throw new LogicException('Include contract attempted an unexpected database query.');
        });
        $user = new User();
        $user->setRawAttributes(['id' => 1]);
        auth()->setUser($user);

        $response_user = \Mockery::mock(User::class)->makePartial();
        $response_user->setRawAttributes(['id' => 3]);
        $response_user->shouldReceive('companyId')->andReturn(1);
        $response_user->shouldReceive('cannot')->with('view', \Mockery::type(Model::class))->andReturn(false);
        $transformer = new $transformer_class('array', $response_user);
        $model = new $model_class();
        $method = 'include'.Str::studly($include);
        $this->assertTrue(method_exists($transformer, $method));

        $paths = (new ReflectionMethod(BaseController::class, 'responseIncludePaths'))->invoke(
            app(BaseController::class), $model, $transformer, [$include]
        );

        // These computed includes have endpoint coverage in IncludePermissionsTest.
        if (in_array(class_basename($transformer).'.'.$include, [
                'CompanyGatewayTransformer.system_logs',
                'CompanyTransformer.designs',
                'CompanyTransformer.payment_terms',
                'CompanyUserTransformer.token',
                'AuthenticatedCompanyUserTransformer.token',
                'PaymentTransformer.type',
            ], true)) {
            $this->assertArrayNotHasKey($include, $paths);

            return;
        }

        $model->setRawAttributes(['id' => 1, 'company_id' => 1, 'user_id' => 1]);
        $related_models = [];
        foreach (array_keys($paths) as $path) {
            if (str_contains($path, '.')) {
                continue;
            }

            $relation = $model->newQueryWithoutRelationships()->getRelation($path);
            $this->assertInstanceOf(Relation::class, $relation);
            $related_model = $relation->getRelated();
            $related_model->setRawAttributes(['id' => 2, 'company_id' => 1, 'user_id' => 1]);
            foreach (['tags', 'documents', 'invitations'] as $preload) {
                $related_model->setRelation($preload, new Collection());
            }
            $is_collection = $relation instanceof HasMany || $relation instanceof HasManyThrough
                || $relation instanceof MorphMany || $relation instanceof BelongsToMany;
            $model->setRelation($path, $is_collection ? new Collection([$related_model]) : $related_model);
            $related_models[] = $related_model;
        }

        $resource = $transformer->{$method}($model);
        $this->assertNotNull($resource);
        $this->assertSame($response_user, (new ReflectionProperty(EntityTransformer::class, 'user'))->getValue($resource->getTransformer()));
        $data = $resource->getData();
        $related_model = $data instanceof Collection ? $data->first() : $data;
        $this->assertContains($related_model, $related_models);
        $child_type = (new ReflectionMethod($resource->getTransformer(), 'transform'))->getParameters()[0]->getType();
        $this->assertInstanceOf($child_type->getName(), $related_model);
    }

    public function testNestedIncludesPreserveExplicitUserAndDoNotInferAuthentication(): void
    {
        auth()->setUser(new User());
        $task = new \App\Models\Task();
        $client = new Client();
        $client->setRelation('group_settings', new \App\Models\GroupSetting());
        $task->setRelation('client', $client);
        $user_property = new ReflectionProperty(EntityTransformer::class, 'user');
        $serializer_property = new ReflectionProperty(EntityTransformer::class, 'serializer');

        foreach ([null, new User()] as $user) {
            foreach ([null, 'array', 'json'] as $serializer) {
                $transformer = new \App\Transformers\TaskTransformer($serializer, $user);
                $client_transformer = $transformer->includeClient($task)->getTransformer();
                $group_transformer = $client_transformer->includeGroupSettings($client)->getTransformer();

                foreach ([$transformer, $client_transformer, $group_transformer] as $nested_transformer) {
                    $this->assertSame($user, $user_property->getValue($nested_transformer));
                    $this->assertSame($serializer, $serializer_property->getValue($nested_transformer));
                }
            }
        }

        $this->assertNull($user_property->getValue(new \App\Transformers\TaskTransformer()));
        $this->assertNull($user_property->getValue(new \App\Transformers\TaskTransformer('array')));
    }

    public function testComputedPaymentTypeReceivesExplicitUser(): void
    {
        $user = new User();
        $transformer = new \App\Transformers\PaymentTransformer('array', $user);
        $resource = $transformer->includeType(new \App\Models\Payment());

        $this->assertSame($user, (new ReflectionProperty(EntityTransformer::class, 'user'))->getValue($resource->getTransformer()));
    }

    public function testCompanyReferencesOnlyEnableChildrenForTheExplicitViewersCompany(): void
    {
        $viewer = \Mockery::mock(User::class)->makePartial();
        $viewer->shouldReceive('companyId')->andReturn(1);
        auth()->setUser($viewer);

        foreach ([
            [\App\Models\CompanyUser::class, \App\Transformers\CompanyUserTransformer::class],
            [\App\Models\Product::class, \App\Transformers\ProductTransformer::class],
            [\App\Models\BankTransactionRule::class, \App\Transformers\BankTransactionRuleTransformer::class],
        ] as [$model_class, $transformer_class]) {
            foreach ([$viewer, null] as $user) {
                $parent_transformer = new $transformer_class('array', $user);
                // Reusing the parent must not carry the inactive company's restrictions into the active one.
                foreach ([2, 1] as $company_id) {
                    $company = new Company();
                    $company->setRawAttributes(['id' => $company_id]);
                    $model = (new $model_class())->setRelation('company', $company);
                    $child_transformer = $parent_transformer->includeCompany($model)->getTransformer();
                    if ($user && $company_id === 1) {
                        $this->assertContains('documents', $child_transformer->getDefaultIncludes());
                        $this->assertContains('tasks', $child_transformer->getAvailableIncludes());
                    } else {
                        $this->assertSame([], $child_transformer->getDefaultIncludes());
                        $this->assertSame([], $child_transformer->getAvailableIncludes());
                    }
                }
            }
        }
    }

    public static function profileIncludes(): array
    {
        return [
            'task creator' => [\App\Models\Task::class, 'user'],
            'task assignee' => [\App\Models\Task::class, 'assigned_user'],
            'project creator' => [\App\Models\Project::class, 'user'],
            'project assignee' => [\App\Models\Project::class, 'assigned_user'],
            'product creator' => [\App\Models\Product::class, 'user'],
            'activity creator' => [\App\Models\Activity::class, 'user'],
        ];
    }

    #[DataProvider('profileIncludes')]
    public function testProfilesArePreloadedWithoutTraversingUserMemberships(string $model_class, string $include): void
    {
        DB::connection()->beforeExecuting(static function () {
            throw new LogicException('Resolving profile includes must not query the database.');
        });
        $transformer_class = 'App\\Transformers\\'.class_basename($model_class).'Transformer';
        $transformer = new $transformer_class('array');
        $model = new $model_class();
        $paths = (new ReflectionMethod(BaseController::class, 'responseIncludePaths'))->invoke(
            app(BaseController::class), $model, $transformer, [$include.'.company_user.company.clients']
        );

        $this->assertArrayHasKey($include, $paths);
        foreach (array_keys($paths) as $path) {
            $this->assertFalse(str_starts_with($path, $include.'.'), $path);
        }

        $user = new User();
        $model->setRelation($include, $user);
        $resource = $transformer->{'include'.Str::studly($include)}($model);
        $this->assertSame($user, $resource->getData());
        $this->assertInstanceOf(\App\Transformers\UserProfileTransformer::class, $resource->getTransformer());
        $this->assertSame([], $resource->getTransformer()->getAvailableIncludes());
        $this->assertSame([], $resource->getTransformer()->getDefaultIncludes());
    }

    public function testFullUserIncludesStillTraverseCompanyMemberships(): void
    {
        $paths = (new ReflectionMethod(BaseController::class, 'responseIncludePaths'))->invoke(
            app(BaseController::class), new \App\Models\CompanyUser(),
            new \App\Transformers\CompanyUserTransformer('array'), ['user.company_user.company.clients']
        );

        $this->assertArrayHasKey('user.company_users.company.clients', $paths);
    }
}
