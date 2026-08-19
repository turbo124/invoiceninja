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

class ClientGroupSettingsCast implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * Settings that are owned by the company settings object but cannot be overridden by clients or groups.
     *
     * @var array<string, true>
     */
    private const EXCLUDED_PROPERTIES = [
        'translations' => true,
        'pdf_variables' => true,
    ];

    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): stdClass
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

        $payload = get_object_vars($settings);

        return json_encode($payload === [] ? new stdClass() : $payload, JSON_THROW_ON_ERROR);
    }

    /**
     * Serialize the sparse settings object when its model is converted to an array.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): stdClass
    {
        return $this->hydrate($this->payload($value));
    }

    private function synchronizeCachedObject(mixed $value, stdClass $settings): void
    {
        if (! $value instanceof stdClass) {
            return;
        }

        foreach (array_keys(get_object_vars($value)) as $property) {
            unset($value->{$property});
        }

        foreach (get_object_vars($settings) as $property => $setting) {
            $value->{$property} = $setting;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hydrate(array $payload): stdClass
    {
        $settings = new stdClass();

        foreach ($payload as $property => $value) {
            $type = $this->overrideSettingType($property);

            if ($type === null || $value === null || $value === '') {
                continue;
            }

            $settings->{$property} = CompanySettings::castAttribute(
                $type,
                $value,
            );
        }

        return $settings;
    }

    private function overrideSettingType(string $property): ?string
    {
        if (isset(self::EXCLUDED_PROPERTIES[$property])
            || in_array($property, CompanySettings::$protected_fields, true)
            || ! property_exists(CompanySettings::class, $property)) {
            return null;
        }

        $property = new ReflectionProperty(CompanySettings::class, $property);
        $type = $property->getType();

        if (! $property->isPublic() || $property->isStatic() || ! $type instanceof ReflectionNamedType) {
            return null;
        }

        return $type->getName();
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
