<?php

namespace Tests\Unit;

use App\DataMapper\CompanySettings;
use App\Http\ValidationRules\ValidClientGroupSettingsRule;
use App\Http\ValidationRules\ValidSettingsRule;
use PHPUnit\Framework\TestCase;

class SettingsValidationTest extends TestCase
{
    /** @var list<bool|int|string> */
    private const FALSE_VALUES = [false, 0, '0', 'false', 'off', 'no'];

    public function testCompanySettingsValidationAcceptsFalseValuesAndRejectsInvalidBooleans(): void
    {
        foreach (self::FALSE_VALUES as $value) {
            $this->assertTrue((new ValidSettingsRule())->passes('settings', [
                'send_reminders' => $value,
            ]));
        }

        $this->assertFalse((new ValidSettingsRule())->passes('settings', [
            'send_reminders' => 'not-a-boolean',
        ]));
    }

    public function testClientGroupSettingsValidationAcceptsFalseValuesAndRejectsInvalidBooleans(): void
    {
        foreach (self::FALSE_VALUES as $value) {
            $this->assertTrue((new ValidClientGroupSettingsRule())->passes('settings', [
                'send_reminders' => $value,
            ]));
        }

        $this->assertFalse((new ValidClientGroupSettingsRule())->passes('settings', [
            'send_reminders' => 'not-a-boolean',
        ]));
    }

    public function testCompanyNumericStringIdsRequireIntegerValues(): void
    {
        $rule = new ValidSettingsRule();

        foreach (CompanySettings::NUMERIC_STRING_CASTS as $property) {
            foreach ([0, 2, '0', '2', '002', '', null] as $value) {
                $this->assertTrue($rule->passes('settings', [
                    $property => $value,
                ]), "Expected {$property} value ".var_export($value, true).' to be accepted.');
            }

            foreach ([-2, 2.5, '-2', '+2', '2.0', '1e3', 'invalid'] as $value) {
                $this->assertFalse($rule->passes('settings', [
                    $property => $value,
                ]), "Expected {$property} value ".var_export($value, true).' to be rejected.');
            }
        }

        foreach (array_keys(CompanySettings::$casts) as $property) {
            if (! str_ends_with($property, '_id') || in_array($property, CompanySettings::NUMERIC_STRING_CASTS, true)) {
                continue;
            }

            $this->assertTrue($rule->passes('settings', [
                $property => 'VolejRejNm',
            ]), "Expected {$property} to accept an opaque ID.");

            foreach ([42, false, [], new \stdClass()] as $value) {
                $this->assertFalse($rule->passes('settings', [
                    $property => $value,
                ]), "Expected {$property} to reject a non-string value.");
            }
        }
    }
}
