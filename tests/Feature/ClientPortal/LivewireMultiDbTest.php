<?php

namespace Tests\Feature\ClientPortal;

use App\Libraries\MultiDB;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LivewireMultiDbTest extends TestCase
{
    private array $tenant_queries = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['ninja.db.multi_db_enabled' => true]);

        foreach (['l4_shard_a', 'l4_shard_b'] as $connection) {
            config(["database.connections.{$connection}" => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ]]);

            Schema::connection($connection)->create('livewire_shard_records', function (Blueprint $table) {
                $table->id();
                $table->string('label');
            });

            DB::connection($connection)->table('livewire_shard_records')->insert([
                ['id' => 42, 'label' => $connection],
                ['id' => 43, 'label' => $connection.'-second'],
            ]);
        }

        DB::listen(function (QueryExecuted $query) {
            if (str_contains($query->sql, 'livewire_shard_records')) {
                $this->tenant_queries[] = $query->connectionName;
            }
        });

        Livewire::component('l4-shard-probe', LivewireShardProbe::class);
        Livewire::addPersistentMiddleware(SelectLivewireShard::class);

        Route::middleware(['web', SelectLivewireShard::class])->get('/l4-multidb/{shard}', function () {
            return Blade::render(<<<'BLADE'
                @livewire('l4-shard-probe', ['switch_to' => 'l4_shard_b'], key('first'))
                @livewire('l4-shard-probe', [], key('second'))
                BLADE);
        });

        Route::middleware('web')->withoutMiddleware(\Illuminate\Routing\Middleware\SubstituteBindings::class)->get('/l4-multidb-unscoped', function () {
            MultiDB::setDb('l4_shard_b');

            return Blade::render("@livewire('l4-shard-probe')");
        });
    }

    protected function tearDown(): void
    {
        foreach (['l4_shard_a', 'l4_shard_b'] as $connection) {
            DB::purge($connection);
        }

        parent::tearDown();
    }

    public static function shardOrders(): array
    {
        return [['a', 'b'], ['b', 'a']];
    }

    #[DataProvider('shardOrders')]
    public function testBatchedReadsDoNotReuseAModelOrCollectionFromAnotherShard(string $first, string $second): void
    {
        $snapshots = [$this->snapshots($first)[1], $this->snapshots($second)[1]];
        $response = $this->updateSnapshots($snapshots, 'inspectRecords')->assertOk();

        foreach ([$first, $second] as $index => $shard) {
            $data = json_decode($response->json("components.{$index}.snapshot"), true)['data'];
            $this->assertSame('l4_shard_'.$shard, $data['observed_model_connection']);
            $this->assertSame('l4_shard_'.$shard, $data['observed_model_label']);
            $this->assertSame('l4_shard_'.$shard, $data['observed_collection_connection']);
        }
    }

    #[DataProvider('shardOrders')]
    public function testBatchedSnapshotsKeepModelsAndCollectionsOnTheirOriginalConnections(string $first, string $second): void
    {
        $first_snapshot = $this->snapshots($first)[1];
        $second_snapshot = $this->snapshots($second)[1];
        $this->tenant_queries = [];

        $response = $this->updateSnapshots([$first_snapshot, $second_snapshot]);

        $response->assertOk();
        $this->assertSame(['l4_shard_'.$first, 'l4_shard_'.$second], array_map(
            fn ($component) => json_decode($component['snapshot'], true)['data']['observed_connection'],
            $response->json('components'),
        ));

        foreach (['l4_shard_a', 'l4_shard_b'] as $connection) {
            $this->assertSame('written-'.$connection, DB::connection($connection)->table('livewire_shard_records')->where('id', 42)->value('label'));
            $this->assertSame('written-'.$connection.'-second', DB::connection($connection)->table('livewire_shard_records')->where('id', 43)->value('label'));
        }

        $this->assertContains('l4_shard_a', $this->tenant_queries);
        $this->assertContains('l4_shard_b', $this->tenant_queries);
    }

    public function testSameRouteRestoresTheDatabaseAfterThePreviousComponentSwitchesIt(): void
    {
        $snapshots = $this->snapshots('a');
        $this->tenant_queries = [];
        $response = $this->updateSnapshots($snapshots);

        $response->assertOk();

        foreach ($response->json('components') as $component) {
            $this->assertSame('l4_shard_a', json_decode($component['snapshot'], true)['data']['observed_connection']);
        }

        $this->assertNotContains('l4_shard_b', $this->tenant_queries);
        $this->assertSame('l4_shard_b', DB::connection('l4_shard_b')->table('livewire_shard_records')->where('id', 42)->value('label'));
    }

    public function testConsecutiveRequestsDoNotReuseModelsFromThePreviousShard(): void
    {
        foreach (['a', 'b', 'a'] as $shard) {
            $snapshot = $this->snapshots($shard)[1];
            $response = $this->updateSnapshots([$snapshot]);
            $response->assertOk();

            $this->assertSame('l4_shard_'.$shard, json_decode($response->json('components.0.snapshot'), true)['data']['observed_connection']);
        }
    }

    public function testAnUnscopedComponentDoesNotOverwriteThePreviousMiddlewareConnection(): void
    {
        $scoped_snapshots = $this->snapshots('a');
        $unscoped_response = $this->get('/l4-multidb-unscoped')->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $unscoped_response->getContent(), $matches);

        $response = $this->updateSnapshots([
            $scoped_snapshots[0],
            html_entity_decode($matches[1], ENT_QUOTES),
            $scoped_snapshots[1],
        ])->assertOk();

        $this->assertSame('l4_shard_a', json_decode($response->json('components.2.snapshot'), true)['data']['observed_connection']);
        $this->assertSame('written-l4_shard_a', DB::connection('l4_shard_a')->table('livewire_shard_records')->where('id', 42)->value('label'));
    }

    private function snapshots(string $shard): array
    {
        $response = $this->get('/l4-multidb/'.$shard)->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshots = array_map(fn ($snapshot) => html_entity_decode($snapshot, ENT_QUOTES), $matches[1]);
        $this->assertCount(2, $snapshots);

        return $snapshots;
    }

    private function updateSnapshots(array $snapshots, string $method = 'writeRecords'): \Illuminate\Testing\TestResponse
    {
        // Deliberately begin on the wrong connection: persistent middleware must
        // establish the shard before Livewire restores any public model property.
        MultiDB::setDb('l4_shard_b');

        return $this->postJson(Livewire::getUpdateUri(), [
            'components' => array_map(fn ($snapshot) => [
                'snapshot' => $snapshot, 'updates' => [],
                'calls' => [['method' => $method, 'params' => []]],
            ], $snapshots),
        ], ['X-Livewire' => '1']);
    }
}

