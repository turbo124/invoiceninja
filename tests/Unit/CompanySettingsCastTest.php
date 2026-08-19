<?php

namespace Tests\Unit;

use App\Casts\CompanySettingsCast;
use App\DataMapper\CompanySettings;
use App\Models\Company;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use stdClass;
use Tests\TestCase;

class CompanySettingsCastTest extends TestCase
{
    public function testCompanyModelRegistersTheSettingsCast(): void
    {
        $this->assertTrue((new Company())->hasCast('settings', CompanySettingsCast::class));
    }

    public function testNativePropertyTypesMatchTheTransitionalCastMap(): void
    {
        $reflection = new ReflectionClass(CompanySettings::class);
        $properties = array_filter(
            $reflection->getProperties(),
            static fn ($property): bool => $property->isPublic() && ! $property->isStatic(),
        );

        $this->assertCount(count(CompanySettings::$casts) + 1, $properties);

        foreach (CompanySettings::$casts as $property => $cast) {
            $message = "{$property} should declare the {$cast} settings contract natively.";
            $reflectionProperty = $reflection->getProperty($property);
            $type = $reflectionProperty->getType();

            $this->assertInstanceOf(ReflectionNamedType::class, $type, $message);
            $this->assertFalse($type->allowsNull(), $message);
            $this->assertSame($this->nativeTypeFor($cast), $type->getName(), $message);
        }

        $translationsType = $reflection->getProperty('translations')->getType();

        $this->assertInstanceOf(ReflectionNamedType::class, $translationsType);
        $this->assertSame('object', $translationsType->getName());
        $this->assertFalse($translationsType->allowsNull());
    }

    public function testEveryConfiguredPropertyUsesTheNativeCompanySettingsContract(): void
    {
        $company = $this->companyWithSettings($this->configuredInput());
        $settings = $company->settings;

        $this->assertInstanceOf(CompanySettings::class, $settings);
        $this->assertCount(count(CompanySettings::$casts) + 1, get_object_vars($settings));

        foreach (CompanySettings::$casts as $property => $cast) {
            $message = "{$property} should hydrate as {$cast}.";
            $expected = $this->configuredExpectedValueFor($cast);

            if ($cast === 'object') {
                $this->assertEquals($expected, $settings->{$property}, $message);
            } else {
                $this->assertSame($expected, $settings->{$property}, $message);
            }
        }

        $this->assertSame('Invoice', $settings->translations->invoice);
    }

    public function testNativePropertyTypeIsAuthoritativeForCompleteSettings(): void
    {
        $casts = CompanySettings::$casts;

        try {
            CompanySettings::$casts['currency_id'] = 'integer';

            $settings = $this->companyWithSettings([
                'currency_id' => 2,
            ])->settings;

            $this->assertSame('2', $settings->currency_id);
        } finally {
            CompanySettings::$casts = $casts;
        }
    }

    public function testNullAndMissingPropertiesResolveToTypedCompanyDefaults(): void
    {
        $defaults = CompanySettings::defaults();
        $input = array_fill_keys(array_keys(CompanySettings::$casts), null);
        $input['translations'] = null;
        $settings = $this->companyWithSettings($input)->settings;

        foreach (get_object_vars($defaults) as $property => $default) {
            $message = "{$property} should fall back to its complete company default.";

            if (is_object($default)) {
                $this->assertEquals($default, $settings->{$property}, $message);
            } else {
                $this->assertSame($default, $settings->{$property}, $message);
            }
        }
    }

    public function testTranslationsAndPdfVariablesRemainTypedObjects(): void
    {
        $settings = $this->companyWithSettings([
            'translations' => [
                'invoice' => 'Invoice',
                'quote' => '',
                'credit' => null,
            ],
            'pdf_variables' => [
                'invoice_details' => ['$invoice.number'],
            ],
        ])->settings;

        $this->assertInstanceOf(stdClass::class, $settings->translations);
        $this->assertSame(['invoice' => 'Invoice'], get_object_vars($settings->translations));
        $this->assertInstanceOf(stdClass::class, $settings->pdf_variables);
        $this->assertSame(
            ['invoice_details' => ['$invoice.number']],
            get_object_vars($settings->pdf_variables),
        );
    }

