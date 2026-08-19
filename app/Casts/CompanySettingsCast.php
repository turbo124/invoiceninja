<?php

namespace App\Casts;

use App\DataMapper\CompanySettings;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;
use ReflectionNamedType;
use ReflectionProperty;
use stdClass;

class CompanySettingsCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): CompanySettings
    {
        return $this->hydrate($this->payload($value));
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $settings = $this->hydrate($this->payload($value));

        $this->synchronizeCachedObject($value, $settings);

        return json_encode(get_object_vars($settings), JSON_THROW_ON_ERROR);
    }

    /**
     * Serialize the complete settings object when its model is converted to an array.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): stdClass
    {
        return (object) get_object_vars($this->hydrate($this->payload($value)));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hydrate(array $payload): CompanySettings
    {
        $settings = new CompanySettings();

        foreach (get_object_vars(CompanySettings::defaults()) as $property => $default) {
            $value = array_key_exists($property, $payload) && $payload[$property] !== null
                ? $payload[$property]
                : $default;

            if ($property === 'translations') {
                $settings->translations = $this->translations($value);

                continue;
            }

            $type = $this->settingType($property);

            if ($type === null) {
                continue;
            }

            $settings->{$property} = $this->castValue(
                $type,
                $value,
                $default,
            );
        }

        return $settings;
    }

    private function castValue(string $type, mixed $value, mixed $default): mixed
    {
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

    private function settingType(string $property): ?string
    {
        if (! property_exists(CompanySettings::class, $property)) {
            return null;
        }

        $property = new ReflectionProperty(CompanySettings::class, $property);
        $type = $property->getType();

        if (! $property->isPublic() || $property->isStatic() || ! $type instanceof ReflectionNamedType) {
            return null;
        }

        return $type->getName();
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

    private function synchronizeCachedObject(mixed $value, CompanySettings $settings): void
    {
        if (! $value instanceof CompanySettings) {
            return;
        }

        foreach (array_keys(get_object_vars($value)) as $property) {
            if (! property_exists(CompanySettings::class, $property)) {
                unset($value->{$property});
            }
        }

        foreach (get_object_vars($settings) as $property => $setting) {
            $value->{$property} = $setting;
        }
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
