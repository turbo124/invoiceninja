<?php

namespace Tests\Unit;

use App\Casts\ClientGroupSettingsCast;
use App\DataMapper\CompanySettings;
use App\Models\Client;
use LogicException;
use stdClass;
use Tests\TestCase;

class ClientSettingsCastTest extends TestCase
{
    /** @var list<string> */
    private const EXCLUDED_PROPERTIES = [
        'translations',
        'pdf_variables',
    ];

    public function testEveryConfiguredOverrideUsesTheCompanySettingsTypeContract(): void
    {
        $input = $this->configuredInput();
        $expected = $this->configuredExpectedValues();
        $settingsObject = (object) $input;
        $client = $this->clientWithSettings([]);

        $client->settings = $settingsObject;

        $stored = $this->storedSettings($client);
        $rehydrated = get_object_vars($this->clientWithSettings($stored)->settings);

        $this->assertCount(count($this->clientSettingCasts()), $stored);

        foreach ($this->clientSettingCasts() as $property => $type) {
            $message = "{$property} should use the {$type} CompanySettings contract.";

            $this->assertTrue(property_exists($settingsObject, $property), $message);
            $this->assertSame($expected[$property], $settingsObject->{$property}, $message);
            $this->assertArrayHasKey($property, $stored, $message);
            $this->assertSame($expected[$property], $stored[$property], $message);
            $this->assertArrayHasKey($property, $rehydrated, $message);
            $this->assertSame($expected[$property], $rehydrated[$property], $message);
        }
    }

    public function testPropertiesOutsideTheClientSettingsContractAreDiscarded(): void
    {
        $this->assertSame([
            'currency_id' => '2',
        ], $this->castStoredSettings([
            'currency_id' => 2,
            'translations' => ['invoice' => 'Not allowed at client level'],
            'pdf_variables' => ['invoice_details' => ['$invoice.number']],
            'entity' => Client::class,
            'industry_id' => 9,
            'size_id' => 10,
            'unknown_setting' => 'discard me',
        ]));
    }

    public function testFrequencyIdsFollowTheirStringContract(): void
    {
        $this->assertSame('string', CompanySettings::$casts['endless_reminder_frequency_id']);
        $this->assertSame('string', CompanySettings::$casts['reset_counter_frequency_id']);

        $this->assertSame([
            'endless_reminder_frequency_id' => '7',
            'reset_counter_frequency_id' => '8',
        ], $this->castStoredSettings([
            'endless_reminder_frequency_id' => 7,
            'reset_counter_frequency_id' => 8,
        ]));
    }

    public function testNativePropertyTypeIsAuthoritativeForSparseOverrides(): void
    {
        $casts = CompanySettings::$casts;

        try {
            CompanySettings::$casts['currency_id'] = 'integer';

            $this->assertSame([
                'currency_id' => '2',
            ], $this->castStoredSettings([
                'currency_id' => 2,
            ]));
        } finally {
            CompanySettings::$casts = $casts;
        }
    }

    public function testProtectedCompanyPropertiesCannotBecomeSparseOverrides(): void
    {
        $protectedFields = CompanySettings::$protected_fields;

        try {
            CompanySettings::$protected_fields = ['currency_id'];

            $this->assertSame([
                'send_reminders' => false,
            ], $this->castStoredSettings([
                'currency_id' => 2,
                'send_reminders' => false,
            ]));
        } finally {
            CompanySettings::$protected_fields = $protectedFields;
        }
    }

    public function testItHydratesOnlyConfiguredClientOverrides(): void
    {
        $client = $this->clientWithSettings([
            'currency_id' => 2,
            'default_task_rate' => '12.50',
            'send_reminders' => true,
            'translations' => ['invoice' => 'Not allowed at client level'],
            'pdf_variables' => ['invoice_details' => ['$invoice.number']],
            'unknown_setting' => 'discard me',
        ]);

        $settings = $client->settings;

        $this->assertInstanceOf(stdClass::class, $settings);
        $this->assertSame('2', $settings->currency_id);
        $this->assertSame(12.5, $settings->default_task_rate);
        $this->assertTrue($settings->send_reminders);
        $this->assertFalse(property_exists($settings, 'language_id'));
        $this->assertFalse(property_exists($settings, 'translations'));
        $this->assertFalse(property_exists($settings, 'pdf_variables'));
        $this->assertFalse(property_exists($settings, 'unknown_setting'));
    }

    public function testEveryConfiguredOverrideCanBeUnset(): void
    {
        $client = $this->clientWithSettings($this->configuredInput());
        $settings = $client->settings;

        foreach (array_keys($this->clientSettingCasts()) as $property) {
            unset($settings->{$property});
        }

        $stored = $this->storedSettings($client);

        $this->assertSame([], $stored);
        $this->assertSame([], get_object_vars($this->clientWithSettings($stored)->settings));
    }

