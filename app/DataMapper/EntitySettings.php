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

namespace App\DataMapper;

use stdClass;

/**
 * EntitySettings.
 *
 * Entity settings extend the settings cascade to the entity level (Invoice, Quote, Credit, etc.)
 *
 * The cascade is: Company -> Group -> Client -> Entity
 *
 * Only a whitelisted subset of settings are permitted at the entity level.
 * If no entity setting is specified, the client/group/company setting is used via fallthrough.
 */
class EntitySettings extends BaseSettings
{
    /**
     * The whitelist of settings permitted at entity level.
     * These are the only settings that can be overridden on individual invoices, quotes, credits, etc.
     */
    public static array $entity_settings = [
        // Gateway
        'company_gateway_ids',

        // Reminders control
        'send_reminders',
        'auto_email_invoice',
        'entity_send_time',

        // Email style
        'email_style',
        'email_style_custom',

        // Email subjects
        'email_subject_invoice',
        'email_subject_quote',
        'email_subject_credit',
        'email_subject_purchase_order',
        'email_subject_reminder1',
        'email_subject_reminder2',
        'email_subject_reminder3',
        'email_subject_reminder_endless',
        'email_subject_custom1',
        'email_subject_custom2',
        'email_subject_custom3',
        'email_subject_payment_failed',

        // Email templates
        'email_template_invoice',
        'email_template_quote',
        'email_template_credit',
        'email_template_purchase_order',
        'email_template_reminder1',
        'email_template_reminder2',
        'email_template_reminder3',
        'email_template_reminder_endless',
        'email_template_custom1',
        'email_template_custom2',
        'email_template_custom3',
        'email_template_payment_failed',

        // Reminder enable flags
        'enable_reminder1',
        'enable_reminder2',
        'enable_reminder3',
        'enable_reminder_endless',

        // Reminder scheduling
        'num_days_reminder1',
        'num_days_reminder2',
        'num_days_reminder3',
        'schedule_reminder1',
        'schedule_reminder2',
        'schedule_reminder3',

        // Late fees
        'late_fee_amount1',
        'late_fee_amount2',
        'late_fee_amount3',
        'late_fee_percent1',
        'late_fee_percent2',
        'late_fee_percent3',
        'endless_reminder_frequency_id',
        'late_fee_endless_amount',
        'late_fee_endless_percent',

        // Quote reminders
        'email_quote_template_reminder1',
        'email_quote_subject_reminder1',
        'enable_quote_reminder1',
        'quote_num_days_reminder1',
        'quote_schedule_reminder1',
        'quote_late_fee_amount1',
        'quote_late_fee_percent1',

        // Payment flow
        'payment_flow',
        'email_subject_payment_failed',
        'email_template_payment_failed',
    ];

    /**
     * Check if a setting key is permitted at the entity level.
     */
    public static function isEntitySetting(string $key): bool
    {
        return in_array($key, self::$entity_settings);
    }

    /**
     * Merge client-resolved settings with entity-level overrides.
     *
     * Uses the same empty-string-means-inherit convention as ClientSettings::buildClientSettings().
     *
     * @param object $client_settings The fully resolved client settings (already merged from company/group/client)
     * @param object|array|null $entity_settings The entity-level settings overrides
     * @return object Merged settings object
     */
    public static function buildEntitySettings($client_settings, $entity_settings): object
    {
        if (! $entity_settings) {
            return $client_settings;
        }

        if (is_array($entity_settings)) {
            $entity_settings = (object) $entity_settings;
        }

        $merged = clone $client_settings;

        foreach ($entity_settings as $key => $value) {
            if (! self::isEntitySetting($key)) {
                continue;
            }

            // Empty string means "inherit from parent" — skip it
            if (is_string($value) && iconv_strlen($value) < 1) {
                continue;
            }

            $merged->{$key} = $value;
        }

        return $merged;
    }

    /**
     * Filter a settings object to only contain whitelisted entity settings.
     * Removes any properties not in the entity settings whitelist.
     *
     * @param object|array $settings
     * @return object Filtered settings containing only whitelisted keys
     */
    public static function filterEntitySettings($settings): object
    {
        if (is_array($settings)) {
            $settings = (object) $settings;
        }

        $filtered = new stdClass();

        foreach ($settings as $key => $value) {
            if (! self::isEntitySetting($key)) {
                continue;
            }

            // Empty string means "inherit from parent" — don't store it
            if (is_string($value) && iconv_strlen($value) < 1) {
                continue;
            }

            $filtered->{$key} = $value;
        }

        return $filtered;
    }
}
