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

use App\DataMapper\EntitySettings;

/**
 * Trait EntitySettingsResolver.
 *
 * Provides entity-level settings resolution for Invoice, Quote, Credit, RecurringInvoice, PurchaseOrder.
 *
 * The cascade is: Entity -> Client/Vendor -> Group -> Company -> Defaults
 *
 * Only whitelisted settings (defined in EntitySettings::$entity_settings) can be overridden at entity level.
 * For non-whitelisted settings, the call falls through directly to the client/vendor.
 *
 * Entity settings are stored within the backup column as backup->settings.
 */
trait EntitySettingsResolver
{
    /**
     * Resolve a single setting value with entity-level override support.
     *
     * Checks entity settings first (if whitelisted), then delegates to the
     * parent entity (client or vendor) for the standard cascade resolution.
     */
    public function getSetting(string $setting): mixed
    {
        $entity_settings = $this->backup?->settings;

        // Check entity-level settings first, but only for whitelisted settings
        if ($entity_settings
            && EntitySettings::isEntitySetting($setting)
            && property_exists($entity_settings, $setting)
            && isset($entity_settings->{$setting})) {

            $value = $entity_settings->{$setting};

            if (is_string($value) && iconv_strlen($value) >= 1) {
                return $value;
            } elseif (is_bool($value)) {
                return $value;
            } elseif (is_int($value)) {
                return $value;
            } elseif (is_float($value)) {
                return $value;
            }
        }

        // Fall through to client/vendor cascade
        return $this->getSettingsParent()->getSetting($setting);
    }

    /**
     * Get fully merged settings including entity-level overrides.
     *
     * Returns a complete settings object with the full cascade applied:
     * Company -> Group -> Client -> Entity
     */
    public function getMergedSettings(): object
    {
        $parent_settings = $this->getSettingsParent()->getMergedSettings();

        $entity_settings = $this->backup?->settings;

        if ($entity_settings) {
            return EntitySettings::buildEntitySettings($parent_settings, $entity_settings);
        }

        return $parent_settings;
    }

    /**
     * Get the entity that owns the setting at the source level.
     *
     * Returns which entity (self, Client, GroupSetting, or Company) provides
     * the resolved value for a given setting.
     */
    public function getSettingEntity(string $setting)
    {
        $entity_settings = $this->backup?->settings;

        // Check entity level first
        if ($entity_settings
            && EntitySettings::isEntitySetting($setting)
            && property_exists($entity_settings, $setting)
            && isset($entity_settings->{$setting})) {

            $value = $entity_settings->{$setting};

            if ((is_string($value) && iconv_strlen($value) >= 1)
                || is_bool($value) || is_int($value) || is_float($value)) {
                return $this;
            }
        }

        // Delegate to parent for source resolution
        $parent = $this->getSettingsParent();

        if (method_exists($parent, 'getSettingEntity')) {
            return $parent->getSettingEntity($setting);
        }

        return $parent;
    }

    /**
     * Get the parent entity for settings resolution.
     *
     * For client-based entities (Invoice, Quote, Credit, RecurringInvoice): returns client
     * For vendor-based entities (PurchaseOrder): returns vendor
     */
    private function getSettingsParent()
    {
        if ($this instanceof \App\Models\PurchaseOrder) {
            return $this->vendor;
        }

        return $this->client;
    }
}
