<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use App\DataMapper\CompanySettings;
use App\DataMapper\EntitySettings;
use stdClass;

/**
 * Trait EntitySettingsSaver.
 *
 * Handles saving and validation of entity-level settings (Invoice, Quote, Credit, etc.)
 * Only whitelisted settings from EntitySettings::$entity_settings are permitted.
 */
trait EntitySettingsSaver
{
    /**
     * Saves entity-level settings, filtering to only whitelisted properties.
     *
     * @param object|array|null $settings The incoming settings
     * @return object|null The filtered and validated settings, or null if empty
     */
    public function saveEntitySettings($settings): ?object
    {
        if (! $settings) {
            return null;
        }

        if (is_array($settings)) {
            $settings = (object) $settings;
        }

        // Filter to only whitelisted entity settings
        $settings = EntitySettings::filterEntitySettings($settings);

        // Remove empty/unset properties (empty string means "inherit from parent")
        foreach ($settings as $key => $value) {
            if (! isset($settings->{$key}) || (is_string($settings->{$key}) && iconv_strlen($settings->{$key}) < 1)) {
                unset($settings->{$key});
            }
        }

        // Type check against CompanySettings casts
        $settings = $this->checkEntitySettingType($settings);

        // If no properties remain after filtering, return null
        if (count((array) $settings) === 0) {
            return null;
        }

        // Merge with existing entity settings if present
        $entity_settings = $this->settings ? clone $this->settings : new stdClass();

        foreach ($settings as $key => $value) {
            $entity_settings->{$key} = $value;
        }

        // Final filter to ensure only whitelisted keys persist
        return EntitySettings::filterEntitySettings($entity_settings);
    }

    /**
     * Validate inbound entity settings.
     *
     * @param object|array $settings
     * @return array|bool Array of errors on failure, TRUE on success
     */
    public function validateEntitySettings($settings)
    {
        if (is_array($settings)) {
            $settings = (object) $settings;
        }

        $casts = CompanySettings::$casts;

        foreach ($settings as $key => $value) {
            // Must be a whitelisted entity setting
            if (! EntitySettings::isEntitySetting($key)) {
                return [$key, 'not_allowed', 'Setting not permitted at entity level'];
            }

            // Empty values are valid (mean "inherit")
            if (! isset($settings->{$key}) || (is_string($value) && iconv_strlen($value) < 1)) {
                continue;
            }

            // Type check against CompanySettings casts
            if (isset($casts[$key])) {
                $expected_type = $casts[$key];

                if (in_array($key, CompanySettings::$string_casts)) {
                    $expected_type = 'string';
                } elseif (substr($key, -3) == '_id' || substr($key, -14) == 'number_counter') {
                    $expected_type = 'integer';
                }

                if (! $this->checkEntityAttribute($expected_type, $value)) {
                    return [$key, $expected_type, $value];
                }
            }
        }

        return true;
    }

    /**
     * Type-checks and casts entity settings properties.
     */
    private function checkEntitySettingType($settings): stdClass
    {
        $settings = (object) $settings;
        $casts = CompanySettings::$casts;

        foreach ($settings as $key => $value) {
            if (! isset($casts[$key])) {
                continue;
            }

            $cast_type = $casts[$key];

            if ($cast_type == 'float') {
                $settings->{$key} = floatval($value);
                continue;
            }

            if (substr($key, -3) == '_id' || substr($key, -14) == 'number_counter') {
                if ($this->checkEntityAttribute('integer', $value)) {
                    settype($settings->{$key}, 'string');
                } else {
                    unset($settings->{$key});
                }
                continue;
            }

            if (is_null($value) || (is_string($value) && $value == '')) {
                continue;
            }

            if ($this->checkEntityAttribute($cast_type, $value)) {
                if ($cast_type == 'string' && is_null($value)) {
                    $settings->{$key} = '';
                }
                settype($settings->{$key}, $cast_type);
            } else {
                unset($settings->{$key});
            }
        }

        return $settings;
    }

    /**
     * Type-checks a property value.
     */
    private function checkEntityAttribute(string $key, $value): bool
    {
        switch ($key) {
            case 'int':
            case 'integer':
                return is_numeric($value) && ctype_digit(strval(abs((int) $value)));
            case 'real':
            case 'float':
            case 'double':
                return ! is_string($value) && (is_float($value) || is_numeric(strval($value)));
            case 'string':
                return (is_string($value) && method_exists($value, '__toString')) || is_null($value) || is_string($value);
            case 'bool':
            case 'boolean':
                return is_bool($value) || (int) filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'object':
                return is_object($value);
            case 'array':
                return is_array($value);
            case 'json':
                json_decode($value);
                return json_last_error() == JSON_ERROR_NONE;
            default:
                return false;
        }
    }
}