    public function testDollarPrefixedPdfVariablesAndTranslationsAreNotEscaped(): void
    {
        $company = $this->companyWithSettings([]);
        $company->settings = (object) [
            'translations' => [
                'invoice' => '$invoice',
            ],
            'pdf_variables' => [
                'statement_credit_columns' => [
                    '$credit.number',
                    '$credit.date',
                    '$total',
                    '$credit.balance',
                ],
            ],
        ];

        $rawSettings = $company->getAttributes()['settings'];
        $storedSettings = json_decode($rawSettings, true, 512, JSON_THROW_ON_ERROR);
        $serializedSettings = json_decode($company->toJson(), true, 512, JSON_THROW_ON_ERROR)['settings'];

        $this->assertStringNotContainsString('\\$', $rawSettings);
        $this->assertSame('$invoice', $storedSettings['translations']['invoice']);
        $this->assertSame(
            ['$credit.number', '$credit.date', '$total', '$credit.balance'],
            $storedSettings['pdf_variables']['statement_credit_columns'],
        );
        $this->assertSame('$invoice', $serializedSettings['translations']['invoice']);
        $this->assertSame(
            ['$credit.number', '$credit.date', '$total', '$credit.balance'],
            $serializedSettings['pdf_variables']['statement_credit_columns'],
        );
    }

    public function testAssignmentsPersistACompleteObjectAndDiscardUnknownProperties(): void
    {
        $company = $this->companyWithSettings([]);
        $company->settings = (object) [
            'currency_id' => 2,
            'send_reminders' => false,
            'default_task_rate' => '12.5',
            'unknown_setting' => 'discard me',
        ];

        $stored = json_decode($company->getAttributes()['settings'], true, 512, JSON_THROW_ON_ERROR);
        $rehydrated = $this->companyWithSettings($stored)->settings;

        $this->assertCount(count(CompanySettings::$casts) + 1, $stored);
        $this->assertArrayNotHasKey('unknown_setting', $stored);
        $this->assertSame('2', $stored['currency_id']);
        $this->assertFalse($stored['send_reminders']);
        $this->assertSame(12.5, $stored['default_task_rate']);
        $this->assertInstanceOf(CompanySettings::class, $rehydrated);
        $this->assertSame('2', $rehydrated->currency_id);
        $this->assertFalse($rehydrated->send_reminders);
        $this->assertSame(12.5, $rehydrated->default_task_rate);
    }

    public function testModelSerializationUsesACompletePlainSettingsObject(): void
    {
        $company = $this->companyWithSettings([
            'currency_id' => 2,
            'send_reminders' => false,
        ]);
        $arraySettings = $company->toArray()['settings'];
        $jsonSettings = json_decode($company->toJson(), true, 512, JSON_THROW_ON_ERROR)['settings'];

        $this->assertInstanceOf(stdClass::class, $arraySettings);
        $this->assertCount(count(CompanySettings::$casts) + 1, get_object_vars($arraySettings));
        $this->assertCount(count(CompanySettings::$casts) + 1, $jsonSettings);
        $this->assertSame('2', $jsonSettings['currency_id']);
        $this->assertFalse($jsonSettings['send_reminders']);
    }

    /**
     * @return array<string, mixed>
     */
    private function configuredInput(): array
    {
        $input = ['translations' => ['invoice' => 'Invoice']];

        foreach (CompanySettings::$casts as $property => $cast) {
            $input[$property] = $this->configuredInputFor($cast);
        }

        return $input;
    }

    private function configuredInputFor(string $cast): mixed
    {
        return match ($cast) {
            'string' => 42,
            'bool', 'boolean' => true,
            'int', 'integer' => '42',
            'real', 'float', 'double' => '12.5',
            'object' => ['invoice_details' => ['$invoice.number']],
            default => throw new LogicException("Unsupported company setting cast type [{$cast}]."),
        };
    }

    private function configuredExpectedValueFor(string $cast): mixed
    {
        return match ($cast) {
            'string' => '42',
            'bool', 'boolean' => true,
            'int', 'integer' => 42,
            'real', 'float', 'double' => 12.5,
            'object' => (object) ['invoice_details' => ['$invoice.number']],
            default => throw new LogicException("Unsupported company setting cast type [{$cast}]."),
        };
    }

    private function nativeTypeFor(string $cast): string
    {
        return match ($cast) {
            'bool', 'boolean' => 'bool',
            'int', 'integer' => 'int',
            'real', 'float', 'double' => 'float',
            default => $cast,
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function companyWithSettings(array $settings): Company
    {
        $company = new Company();
        $company->mergeCasts(['settings' => CompanySettingsCast::class]);
        $company->setRawAttributes([
            'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
        ], true);

        return $company;
    }
}
