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
use App\Jobs\Company\CompanyTaxRate;
use App\Models\Company;
use stdClass;

/**
 * Class CompanySettingsSaver.
 *
 * Whilst it may appear that this CompanySettingsSaver and ClientGroupSettingsSaver
 * could be duplicates, they are not.
 *
 * Each requires their own approach to saving and attempts to
 * merge the two code paths should be avoided.
 */
trait CompanySettingsSaver
{
    private array $string_ids = [
        'payment_refund_design_id',
        'payment_receipt_design_id',
        'delivery_note_design_id',
        'statement_design_id',
        'besr_id',
        'gmail_sending_user_id',
    ];

    /**
     * @param  array<string, mixed>|object|string  $settings
     */
    public function saveSettings(mixed $settings, Company $entity): void
    {
        $settings = $this->settingsPayload($settings);

        if ($settings === []) {
            return;
        }

        foreach (CompanySettings::$protected_fields as $field) {
            unset($settings[$field]);
        }

        $originalSettings = $this->settingsPayload($entity->getOriginal('settings'));
        $entity->settings = array_replace($originalSettings, $settings);
        $companySettings = $entity->settings;
        $refreshTaxRates = $this->shouldRefreshTaxRates($entity, $originalSettings, $companySettings);

        $entity->save();

        if ($refreshTaxRates) {
            CompanyTaxRate::dispatch($entity);
        }
    }

    /**
     * Rollback copy of the settings persistence path used before CompanySettingsCast.
     */
    public function backupSaveSettings(mixed $settings, Company $entity): void
    {
        if (! $settings) {
            return;
        }

        foreach (CompanySettings::$protected_fields as $field) {
            unset($settings[$field]);
        }

        $settings = $this->backupCheckSettingType($settings);

        $companySettings = CompanySettings::defaults();

        foreach ($settings as $key => $value) {
            if (is_null($settings->{$key})) {
                $companySettings->{$key} = '';
            } else {
                $companySettings->{$key} = $value;
            }
        }

        if (property_exists($settings, 'translations')) {
            foreach ($settings->translations as $key => $value) {
                if (is_array($settings->translations)) {
                    if (is_null($settings->translations[$key])) {
                        $settings->translations[$key] = '';
                    }
                } elseif (is_object($settings->translations)) {
                    if (is_null($settings->translations->{$key})) {
                        $settings->translations->{$key} = '';
                    }
                }
            }

            $companySettings->translations = $settings->translations;
        }

        $entity->settings = $companySettings;

        if ($entity->calculate_taxes
            && $companySettings->country_id == '840'
            && array_key_exists('settings', $entity->getDirty())
            && ! $entity->account->isFreeHostedClient()) {
            $oldSettings = $entity->getOriginal()['settings'];

            if ($oldSettings->postal_code != $companySettings->postal_code) {
                CompanyTaxRate::dispatch($entity);
            }
        } elseif ($entity->calculate_taxes
            && $companySettings->country_id == '840'
            && array_key_exists('calculate_taxes', $entity->getDirty())
            && $entity->getOriginal('calculate_taxes') == 0
            && ! $entity->account->isFreeHostedClient()) {
            CompanyTaxRate::dispatch($entity);
        }

        $entity->save();
    }

    /**
     * Used for custom validation of inbound
     * settings request.
     *
     * Returns an array of errors, or boolean TRUE
     * on successful validation
     * @param  array $settings The request() settings array
     * @return array|bool      Array on failure, boolean TRUE on success
     */
    public function validateSettings($settings)
    {
        $settings = (object) $settings;

        $casts = CompanySettings::$casts;

        ksort($casts);

        foreach ($casts as $key => $value) {
            if (in_array($key, CompanySettings::$string_casts)) {
                $value = 'string';

                if (! property_exists($settings, $key)) {
                    continue;
                } elseif (! $this->checkAttribute($value, $settings->{$key})) {
                    return [$key, $value, $settings->{$key}];
                }

                continue;
            }
            /*Separate loop if it is a _id field which is an integer cast as a string*/ elseif (substr($key, -3) == '_id' || substr($key, -14) == 'number_counter') {
                $value = 'integer';

                if (in_array($key, $this->string_ids)) {
                    // if ($key == 'besr_id') {
                    $value = 'string';
                }

                if (! property_exists($settings, $key)) {
                    continue;
                } elseif (! $this->checkAttribute($value, $settings->{$key})) {
                    return [$key, $value, $settings->{$key}];
                }

                continue;
            } elseif ($key == 'pdf_variables') {
                continue;
            }

            /* Handles unset settings or blank strings */
            if (! property_exists($settings, $key) || is_null($settings->{$key}) || ! isset($settings->{$key}) || $settings->{$key} == '') {
                continue;
            }

            /*Catch all filter */
            if (! $this->checkAttribute($value, $settings->{$key})) {
                return [$key, $value, $settings->{$key}];
            }
        }

        return true;
    }

