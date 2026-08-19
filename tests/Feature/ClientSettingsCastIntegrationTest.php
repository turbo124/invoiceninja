<?php

namespace Tests\Feature;

use App\Casts\ClientGroupSettingsCast;
use App\DataMapper\CompanySettings;
use App\Models\Client;
use App\Repositories\ClientRepository;
use App\Transformers\ClientTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use stdClass;
use Tests\MockAccountData;
use Tests\TestCase;

class ClientSettingsCastIntegrationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    /** @var list<string> */
    private const EXCLUDED_PROPERTIES = [
        'translations',
        'pdf_variables',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testClientSettingsSurviveADatabaseRoundTripAndUnsetPersists(): void
    {
        $client = $this->withClientSettingsCast($this->client);
        $settings = new stdClass();
        $settings->currency_id = 2;
        $settings->send_reminders = false;
        $settings->default_task_rate = 0;
        $settings->endless_reminder_frequency_id = 7;
        $settings->reset_counter_frequency_id = 8;
        $settings->language_id = null;
        $client->settings = $settings;
        $client->save();

        $freshClient = $this->withClientSettingsCast(Client::query()->findOrFail($client->id));

        $this->assertSame('2', $freshClient->settings->currency_id);
        $this->assertFalse($freshClient->settings->send_reminders);
        $this->assertSame(0.0, $freshClient->settings->default_task_rate);
        $this->assertSame('7', $freshClient->settings->endless_reminder_frequency_id);
        $this->assertSame('8', $freshClient->settings->reset_counter_frequency_id);
        $this->assertFalse(property_exists($freshClient->settings, 'language_id'));

        unset($freshClient->settings->currency_id);
        $freshClient->save();

        $rehydratedClient = $this->withClientSettingsCast(Client::query()->findOrFail($client->id));
        $storedSettings = json_decode(
            $rehydratedClient->getRawOriginal('settings'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertArrayNotHasKey('currency_id', $storedSettings);
        $this->assertFalse(property_exists($rehydratedClient->settings, 'currency_id'));
        $this->assertFalse($rehydratedClient->settings->send_reminders);
        $this->assertSame(0.0, $rehydratedClient->settings->default_task_rate);
        $this->assertSame('7', $rehydratedClient->settings->endless_reminder_frequency_id);
        $this->assertSame('8', $rehydratedClient->settings->reset_counter_frequency_id);
    }

    public function testEveryContractPropertySurvivesADatabaseRoundTrip(): void
    {
        $input = $this->configuredInput();
        $expected = $this->configuredExpectedValues();
        $client = $this->withClientSettingsCast($this->client);
        $client->settings = (object) $input;
        $client->save();

        $freshClient = $this->withClientSettingsCast(Client::query()->findOrFail($client->id));
        $stored = json_decode(
            $freshClient->getRawOriginal('settings'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $rehydrated = get_object_vars($freshClient->settings);

        $this->assertCount(count($this->clientSettingCasts()), $stored);
        $this->assertCount(count($this->clientSettingCasts()), $rehydrated);

        foreach ($this->clientSettingCasts() as $property => $type) {
            $message = "{$property} should survive persistence as {$type}.";

            $this->assertArrayHasKey($property, $stored, $message);
            $this->assertSame($expected[$property], $stored[$property], $message);
            $this->assertArrayHasKey($property, $rehydrated, $message);
            $this->assertSame($expected[$property], $rehydrated[$property], $message);
        }
    }

    public function testInPlaceUpdatesAreNormalizedAndPersisted(): void
    {
        $client = $this->withClientSettingsCast($this->client);
        $client->settings = (object) [
            'currency_id' => '2',
            'default_task_rate' => 10,
            'send_reminders' => true,
            'payment_terms' => '14',
        ];
        $client->save();

        $freshClient = $this->withClientSettingsCast(Client::query()->findOrFail($client->id));
        $freshClient->settings->currency_id = 3;
        $freshClient->settings->default_task_rate = '15.25';
        $freshClient->settings->send_reminders = false;
        $freshClient->settings->payment_terms = null;
        $freshClient->save();

        $rehydratedClient = $this->withClientSettingsCast(Client::query()->findOrFail($client->id));

        $this->assertSame('3', $rehydratedClient->settings->currency_id);
        $this->assertSame(15.25, $rehydratedClient->settings->default_task_rate);
        $this->assertFalse($rehydratedClient->settings->send_reminders);
        $this->assertFalse(property_exists($rehydratedClient->settings, 'payment_terms'));
    }

    public function testSparseOverridesPreserveCompanyFallbackAndFalsyClientValues(): void
    {
        $client = $this->withClientSettingsCast($this->client);
        $companyCurrencyId = $client->company->settings->currency_id;
        $client->settings = (object) [
            'send_reminders' => false,
            'default_task_rate' => 0,
        ];

        $this->assertSame($companyCurrencyId, $client->getSetting('currency_id'));
        $this->assertFalse($client->getSetting('send_reminders'));
        $this->assertSame(0.0, $client->getSetting('default_task_rate'));

        $mergedSettings = $client->getMergedSettings();

        $this->assertSame($companyCurrencyId, $mergedSettings->currency_id);
        $this->assertFalse($mergedSettings->send_reminders);
        $this->assertSame(0.0, $mergedSettings->default_task_rate);
    }

    public function testMergedSettingsWithoutAGroupRemainDetachedFromCachedClientOverrides(): void
    {
        $this->client->group_settings_id = null;
        $this->client->settings = (object) [
            'send_reminders' => false,
        ];
        $this->client->save();

        $client = Client::query()->findOrFail($this->client->id);
        $mergedSettings = $client->getMergedSettings();
        $clientSettings = $client->settings;

        $this->assertNotSame($clientSettings, $mergedSettings);
        $this->assertTrue(property_exists($mergedSettings, 'schedule_reminder1'));
        $this->assertSame('', $mergedSettings->schedule_reminder1);

        $client->timezone_offset();
        $client->getAttributes();

        $this->assertTrue(property_exists($mergedSettings, 'schedule_reminder1'));
        $this->assertSame('', $mergedSettings->schedule_reminder1);
        $this->assertSame(['send_reminders' => false], get_object_vars($client->settings));

        $client->save();

        $freshClient = Client::query()->findOrFail($client->id);

        $this->assertSame(
            ['send_reminders' => false],
            json_decode($freshClient->getRawOriginal('settings'), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testMergedSettingsWithAGroupRemainDetachedFromCachedClientOverrides(): void
    {
        $groupSettings = $this->client->group_settings;
        $groupSettings->settings = (object) [
            'language_id' => '2',
        ];
        $groupSettings->save();

        $this->client->settings = (object) [
            'send_reminders' => false,
        ];
        $this->client->save();

        $client = Client::query()->findOrFail($this->client->id);
        $mergedSettings = $client->getMergedSettings();
        $clientSettings = $client->settings;

        $this->assertNotSame($clientSettings, $mergedSettings);
        $this->assertSame('2', $mergedSettings->language_id);
        $this->assertTrue(property_exists($mergedSettings, 'schedule_reminder1'));
        $this->assertSame('', $mergedSettings->schedule_reminder1);

        $client->timezone_offset();
        $client->getAttributes();

        $this->assertSame('2', $mergedSettings->language_id);
        $this->assertTrue(property_exists($mergedSettings, 'schedule_reminder1'));
        $this->assertSame('', $mergedSettings->schedule_reminder1);
        $this->assertSame(['send_reminders' => false], get_object_vars($client->settings));

        $client->save();

        $freshClient = Client::query()->findOrFail($client->id);

        $this->assertSame(
            ['send_reminders' => false],
            json_decode($freshClient->getRawOriginal('settings'), true, 512, JSON_THROW_ON_ERROR),
        );
        $this->assertSame(['language_id' => '2'], get_object_vars($groupSettings->fresh()->settings));
    }

    public function testRegisteredClientSettingsCastIsUsedThroughTheRepository(): void
    {
        $client = Client::query()->findOrFail($this->client->id);

        $this->assertTrue($client->hasCast('settings', ClientGroupSettingsCast::class));

        app(ClientRepository::class)->save([
            'name' => $client->name,
            'settings' => [
                'currency_id' => 2,
                'send_reminders' => false,
                'default_task_rate' => 0,
                'language_id' => null,
                'pdf_variables' => ['invoice_details' => ['$invoice.number']],
            ],
        ], $client);

        $freshClient = Client::query()->findOrFail($client->id);
        $storedSettings = json_decode(
            $freshClient->getRawOriginal('settings'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame([
            'currency_id' => '2',
            'send_reminders' => false,
            'default_task_rate' => 0,
        ], $storedSettings);
        $this->assertSame('2', $freshClient->settings->currency_id);
        $this->assertFalse($freshClient->settings->send_reminders);
        $this->assertSame(0.0, $freshClient->settings->default_task_rate);
        $this->assertFalse(property_exists($freshClient->settings, 'language_id'));
        $this->assertFalse(property_exists($freshClient->settings, 'pdf_variables'));
    }

    public function testCanonicalSettingsRemainCleanWhileMutationAndUnsetBecomeDirty(): void
    {
        $this->client->settings = (object) [
            'currency_id' => '2',
            'send_reminders' => true,
        ];
        $this->client->save();

        $client = Client::query()->findOrFail($this->client->id);

        $this->assertFalse($client->isDirty('settings'));

        $client->settings;

        $this->assertFalse($client->isDirty('settings'));

        $client->settings->send_reminders = false;

        $this->assertTrue($client->isDirty('settings'));

        $client->save();
        $client = Client::query()->findOrFail($client->id);

        $this->assertFalse($client->isDirty('settings'));

        unset($client->settings->currency_id);

        $this->assertTrue($client->isDirty('settings'));
    }

    public function testEmptySettingsPersistAndSerializeAsAJsonObject(): void
    {
        $this->client->settings = new stdClass();
        $this->client->save();

        $client = Client::query()->findOrFail($this->client->id);
        $arraySettings = $client->toArray()['settings'];

        $this->assertSame('{}', $client->getRawOriginal('settings'));
        $this->assertInstanceOf(stdClass::class, $arraySettings);
        $this->assertSame([], get_object_vars($arraySettings));
        $this->assertSame('{}', json_encode($arraySettings, JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('"settings":{}', $client->toJson());
    }

    public function testClientTransformerPreservesTheSparseSettingsShape(): void
    {
        $client = $this->withClientSettingsCast($this->client);
        $settings = new stdClass();
        $settings->currency_id = 2;
        $settings->send_reminders = false;
        $client->settings = $settings;

        $payload = (new ClientTransformer())->transform($client);

        $this->assertInstanceOf(stdClass::class, $payload['settings']);
        $this->assertSame([
            'currency_id' => '2',
            'send_reminders' => false,
        ], get_object_vars($payload['settings']));
        $this->assertSame(
            ['currency_id' => '2', 'send_reminders' => false],
            json_decode(json_encode($payload['settings'], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
        );
        $this->assertFalse(property_exists($payload['settings'], 'language_id'));
    }

    public function testClientTransformerSerializesEveryContractProperty(): void
    {
        $input = $this->configuredInput();
        $expected = $this->configuredExpectedValues();
        $client = $this->withClientSettingsCast($this->client);
        $client->settings = (object) $input;

        $payload = (new ClientTransformer())->transform($client);
        $transformed = get_object_vars($payload['settings']);

        $this->assertCount(count($this->clientSettingCasts()), $transformed);

        foreach ($this->clientSettingCasts() as $property => $type) {
            $message = "{$property} should be transformed as {$type}.";

            $this->assertArrayHasKey($property, $transformed, $message);
            $this->assertSame($expected[$property], $transformed[$property], $message);
        }
    }

    /**
     * @return array<string, string>
     */
    private function clientSettingCasts(): array
    {
        return array_diff_key(
            CompanySettings::$casts,
            array_fill_keys(self::EXCLUDED_PROPERTIES, true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function configuredInput(): array
    {
        $input = [];

        foreach ($this->clientSettingCasts() as $property => $type) {
            $input[$property] = match ($type) {
                'string' => 42,
                'bool', 'boolean' => true,
                'int', 'integer' => '42',
                'real', 'float', 'double' => '12.5',
                default => throw new LogicException("Unsupported client setting cast type [{$type}]."),
            };
        }

        return $input;
    }

    /**
     * @return array<string, mixed>
     */
    private function configuredExpectedValues(): array
    {
        $expected = [];

        foreach ($this->clientSettingCasts() as $property => $type) {
            $expected[$property] = match ($type) {
                'string' => '42',
                'bool', 'boolean' => true,
                'int', 'integer' => 42,
                'real', 'float', 'double' => 12.5,
                default => throw new LogicException("Unsupported client setting cast type [{$type}]."),
            };
        }

        return $expected;
    }

    private function withClientSettingsCast(Client $client): Client
    {
        $client->mergeCasts(['settings' => ClientGroupSettingsCast::class]);

        return $client;
    }
}
