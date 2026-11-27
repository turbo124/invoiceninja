<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Company;
use App\Models\GroupSetting;
use App\Models\Quote;
use App\Models\Timezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientTimezoneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->instance('timezones', collect([
            (new Timezone())->forceFill(['id' => 1, 'name' => 'UTC']),
            (new Timezone())->forceFill(['id' => 2, 'name' => 'Australia/Sydney']),
            (new Timezone())->forceFill(['id' => 3, 'name' => 'America/Los_Angeles']),
        ]));
    }

    public function testTimezonePreservesSettingsPrecedenceAndReflectsChangesWithoutQueries(): void
    {
        $client = $this->client();
        $query_count = 0;
        DB::listen(function () use (&$query_count) {
            $query_count++;
        });

        $this->assertSame('UTC', $client->timezone()->name);

        $client->setRelation('group_settings', (new GroupSetting())->forceFill([
            'settings' => (object) ['timezone_id' => '2'],
        ]));
        $this->assertSame('Australia/Sydney', $client->timezone()->name);

        $client->settings = (object) ['timezone_id' => '3'];
        $this->assertSame('America/Los_Angeles', $client->timezone()->name);

        $client->settings = (object) ['timezone_id' => ''];
        $this->assertSame('Australia/Sydney', $client->timezone()->name);

        $client->setRelation('group_settings', null);
        $this->assertSame('UTC', $client->timezone()->name);
        $this->assertSame(0, $query_count);
    }

    public function testQuoteExpiryUsesEachClientsLocalDateWithoutTimezoneQueries(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 13:00:00', 'UTC'));
        $query_count = 0;
        DB::listen(function () use (&$query_count) {
            $query_count++;
        });

        for ($i = 0; $i < 50; $i++) {
            foreach ([1 => Quote::STATUS_SENT, 2 => Quote::STATUS_EXPIRED, 3 => Quote::STATUS_SENT] as $timezone_id => $status) {
                $client = $this->client();
                $client->settings = (object) ['timezone_id' => (string) $timezone_id];

                $quote = new Quote();
                $quote->setRawAttributes([
                    'status_id' => Quote::STATUS_SENT,
                    'is_deleted' => false,
                    'due_date' => '2026-11-10',
                ], true);
                $quote->setRelation('client', $client);

                $this->assertSame($status, $quote->status_id);
                $this->assertSame(Quote::STATUS_SENT, $quote->getRawOriginal('status_id'));
            }
        }

        $this->assertSame(0, $query_count);
    }

    private function client(): Client
    {
        $client = new Client();
        $client->settings = new \stdClass();
        $client->setRelation('group_settings', null);
        $client->setRelation('company', (new Company())->forceFill([
            'settings' => (object) ['timezone_id' => '1'],
        ]));

        return $client;
    }
}