class SelectLivewireShard
{
    public function handle(Request $request, Closure $next)
    {
        MultiDB::setDb('l4_shard_'.$request->route('shard'));

        return $next($request);
    }
}

class LivewireShardRecord extends Model
{
    protected $table = 'livewire_shard_records';

    public $timestamps = false;
}

class LivewireShardProbe extends Component
{
    public LivewireShardRecord $record;

    public Collection $records;

    public string $switch_to = '';

    public string $observed_connection = '';

    public string $observed_model_connection = '';

    public string $observed_model_label = '';

    public string $observed_collection_connection = '';

    public function mount(): void
    {
        $this->record = LivewireShardRecord::findOrFail(42);
        $this->records = LivewireShardRecord::where('id', 43)->get();
    }

    public function writeRecords(): void
    {
        $this->observed_connection = config('database.default');
        $this->record->label = 'written-'.$this->observed_connection;
        $this->record->save();

        foreach ($this->records as $record) {
            $record->label = 'written-'.$this->observed_connection.'-second';
            $record->save();
        }

        if ($this->switch_to !== '') {
            MultiDB::setDb($this->switch_to);
        }
    }

    public function inspectRecords(): void
    {
        $this->observed_model_connection = $this->record->getConnectionName();
        $this->observed_model_label = $this->record->label;
        $this->observed_collection_connection = $this->records->first()->getConnectionName();
    }

    public function render(): string
    {
        return '<div>{{ $record->label }}</div>';
    }
}