    public function testEveryValidFalsyOverrideRemainsConfigured(): void
    {
        $input = [];
        $expected = [];

        foreach ($this->clientSettingCasts() as $property => $type) {
            $input[$property] = $this->falsyInputFor($type);
            $expected[$property] = $this->falsyExpectedValueFor($type);
        }

        $settingsObject = (object) $input;
        $client = $this->clientWithSettings([]);
        $client->settings = $settingsObject;

        $stored = $this->storedSettings($client);
        $rehydrated = $this->clientWithSettings($stored)->settings;

        $this->assertCount(count($this->clientSettingCasts()), $stored);

        foreach ($this->clientSettingCasts() as $property => $type) {
            $message = "{$property} should retain its valid falsy {$type} override.";

            $this->assertTrue(property_exists($settingsObject, $property), $message);
            $this->assertSame($expected[$property], $settingsObject->{$property}, $message);
            $this->assertArrayHasKey($property, $stored, $message);
            $this->assertTrue(property_exists($rehydrated, $property), $message);
            $this->assertSame($expected[$property], $rehydrated->{$property}, $message);
        }
    }

    public function testNullAndBlankValuesAreDiscardedForEveryContractProperty(): void
    {
        foreach (['null' => null, 'blank' => ''] as $case => $value) {
            $input = array_fill_keys(array_keys($this->clientSettingCasts()), $value);
            $settingsObject = (object) $input;
            $client = $this->clientWithSettings([]);

            $client->settings = $settingsObject;

            $this->assertSame([], get_object_vars($settingsObject), "{$case} properties should be removed.");
            $this->assertSame([], $this->storedSettings($client), "{$case} properties should not be stored.");
            $this->assertSame(
                [],
                get_object_vars($this->clientWithSettings($input)->settings),
                "{$case} properties should not be hydrated.",
            );
        }
    }

    public function testArrayAndObjectAssignmentsUseTheSameContract(): void
    {
        $input = [
            'currency_id' => 2,
            'default_task_rate' => '12.50',
            'send_reminders' => false,
            'invoice_number_counter' => '8',
        ];
        $expected = [
            'currency_id' => '2',
            'default_task_rate' => 12.5,
            'send_reminders' => false,
            'invoice_number_counter' => 8,
        ];

        foreach ([$input, (object) $input] as $value) {
            $client = $this->clientWithSettings([]);
            $client->settings = $value;

            $this->assertSame($expected, $this->storedSettings($client));
        }
    }

    public function testEmptyAssignmentsProduceAnEmptySparseObject(): void
    {
        foreach ([null, '', [], new stdClass()] as $value) {
            $client = $this->clientWithSettings($this->configuredInput());
            $client->settings = $value;

            $stored = $this->storedSettings($client);

            $this->assertSame([], $stored);
            $this->assertSame([], get_object_vars($this->clientWithSettings($stored)->settings));
        }
    }

    public function testModelArrayAndJsonSerializationPreserveTheSparseContract(): void
    {
        $client = $this->clientWithSettings([
            'currency_id' => 2,
            'send_reminders' => false,
            'language_id' => null,
        ]);

        $arraySettings = $client->toArray()['settings'];
        $jsonSettings = json_decode($client->toJson(), true, 512, JSON_THROW_ON_ERROR)['settings'];

        $this->assertInstanceOf(stdClass::class, $arraySettings);
        $this->assertSame([
            'currency_id' => '2',
            'send_reminders' => false,
        ], get_object_vars($arraySettings));
        $this->assertSame([
            'currency_id' => '2',
            'send_reminders' => false,
        ], $jsonSettings);
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
            $input[$property] = $this->configuredInputFor($type);
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
            $expected[$property] = $this->configuredExpectedValueFor($type);
        }

        return $expected;
    }

    private function configuredInputFor(string $type): mixed
    {
        return match ($type) {
            'string' => 42,
            'bool', 'boolean' => true,
            'int', 'integer' => '42',
            'real', 'float', 'double' => '12.5',
            default => throw new LogicException("Unsupported client setting cast type [{$type}]."),
        };
    }

    private function configuredExpectedValueFor(string $type): mixed
    {
        return match ($type) {
            'string' => '42',
            'bool', 'boolean' => true,
            'int', 'integer' => 42,
            'real', 'float', 'double' => 12.5,
            default => throw new LogicException("Unsupported client setting cast type [{$type}]."),
        };
    }

    private function falsyInputFor(string $type): mixed
    {
        return match ($type) {
            'string' => '0',
            'bool', 'boolean' => false,
            'int', 'integer' => 0,
            'real', 'float', 'double' => 0.0,
            default => throw new LogicException("Unsupported client setting cast type [{$type}]."),
        };
    }

    private function falsyExpectedValueFor(string $type): mixed
    {
        return $this->falsyInputFor($type);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function clientWithSettings(array $settings): Client
    {
        return $this->clientWithRawSettings(
            json_encode($settings === [] ? new stdClass() : $settings, JSON_THROW_ON_ERROR),
        );
    }

    private function clientWithRawSettings(string $settings): Client
    {
        $client = new Client();
        $client->mergeCasts(['settings' => ClientGroupSettingsCast::class]);
        $client->setRawAttributes(['settings' => $settings], true);

        return $client;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function castStoredSettings(array $settings): array
    {
        $client = $this->clientWithSettings([]);
        $client->settings = (object) $settings;

        return $this->storedSettings($client);
    }

    /**
     * @return array<string, mixed>
     */
    private function storedSettings(Client $client): array
    {
        return json_decode($client->getAttributes()['settings'], true, 512, JSON_THROW_ON_ERROR);
    }
}
