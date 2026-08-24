<?php

namespace Tests\Unit;

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
}
