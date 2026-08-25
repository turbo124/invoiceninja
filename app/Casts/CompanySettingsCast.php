<?php

namespace App\Casts;

use App\DataMapper\CompanySettings;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;
use stdClass;

class CompanySettingsCast implements CastsAttributes, SerializesCastableAttributes
{
    public bool $withoutObjectCaching = false;

    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): CompanySettings
    {
        return $this->normalize($value);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $this->withoutObjectCaching = ! $value instanceof CompanySettings;
        $settings = $this->normalize($value);

        return json_encode($this->storagePayload($settings), JSON_THROW_ON_ERROR);
    }

    /**
     * Serialize the complete settings object when its model is converted to an array.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): stdClass
    {
        return (object) $this->storagePayload($this->normalize($value));
    }

    public function normalize(mixed $value): CompanySettings
    {
        if ($value instanceof CompanySettings) {
            return $value;
        }

        return $this->hydrate($this->payload($value));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hydrate(array $payload): CompanySettings
    {
        $settings = new CompanySettings();
        $defaults = CompanySettings::defaults();

        foreach (get_object_vars($defaults) as $property => $default) {
            $value = array_key_exists($property, $payload) && $payload[$property] !== null
                ? $payload[$property]
                : $default;

            if ($property === 'translations') {
                $settings->translations = $this->translations($value);

                continue;
            }

            $settings->{$property} = $this->castValue(
                $property,
                $value,
                $default,
            );
        }

        return $settings;
    }

    private function castValue(string $property, mixed $value, mixed $default): mixed
    {
        if (in_array($property, CompanySettings::NUMERIC_STRING_CASTS, true)) {
            return $this->numericString($value, (string) $default);
        }

        $type = gettype($default);

        if ($type !== 'object') {
            return CompanySettings::castAttribute($type, $value);
        }

        if (is_object($value)) {
            return $value;
        }

        if (is_array($value)) {
            return (object) $value;
        }

        if (is_string($value) && strlen($value) >= 1) {
            try {
                $decoded = json_decode($value, false, 512, JSON_THROW_ON_ERROR);

                if (is_object($decoded)) {
                    return $decoded;
                }
            } catch (JsonException) {
                return is_object($default) ? $default : new stdClass();
            }
        }

        return is_object($default) ? $default : new stdClass();
    }

    private function numericString(mixed $value, string $default): string
    {
        if ($value === '') {
            return '';
        }

        if (is_int($value) && $value >= 0) {
            return (string) $value;
        }

        if (is_float($value) && is_finite($value) && $value >= 0 && floor($value) === $value) {
            return number_format($value, 0, '.', '');
        }

        if (! is_string($value) || ! ctype_digit($value)) {
            return $default;
        }

        $value = ltrim($value, '0');

        return $value === '' ? '0' : $value;
    }

    private function translations(mixed $value): stdClass
    {
        if (is_string($value) && strlen($value) >= 1) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $value = [];
            }
        }

        if (! is_array($value) && ! is_object($value)) {
            return new stdClass();
        }

        $translations = new stdClass();

        foreach ((array) $value as $key => $translation) {
            if (! is_scalar($translation) || strlen((string) $translation) < 1) {
                continue;
            }

            $translations->{$key} = (string) $translation;
        }

        return $translations;
    }

    /**
     * @return array<string, mixed>
     */
    private function storagePayload(CompanySettings $settings): array
    {
        $payload = [];

        foreach (array_keys(CompanySettings::$casts) as $property) {
            $payload[$property] = $settings->{$property};
        }

        $payload['translations'] = $settings->translations;

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(mixed $value): array
    {
        if ($value === null || $value === '' || $value === 'null') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return get_object_vars($value);
        }

        if (! is_string($value)) {
            return [];
        }

        try {
            $payload = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }
}