    /**
     * Type checks a object property.
     * @param  string $key   The type
     * @param  string $value The object property
     * @return bool        TRUE if the property is the expected type
     */
    private function checkAttribute($key, $value): bool
    {
        switch ($key) {
            case 'int':
            case 'integer':
                return ctype_digit(strval(abs((int) $value)));
            case 'real':
            case 'float':
            case 'double':
                return ! is_string($value) && (is_float($value) || is_numeric(strval($value)));
            case 'string':
                return (is_string($value) && method_exists($value, '__toString')) || is_null($value) || is_string($value);
            case 'bool':
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null;
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

    private function backupCheckSettingType(mixed $settings): stdClass
    {
        $settings = (object) $settings;
        $casts = CompanySettings::$casts;

        foreach ($casts as $key => $value) {
            if (in_array($key, CompanySettings::$string_casts)) {
                $value = 'string';

                if (! property_exists($settings, $key)) {
                    continue;
                } elseif ($this->backupCheckAttribute($value, $settings->{$key})) {
                    if (substr($key, -3) == '_id') {
                        settype($settings->{$key}, 'string');
                    } else {
                        settype($settings->{$key}, $value);
                    }
                } else {
                    unset($settings->{$key});
                }

                continue;
            }

            if (substr($key, -3) == '_id' || substr($key, -14) == 'number_counter') {
                $value = 'integer';

                if (in_array($key, $this->string_ids)) {
                    $value = 'string';
                }

                if (! property_exists($settings, $key)) {
                    continue;
                } elseif ($this->backupCheckAttribute($value, $settings->{$key})) {
                    if (substr($key, -3) == '_id') {
                        settype($settings->{$key}, 'string');
                    } else {
                        settype($settings->{$key}, $value);
                    }
                } else {
                    unset($settings->{$key});
                }

                continue;
            } elseif ($key == 'pdf_variables') {
                settype($settings->{$key}, 'object');
            }

            if ($value == 'float' && property_exists($settings, $key)) {
                $settings->{$key} = floatval($settings->{$key});
            }

            if (! property_exists($settings, $key) || is_null($settings->{$key}) || ! isset($settings->{$key}) || $settings->{$key} == '') {
                continue;
            }

            if ($this->backupCheckAttribute($value, $settings->{$key})) {
                if ($value == 'string' && is_null($settings->{$key})) {
                    $settings->{$key} = '';
                }

                settype($settings->{$key}, $value);
            } else {
                unset($settings->{$key});
            }
        }

        return $settings;
    }

    private function backupCheckAttribute(mixed $key, mixed $value): bool
    {
        switch ($key) {
            case 'int':
            case 'integer':
                return ctype_digit(strval(abs((int) $value)));
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

    private function getAccountFromEntity($entity)
    {
        if ($entity instanceof Company) {
            return $entity->account;
        }

        return $entity->company->account;
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(mixed $settings): array
    {
        if (is_array($settings)) {
            return $settings;
        }

        if (is_object($settings)) {
            return get_object_vars($settings);
        }

        if (! is_string($settings)) {
            return [];
        }

        $settings = json_decode($settings, true);

        return is_array($settings) ? $settings : [];
    }

    /**
     * @param  array<string, mixed>  $originalSettings
     */
    private function shouldRefreshTaxRates(
        Company $company,
        array $originalSettings,
        CompanySettings $settings,
    ): bool {
        if (! $company->calculate_taxes
            || $settings->country_id !== '840'
            || $company->account->isFreeHostedClient()) {
            return false;
        }

        $postalCodeChanged = $company->isDirty('settings')
            && ($originalSettings['postal_code'] ?? '') !== $settings->postal_code;
        $taxCalculationEnabled = $company->isDirty('calculate_taxes')
            && ! (bool) $company->getOriginal('calculate_taxes');

        return $postalCodeChanged || $taxCalculationEnabled;
    }
}
