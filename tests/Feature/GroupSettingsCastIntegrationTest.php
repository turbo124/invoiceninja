<?php

namespace Tests\Feature;

use App\Casts\ClientGroupSettingsCast;
use App\Models\Client;
use App\Models\GroupSetting;
use App\Repositories\GroupSettingRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use stdClass;
use Tests\MockAccountData;
use Tests\TestCase;

class GroupSettingsCastIntegrationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testRegisteredGroupCastIsUsedThroughTheRepository(): void
    {
        $group = $this->client->group_settings;

        $this->assertTrue($group->hasCast('settings', ClientGroupSettingsCast::class));

        app(GroupSettingRepository::class)->save([
            'name' => $group->name,
            'settings' => [
                'currency_id' => 2,
                'send_reminders' => false,
                'default_task_rate' => 0,
                'statement_design_id' => 42,
                'task_round_up' => false,
                'language_id' => null,
                'tax_name1' => '',
                'translations' => ['invoice' => 'Not allowed'],
                'pdf_variables' => ['invoice_details' => ['$invoice.number']],
                'entity' => Client::class,
                'unknown_setting' => 'discard me',
            ],
        ], $group);

        $freshGroup = GroupSetting::query()->findOrFail($group->id);
        $stored = json_decode($freshGroup->getRawOriginal('settings'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([
            'currency_id' => '2',
            'send_reminders' => false,
            'default_task_rate' => 0,
            'statement_design_id' => '42',
            'task_round_up' => false,
        ], $stored);
        $this->assertSame('2', $freshGroup->settings->currency_id);
        $this->assertFalse($freshGroup->settings->send_reminders);
        $this->assertSame(0.0, $freshGroup->settings->default_task_rate);
        $this->assertFalse(property_exists($freshGroup->settings, 'language_id'));
        $this->assertFalse(property_exists($freshGroup->settings, 'tax_name1'));
        $this->assertFalse(property_exists($freshGroup->settings, 'entity'));
    }

    public function testGroupApiUsesTheSharedOverrideContractWithoutSettingsDataFiltering(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/group_settings', [
            'name' => 'cast-contract-group',
            'settings' => [
                'currency_id' => '2',
                'statement_design_id' => 'design-id',
                'task_round_up' => false,
                'send_reminders' => false,
                'tax_name1' => '',
                'language_id' => null,
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.settings.currency_id', '2');
        $response->assertJsonPath('data.settings.statement_design_id', 'design-id');
        $response->assertJsonPath('data.settings.task_round_up', false);
        $response->assertJsonPath('data.settings.send_reminders', false);
        $response->assertJsonMissingPath('data.settings.tax_name1');
        $response->assertJsonMissingPath('data.settings.language_id');

        $group = GroupSetting::query()->where('name', 'cast-contract-group')->firstOrFail();

        $this->assertSame([
            'currency_id' => '2',
            'statement_design_id' => 'design-id',
            'task_round_up' => false,
            'send_reminders' => false,
        ], json_decode($group->getRawOriginal('settings'), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testCanonicalGroupSettingsRemainCleanWhileMutationAndUnsetBecomeDirty(): void
    {
        $group = $this->client->group_settings;
        $group->settings = (object) [
            'currency_id' => '2',
            'send_reminders' => true,
        ];
        $group->save();

        $group = GroupSetting::query()->findOrFail($group->id);

        $this->assertFalse($group->isDirty('settings'));

        $group->settings;

        $this->assertFalse($group->isDirty('settings'));

        $group->settings->send_reminders = false;

        $this->assertTrue($group->isDirty('settings'));

        $group->save();
        $group = GroupSetting::query()->findOrFail($group->id);

        unset($group->settings->currency_id);

        $this->assertTrue($group->isDirty('settings'));
    }

    public function testEmptyGroupSettingsPersistAndSerializeAsAnObjectWithoutEntityMetadata(): void
    {
        $group = $this->client->group_settings;
        $group->settings = new stdClass();
        $group->save();

        $group = GroupSetting::query()->findOrFail($group->id);
        $arraySettings = $group->toArray()['settings'];

        $this->assertSame('{}', $group->getRawOriginal('settings'));
        $this->assertInstanceOf(stdClass::class, $arraySettings);
        $this->assertSame([], get_object_vars($arraySettings));
        $this->assertFalse(property_exists($group->settings, 'entity'));
        $this->assertStringContainsString('"settings":{}', $group->toJson());
    }

    public function testMergedSettingsNeverPopulateTheCachedGroupOverrideObject(): void
    {
        $group = $this->client->group_settings;
        $group->settings = (object) [
            'send_reminders' => false,
        ];
        $group->save();

        $this->client->settings = new stdClass();
        $this->client->save();

        $client = Client::query()->with('group_settings')->findOrFail($this->client->id);
        $groupOverrides = $client->group_settings->settings;
        $mergedSettings = $client->getMergedSettings();

        $client->timezone_offset();
        $client->getAttributes();
        $client->group_settings->getAttributes();

        $this->assertFalse($mergedSettings->send_reminders);
        $this->assertTrue(property_exists($mergedSettings, 'schedule_reminder1'));
        $this->assertSame(['send_reminders' => false], get_object_vars($groupOverrides));
        $this->assertSame(['send_reminders' => false], get_object_vars($client->group_settings->settings));
    }
}
