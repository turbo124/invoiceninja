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

namespace App\DataProviders;

/**
 * Static catalogue of searchable settings destinations.
 *
 * Each entry describes a navigable settings route together with the metadata
 * required to render and gate it inside the global search palette:
 *
 *  - path:       the front-end route to navigate to. Every path resolves to a
 *                real, index-rendering route in the admin UI — list routes that
 *                only expose create/edit children (e.g. tax_rates) point at the
 *                parent settings page where the list actually renders.
 *  - section:    translation key used as the bold heading (the parent group);
 *                null resolves to the generic "settings" heading.
 *  - label:      translation key used as the result name (the leaf).
 *  - keywords:   space separated, untranslated synonyms that widen matching
 *                (e.g. "smtp mailgun postmark" for the email settings page).
 *  - permission: gate required to surface the entry. SettingsSearchMap::ADMIN
 *                requires an admin/owner; null exposes the entry to every user;
 *                any other string is passed to User::hasPermission().
 *  - scope:      deployment scope, one of SettingsSearchMap::SCOPE_*.
 *
 * @phpstan-type SettingsSearchEntry array{path: string, section: string|null, label: string, keywords: string, permission: string|null, scope: string}
 */
class SettingsSearchMap
{
    public const ADMIN = 'admin';

    public const SCOPE_ALL = 'all';

    public const SCOPE_HOSTED = 'hosted';

    public const SCOPE_SELFHOST = 'selfhost';

    /**
     * The full settings catalogue.
     *
     * @return array<int, SettingsSearchEntry>
     */
    public static function all(): array
    {
        return [
            // User details — available to every authenticated user (own profile).
            ['path' => '/settings/user_details', 'section' => null, 'label' => 'user_details', 'keywords' => 'profile account me', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details', 'section' => 'user_details', 'label' => 'details', 'keywords' => 'profile name email avatar', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/password', 'section' => 'user_details', 'label' => 'password', 'keywords' => 'security login credentials change password', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/connect', 'section' => 'user_details', 'label' => 'connect', 'keywords' => 'oauth google microsoft apple gmail social login link', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/accent_color', 'section' => 'user_details', 'label' => 'accent_color', 'keywords' => 'theme colour color appearance', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/notifications', 'section' => 'user_details', 'label' => 'notifications', 'keywords' => 'alerts emails notify', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/enable_two_factor', 'section' => 'user_details', 'label' => 'enable_two_factor', 'keywords' => '2fa mfa totp authenticator security otp', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/custom_fields', 'section' => 'user_details', 'label' => 'custom_fields', 'keywords' => 'extra fields metadata', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'preferences', 'keywords' => 'options settings', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'show_pdf_preview', 'keywords' => 'pdf preview invoice quote', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'show_document_preview', 'keywords' => 'document preview docuninja', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'dark_mode', 'keywords' => 'theme appearance light night', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'number_precision', 'keywords' => 'decimals rounding amounts', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'show_table_footer', 'keywords' => 'tables totals footer', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'persist_table_filters', 'keywords' => 'filters remember table', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'react_notification_link', 'keywords' => 'react links notifications', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'auto_expand_product_table_notes', 'keywords' => 'product notes table expand', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'enable_public_notifications', 'keywords' => 'public notifications', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'use_system_fonts', 'keywords' => 'fonts typography', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'use_legacy_editor', 'keywords' => 'editor markdown html', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'hide_peppol_sent_status', 'keywords' => 'peppol sent status einvoice', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/preferences', 'section' => 'user_details', 'label' => 'status_color_theme', 'keywords' => 'status colours colors theme badges', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/notifications', 'section' => 'user_details', 'label' => 'login_notification', 'keywords' => 'login alert security email', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/notifications', 'section' => 'user_details', 'label' => 'task_assigned_notification', 'keywords' => 'task assigned alert email', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/notifications', 'section' => 'user_details', 'label' => 'disable_recurring_payment_notification', 'keywords' => 'recurring payment email alert', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/notifications', 'section' => 'user_details', 'label' => 'enable_e_invoice_received_notification', 'keywords' => 'e-invoice received peppol alert', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/enable_two_factor', 'section' => 'user_details', 'label' => 'passkey', 'keywords' => 'webauthn fido security key 2fa', 'permission' => null, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/user_details/keyboard_shortcuts', 'section' => 'user_details', 'label' => 'keyboard_shortcuts', 'keywords' => 'hotkeys key bindings shortcuts search', 'permission' => null, 'scope' => self::SCOPE_ALL],

            // Company details. The "Details" tab renders at the company_details index.
            ['path' => '/settings/company_details', 'section' => null, 'label' => 'company_details', 'keywords' => 'business organisation organization details name id number website', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details', 'section' => 'company_details', 'label' => 'details', 'keywords' => 'name id number website organisation organization', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/address', 'section' => 'company_details', 'label' => 'address', 'keywords' => 'street city postcode state country location', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/logo', 'section' => 'company_details', 'label' => 'logo', 'keywords' => 'branding image picture', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/defaults', 'section' => 'company_details', 'label' => 'defaults', 'keywords' => 'default terms footer notes', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/documents', 'section' => 'company_details', 'label' => 'documents', 'keywords' => 'files attachments uploads', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/company_details/custom_fields', 'section' => 'company_details', 'label' => 'custom_fields', 'keywords' => 'extra fields metadata', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Localization.
            ['path' => '/settings/localization', 'section' => null, 'label' => 'localization', 'keywords' => 'language currency timezone date format region locale number first day of week', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/localization', 'section' => 'localization', 'label' => 'settings', 'keywords' => 'language currency timezone date format', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/localization/custom_labels', 'section' => 'localization', 'label' => 'custom_labels', 'keywords' => 'translations rename labels wording', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Payments.
            ['path' => '/settings/online_payments', 'section' => null, 'label' => 'online_payments', 'keywords' => 'gateway stripe paypal gocardless mollie checkout braintree authorize razorpay payment methods payment_settings', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/online_payments', 'section' => 'online_payments', 'label' => 'payment_settings', 'keywords' => 'gateways terms processors', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/online_payments', 'section' => 'online_payments', 'label' => 'payment_type_stripe', 'keywords' => 'stripe connect ach card', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/online_payments', 'section' => 'online_payments', 'label' => 'paypal', 'keywords' => 'paypal express checkout', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/online_payments', 'section' => 'online_payments', 'label' => 'gocardless', 'keywords' => 'direct debit gocardless', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/online_payments', 'section' => 'online_payments', 'label' => 'checkout_com', 'keywords' => 'checkout.com card gateway', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/online_payments', 'section' => 'online_payments', 'label' => 'payment_type_Alipay', 'keywords' => 'alipay stripe china', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/online_payments', 'section' => 'online_payments', 'label' => 'payment_type_Sofort', 'keywords' => 'sofort klarna stripe', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/gateways/create', 'section' => 'online_payments', 'label' => 'add_gateway', 'keywords' => 'gateway stripe paypal mollie braintree razorpay connect processor new', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/payment_terms', 'section' => 'online_payments', 'label' => 'payment_terms', 'keywords' => 'net due days terms list', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/payment_terms/create', 'section' => 'online_payments', 'label' => 'new_payment_term', 'keywords' => 'add net due days term', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/payment_terms/create', 'section' => 'online_payments', 'label' => 'create_payment_term', 'keywords' => 'add net due days term', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Taxes. The tax rates list renders inside the tax settings page.
            ['path' => '/settings/tax_settings', 'section' => null, 'label' => 'tax_settings', 'keywords' => 'vat gst sales tax rates inclusive', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tax_settings', 'section' => 'tax_settings', 'label' => 'tax_rates', 'keywords' => 'vat gst percentage rate', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tax_rates/create', 'section' => 'tax_settings', 'label' => 'new_tax_rate', 'keywords' => 'add vat gst rate', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tax_rates/create', 'section' => 'tax_settings', 'label' => 'create_tax_rate', 'keywords' => 'add vat gst rate', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tax_settings', 'section' => 'tax_settings', 'label' => 'calculate_taxes', 'keywords' => 'automated tax avalara stripe tax', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tax_settings', 'section' => 'tax_settings', 'label' => 'inclusive_taxes', 'keywords' => 'tax inclusive vat gst included', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tax_settings', 'section' => 'tax_settings', 'label' => 'default_tax_rate', 'keywords' => 'default line item tax rate', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tax_settings', 'section' => 'tax_settings', 'label' => 'invoice_tax_rates', 'keywords' => 'invoice level tax rates', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tax_settings', 'section' => 'tax_settings', 'label' => 'line_item_tax_rates', 'keywords' => 'line item product tax rates', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tax_settings', 'section' => 'tax_settings', 'label' => 'expense_tax_rates', 'keywords' => 'expense tax rates', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Products.
            ['path' => '/settings/product_settings', 'section' => null, 'label' => 'product_settings', 'keywords' => 'items inventory stock products', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/product_settings', 'section' => 'product_settings', 'label' => 'track_inventory', 'keywords' => 'stock quantity inventory tracking', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/product_settings', 'section' => 'product_settings', 'label' => 'stock_notifications', 'keywords' => 'low stock alert inventory', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/product_settings', 'section' => 'product_settings', 'label' => 'notification_threshold', 'keywords' => 'low stock threshold quantity', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/product_settings', 'section' => 'product_settings', 'label' => 'show_product_discount', 'keywords' => 'discount line item product', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/product_settings', 'section' => 'product_settings', 'label' => 'show_product_cost', 'keywords' => 'cost margin product', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/product_settings', 'section' => 'product_settings', 'label' => 'show_product_quantity', 'keywords' => 'quantity product line item', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/product_settings', 'section' => 'product_settings', 'label' => 'default_quantity', 'keywords' => 'default product quantity', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/product_settings', 'section' => 'product_settings', 'label' => 'fill_products', 'keywords' => 'auto fill products autocomplete', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/product_settings', 'section' => 'product_settings', 'label' => 'update_products', 'keywords' => 'auto update products', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/product_settings', 'section' => 'product_settings', 'label' => 'convert_products', 'keywords' => 'convert products quote invoice', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Tasks. The task statuses list renders inside the task settings page.
            ['path' => '/settings/task_settings', 'section' => null, 'label' => 'task_settings', 'keywords' => 'time tracking timer', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'task_statuses', 'keywords' => 'kanban columns workflow status', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_statuses/create', 'section' => 'task_settings', 'label' => 'new_task_status', 'keywords' => 'add kanban column status', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'lock_invoiced_tasks', 'keywords' => 'timer billing lock', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'auto_start_tasks', 'keywords' => 'timer start tracking', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'show_task_end_date', 'keywords' => 'task end date due', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'show_task_item_description', 'keywords' => 'task item description', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'allow_billable_task_items', 'keywords' => 'billable task items', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'show_tasks_table', 'keywords' => 'tasks table invoice pdf', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'invoice_task_datelog', 'keywords' => 'task date log invoice', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'invoice_task_timelog', 'keywords' => 'task time log invoice timer', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'invoice_task_hours', 'keywords' => 'task hours invoice', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'invoice_task_project', 'keywords' => 'task project invoice', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'invoice_task_item_description', 'keywords' => 'task description invoice line', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'add_documents_to_invoice', 'keywords' => 'task documents attach invoice', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/task_settings', 'section' => 'task_settings', 'label' => 'task_round_to_nearest', 'keywords' => 'round task time billing', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Expenses. The expense categories list renders inside the expense settings page.
            ['path' => '/settings/expense_settings', 'section' => null, 'label' => 'expense_settings', 'keywords' => 'vendor bills costs', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'expense_categories', 'keywords' => 'categories grouping', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_categories/create', 'section' => 'expense_settings', 'label' => 'new_expense_category', 'keywords' => 'add category', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'expense_mailbox', 'keywords' => 'inbound email receipt scan forward', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'convert_currency', 'keywords' => 'exchange rate foreign currency', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'should_be_invoiced', 'keywords' => 'expense invoice billable', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'mark_paid', 'keywords' => 'expense paid mark', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'notify_vendor_when_paid', 'keywords' => 'vendor paid notification email', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'add_documents_to_invoice', 'keywords' => 'expense documents attach invoice', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'expense_mailbox_active', 'keywords' => 'expense mailbox inbound email', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'inbound_mailbox_allow_company_users', 'keywords' => 'inbound mailbox company senders', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'inbound_mailbox_allow_vendors', 'keywords' => 'inbound mailbox vendor senders', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'inbound_mailbox_allow_clients', 'keywords' => 'inbound mailbox client senders', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'inbound_mailbox_whitelist', 'keywords' => 'inbound allow list whitelist email', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'inbound_mailbox_blacklist', 'keywords' => 'inbound banned list blacklist email', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'inbound_mailbox_allow_unknown', 'keywords' => 'inbound allow all senders unknown', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'enter_taxes', 'keywords' => 'expense enter taxes', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/expense_settings', 'section' => 'expense_settings', 'label' => 'inclusive_taxes', 'keywords' => 'expense inclusive taxes', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Tags.
            ['path' => '/settings/tags', 'section' => null, 'label' => 'tags', 'keywords' => 'labels keywords categorise task project tags', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tags', 'section' => 'tags', 'label' => 'task_tags', 'keywords' => 'labels keywords categorise tags', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tags/create', 'section' => 'tags', 'label' => 'new_tag', 'keywords' => 'add label keyword', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/tags/projects', 'section' => 'tags', 'label' => 'project_tags', 'keywords' => 'labels keywords project tags', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Workflow.
            ['path' => '/settings/workflow_settings', 'section' => null, 'label' => 'workflow_settings', 'keywords' => 'automation auto bill email send', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/workflow_settings', 'section' => 'workflow_settings', 'label' => 'auto_email_invoice', 'keywords' => 'send invoice automatically email', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/workflow_settings', 'section' => 'workflow_settings', 'label' => 'lock_invoices', 'keywords' => 'prevent edit sent paid', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/workflow_settings', 'section' => 'workflow_settings', 'label' => 'auto_convert_quote', 'keywords' => 'quote to invoice automation', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/workflow_settings', 'section' => 'workflow_settings', 'label' => 'stop_on_unpaid', 'keywords' => 'stop recurring unpaid invoice', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/workflow_settings', 'section' => 'workflow_settings', 'label' => 'auto_archive_invoice', 'keywords' => 'archive sent paid invoice', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/workflow_settings', 'section' => 'workflow_settings', 'label' => 'auto_archive_invoice_cancelled', 'keywords' => 'archive cancelled invoice', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/workflow_settings', 'section' => 'workflow_settings', 'label' => 'auto_archive_quote', 'keywords' => 'archive quote sent accepted', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/workflow_settings', 'section' => 'workflow_settings', 'label' => 'use_quote_terms', 'keywords' => 'quote terms invoice workflow', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Account management.
            ['path' => '/settings/account_management', 'section' => null, 'label' => 'account_management', 'keywords' => 'subscription plan billing account', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management', 'section' => 'account_management', 'label' => 'plan', 'keywords' => 'subscription upgrade downgrade pricing', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/overview', 'section' => 'account_management', 'label' => 'overview', 'keywords' => 'plan usage summary', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/users', 'section' => 'account_management', 'label' => 'users', 'keywords' => 'account owners team', 'permission' => self::ADMIN, 'scope' => self::SCOPE_HOSTED],
            ['path' => '/settings/account_management/billing_history', 'section' => 'account_management', 'label' => 'billing_history', 'keywords' => 'invoices receipts charges subscription payments', 'permission' => self::ADMIN, 'scope' => self::SCOPE_HOSTED],
            ['path' => '/settings/account_management/enabled_modules', 'section' => 'account_management', 'label' => 'enabled_modules', 'keywords' => 'features toggle modules', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/integrations', 'section' => 'account_management', 'label' => 'integrations', 'keywords' => 'qbo zapier slack api sync connect third party accounting', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/integrations', 'section' => 'account_management', 'label' => 'quickbooks', 'keywords' => 'qbo intuit connect sync accounting online integration', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/integrations', 'section' => 'account_management', 'label' => 'zapier', 'keywords' => 'automation connect apps workflow', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/integrations', 'section' => 'account_management', 'label' => 'slack_incoming_webhooks', 'keywords' => 'slack webhook notifications payments', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/integrations', 'section' => 'account_management', 'label' => 'api_docs', 'keywords' => 'api documentation developer reference swagger', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/security_settings', 'section' => 'account_management', 'label' => 'security_settings', 'keywords' => 'password 2fa sessions timeout security', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/account_management/referral_program', 'section' => 'account_management', 'label' => 'referral_program', 'keywords' => 'affiliate rewards referrals invite', 'permission' => self::ADMIN, 'scope' => self::SCOPE_HOSTED],
            ['path' => '/settings/account_management/danger_zone', 'section' => 'account_management', 'label' => 'danger_zone', 'keywords' => 'delete purge cancel account close', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Backup / restore / import / export. Backup renders at the backup_restore index.
            ['path' => '/settings/backup_restore', 'section' => null, 'label' => 'backup_restore', 'keywords' => 'export import json archive backup download', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/backup_restore', 'section' => 'backup_restore', 'label' => 'backup', 'keywords' => 'export download json archive company', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/backup_restore/restore', 'section' => 'backup_restore', 'label' => 'restore', 'keywords' => 'import recover upload', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/import_export', 'section' => null, 'label' => 'import_export', 'keywords' => 'csv migration upload download', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/import_export', 'section' => 'import_export', 'label' => 'import', 'keywords' => 'csv upload migration data', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/import_export', 'section' => 'import_export', 'label' => 'export', 'keywords' => 'csv download data', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/import_export', 'section' => 'import_export', 'label' => 'quickbooks', 'keywords' => 'qbo intuit import migrate backup desktop', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/import_export', 'section' => 'import_export', 'label' => 'freshbooks', 'keywords' => 'freshbooks import migrate', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/import_export', 'section' => 'import_export', 'label' => 'zoho', 'keywords' => 'zoho books import migrate', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/import_export', 'section' => 'import_export', 'label' => 'waveaccounting', 'keywords' => 'wave accounting import migrate', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/import_export', 'section' => 'import_export', 'label' => 'invoice2go', 'keywords' => 'invoice2go import migrate', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/import_export', 'section' => 'import_export', 'label' => 'invoicely', 'keywords' => 'invoicely import migrate', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Invoice design.
            ['path' => '/settings/invoice_design', 'section' => null, 'label' => 'invoice_design', 'keywords' => 'pdf template layout design theme general fonts', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design', 'section' => 'invoice_design', 'label' => 'general_settings', 'keywords' => 'pdf defaults fonts page size margins', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/custom_designs', 'section' => 'invoice_design', 'label' => 'custom_designs', 'keywords' => 'template html pdf design custom layout', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/custom_designs/create', 'section' => 'invoice_design', 'label' => 'new_design', 'keywords' => 'template html pdf create add', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/client_details', 'section' => 'invoice_design', 'label' => 'client_details', 'keywords' => 'pdf client block customer', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/company_details', 'section' => 'invoice_design', 'label' => 'company', 'keywords' => 'pdf issuer business block', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/company_address', 'section' => 'invoice_design', 'label' => 'company_address', 'keywords' => 'pdf address location', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/invoice_details', 'section' => 'invoice_design', 'label' => 'invoice_details', 'keywords' => 'pdf invoice number date due', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/quote_details', 'section' => 'invoice_design', 'label' => 'quote_details', 'keywords' => 'pdf quote estimate', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/credit_details', 'section' => 'invoice_design', 'label' => 'credit_details', 'keywords' => 'pdf credit note', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/vendor_details', 'section' => 'invoice_design', 'label' => 'vendor_details', 'keywords' => 'pdf supplier vendor', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/purchase_order_details', 'section' => 'invoice_design', 'label' => 'purchase_order_details', 'keywords' => 'pdf po purchase order', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/product_columns', 'section' => 'invoice_design', 'label' => 'product_columns', 'keywords' => 'pdf line items invoice products table columns', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/product_columns', 'section' => 'invoice_design', 'label' => 'invoice_product_columns', 'keywords' => 'pdf invoice line items table', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/quote_product_columns', 'section' => 'invoice_design', 'label' => 'quote_product_columns', 'keywords' => 'pdf quote line items table', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/task_columns', 'section' => 'invoice_design', 'label' => 'task_columns', 'keywords' => 'pdf tasks time table columns', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/invoice_design/total_fields', 'section' => 'invoice_design', 'label' => 'total_fields', 'keywords' => 'pdf subtotal tax balance totals', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Custom fields.
            ['path' => '/settings/custom_fields', 'section' => null, 'label' => 'custom_fields', 'keywords' => 'extra fields metadata', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/company', 'section' => 'custom_fields', 'label' => 'company', 'keywords' => 'extra fields', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/clients', 'section' => 'custom_fields', 'label' => 'clients', 'keywords' => 'extra fields customers', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/products', 'section' => 'custom_fields', 'label' => 'products', 'keywords' => 'extra fields items', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/invoices', 'section' => 'custom_fields', 'label' => 'invoices', 'keywords' => 'extra fields', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/payments', 'section' => 'custom_fields', 'label' => 'payments', 'keywords' => 'extra fields', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/projects', 'section' => 'custom_fields', 'label' => 'projects', 'keywords' => 'extra fields', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/tasks', 'section' => 'custom_fields', 'label' => 'tasks', 'keywords' => 'extra fields', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/vendors', 'section' => 'custom_fields', 'label' => 'vendors', 'keywords' => 'extra fields suppliers', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/expenses', 'section' => 'custom_fields', 'label' => 'expenses', 'keywords' => 'extra fields', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/custom_fields/users', 'section' => 'custom_fields', 'label' => 'users', 'keywords' => 'extra fields staff', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Generated numbers.
            ['path' => '/settings/generated_numbers', 'section' => null, 'label' => 'generated_numbers', 'keywords' => 'invoice number counter pattern prefix sequence', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers', 'section' => 'generated_numbers', 'label' => 'settings', 'keywords' => 'numbering defaults counter pattern', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/clients', 'section' => 'generated_numbers', 'label' => 'clients', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/invoices', 'section' => 'generated_numbers', 'label' => 'invoices', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/recurring_invoices', 'section' => 'generated_numbers', 'label' => 'recurring_invoices', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/payments', 'section' => 'generated_numbers', 'label' => 'payments', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/quotes', 'section' => 'generated_numbers', 'label' => 'quotes', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/credits', 'section' => 'generated_numbers', 'label' => 'credits', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/projects', 'section' => 'generated_numbers', 'label' => 'projects', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/tasks', 'section' => 'generated_numbers', 'label' => 'tasks', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/vendors', 'section' => 'generated_numbers', 'label' => 'vendors', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/purchase_orders', 'section' => 'generated_numbers', 'label' => 'purchase_orders', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/expenses', 'section' => 'generated_numbers', 'label' => 'expenses', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/generated_numbers/recurring_expenses', 'section' => 'generated_numbers', 'label' => 'recurring_expenses', 'keywords' => 'number pattern counter prefix', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Client portal.
            ['path' => '/settings/client_portal', 'section' => null, 'label' => 'client_portal', 'keywords' => 'customer portal self service domain client view', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal', 'section' => 'client_portal', 'label' => 'settings', 'keywords' => 'portal configuration domain', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal/authorization', 'section' => 'client_portal', 'label' => 'authorization', 'keywords' => 'login password protect access', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal/registration', 'section' => 'client_portal', 'label' => 'registration', 'keywords' => 'signup self register', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal/messages', 'section' => 'client_portal', 'label' => 'messages', 'keywords' => 'text terms policy footer', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal/customize', 'section' => 'client_portal', 'label' => 'customize', 'keywords' => 'css branding styling header', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal', 'section' => 'client_portal', 'label' => 'subdomain', 'keywords' => 'portal url domain hostname', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal/registration', 'section' => 'client_portal', 'label' => 'client_registration', 'keywords' => 'signup self register portal', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal', 'section' => 'client_portal', 'label' => 'portal_mode', 'keywords' => 'portal subdomain domain hosted', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal', 'section' => 'client_portal', 'label' => 'domain_url', 'keywords' => 'custom domain portal url', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal', 'section' => 'client_portal', 'label' => 'enable_client_portal_dashboard', 'keywords' => 'client portal dashboard home', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal', 'section' => 'client_portal', 'label' => 'enable_client_profile_update', 'keywords' => 'client profile update edit', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal', 'section' => 'client_portal', 'label' => 'show_pdfhtml_on_mobile', 'keywords' => 'mobile html pdf portal view', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/client_portal', 'section' => 'client_portal', 'label' => 'preference_product_notes_for_html_view', 'keywords' => 'html view product notes portal', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // E-Invoicing.
            ['path' => '/settings/e_invoice', 'section' => null, 'label' => 'e_invoicing', 'keywords' => 'peppol einvoice e-invoice xml ubl storecove electronic verifactu xinvoice fatturapae', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/e_invoice', 'section' => 'e_invoicing', 'label' => 'e_invoice', 'keywords' => 'peppol network registration storecove', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/e_invoice', 'section' => 'e_invoicing', 'label' => 'configure_peppol', 'keywords' => 'peppol network onboarding einvoice storecove', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/e_invoice', 'section' => 'e_invoicing', 'label' => 'verifactu', 'keywords' => 'spain einvoice reporting', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/e_invoice', 'section' => 'e_invoicing', 'label' => 'upload_certificate', 'keywords' => 'certificate pem p12 einvoice', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/e_invoice', 'section' => 'e_invoicing', 'label' => 'certificate_passphrase', 'keywords' => 'certificate password passphrase', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/e_invoice', 'section' => 'e_invoicing', 'label' => 'e_reporting_enabled', 'keywords' => 'france e-reporting einvoice', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/e_invoice', 'section' => 'e_invoicing', 'label' => 'act_as_sender', 'keywords' => 'peppol send e-invoice sender', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/e_invoice', 'section' => 'e_invoicing', 'label' => 'act_as_receiver', 'keywords' => 'peppol receive e-invoice receiver', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/e_invoice', 'section' => 'e_invoicing', 'label' => 'e_invoice_forward_email', 'keywords' => 'peppol forward email', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/e_invoice', 'section' => 'e_invoicing', 'label' => 'skip_automatic_email_with_peppol', 'keywords' => 'peppol disable email send', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Email.
            ['path' => '/settings/email_settings', 'section' => null, 'label' => 'email_settings', 'keywords' => 'smtp mailgun postmark sending from name reply to bcc', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/email_settings', 'section' => 'email_settings', 'label' => 'mailgun', 'keywords' => 'email provider delivery', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/email_settings', 'section' => 'email_settings', 'label' => 'postmark', 'keywords' => 'email provider delivery', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/email_settings', 'section' => 'email_settings', 'label' => 'send_time', 'keywords' => 'email send time schedule', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/email_settings', 'section' => 'email_settings', 'label' => 'sync_send_time', 'keywords' => 'sync send time timezone', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/email_settings', 'section' => 'email_settings', 'label' => 'verify_peer', 'keywords' => 'smtp tls ssl verify certificate', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/email_settings', 'section' => 'email_settings', 'label' => 'bcc_email', 'keywords' => 'blind copy bcc email', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => null, 'label' => 'templates_and_reminders', 'keywords' => 'reminders email templates dunning late fees', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'reminders', 'keywords' => 'dunning overdue payment chase', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'first_reminder', 'keywords' => 'dunning overdue email', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'endless_reminder', 'keywords' => 'dunning recurring overdue', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'late_fees', 'keywords' => 'penalty interest overdue charge', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'email_templates', 'keywords' => 'invoice quote payment email body subject', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'partial_payment', 'keywords' => 'payment email template', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'payment_failed', 'keywords' => 'declined card email template', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'second_reminder', 'keywords' => 'dunning overdue second email', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'third_reminder', 'keywords' => 'dunning overdue third email', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'quote_reminder1', 'keywords' => 'quote reminder email dunning', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'statement', 'keywords' => 'client statement email template', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'first_custom', 'keywords' => 'custom email template one', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'second_custom', 'keywords' => 'custom email template two', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'third_custom', 'keywords' => 'custom email template three', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'purchase_order', 'keywords' => 'purchase order email template po', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'late_fee_amount', 'keywords' => 'late fee fixed amount penalty', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/templates_and_reminders', 'section' => 'templates_and_reminders', 'label' => 'late_fee_percent', 'keywords' => 'late fee percent penalty interest', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Banking.
            ['path' => '/settings/bank_accounts', 'section' => null, 'label' => 'bank_accounts', 'keywords' => 'banking transactions feeds yodlee nordigen plaid gocardless connect account list', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/bank_accounts/create', 'section' => 'bank_accounts', 'label' => 'new_bank_account', 'keywords' => 'connect link banking add', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/bank_accounts/transaction_rules', 'section' => 'bank_accounts', 'label' => 'transaction_rules', 'keywords' => 'matching rules categorise transactions', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/bank_accounts/transaction_rules/create', 'section' => 'bank_accounts', 'label' => 'new_transaction_rule', 'keywords' => 'add matching rule', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Groups / subscriptions / schedules / users.
            ['path' => '/settings/group_settings', 'section' => null, 'label' => 'group_settings', 'keywords' => 'groups client groups', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/group_settings/create', 'section' => 'group_settings', 'label' => 'new_group', 'keywords' => 'add client group', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions', 'section' => null, 'label' => 'payment_links', 'keywords' => 'recurring payment links plans checkout subscriptions', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions', 'section' => 'payment_links', 'label' => 'subscriptions', 'keywords' => 'recurring payment links plans checkout', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions/create', 'section' => 'payment_links', 'label' => 'new_payment_link', 'keywords' => 'add payment link plan subscription', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions/create', 'section' => 'payment_links', 'label' => 'new_subscription', 'keywords' => 'add subscription payment link plan', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions/create', 'section' => 'payment_links', 'label' => 'purchase_page', 'keywords' => 'subscription checkout purchase page', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions/create', 'section' => 'payment_links', 'label' => 'recurring_products', 'keywords' => 'subscription recurring products', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions/create', 'section' => 'payment_links', 'label' => 'optional_products', 'keywords' => 'subscription optional products add-on', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions/create', 'section' => 'payment_links', 'label' => 'optional_recurring_products', 'keywords' => 'subscription optional recurring products', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions/create', 'section' => 'payment_links', 'label' => 'auto_bill', 'keywords' => 'subscription auto bill charge', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions/create', 'section' => 'payment_links', 'label' => 'trial_enabled', 'keywords' => 'subscription trial free period', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions/create', 'section' => 'payment_links', 'label' => 'registration_required', 'keywords' => 'subscription registration required signup', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions/create', 'section' => 'payment_links', 'label' => 'webhook_url', 'keywords' => 'subscription webhook callback url', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/subscriptions/create', 'section' => 'payment_links', 'label' => 'promo_code', 'keywords' => 'subscription promo discount code', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/schedules', 'section' => null, 'label' => 'schedules', 'keywords' => 'scheduled reports automation cron jobs', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/schedules/create', 'section' => 'schedules', 'label' => 'new_schedule', 'keywords' => 'add scheduled report job', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/users', 'section' => null, 'label' => 'user_management', 'keywords' => 'team members staff permissions invite users', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/users', 'section' => 'user_management', 'label' => 'users', 'keywords' => 'team members staff permissions invite', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/users/create', 'section' => 'users', 'label' => 'new_user', 'keywords' => 'add team member staff invite', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/system_logs', 'section' => null, 'label' => 'system_logs', 'keywords' => 'audit logs activity history events', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],

            // Integrations. The bare /settings/integrations path has no index; api tokens,
            // webhooks and analytics each resolve to their own page.
            ['path' => '/settings/integrations/api_tokens', 'section' => 'account_management', 'label' => 'api_tokens', 'keywords' => 'api keys secret token developer integrations', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/integrations/api_tokens/create', 'section' => 'account_management', 'label' => 'new_token', 'keywords' => 'add api key secret', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/integrations/api_webhooks', 'section' => 'account_management', 'label' => 'api_webhooks', 'keywords' => 'webhooks zapier callbacks events', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/integrations/api_webhooks/create', 'section' => 'account_management', 'label' => 'new_webhook', 'keywords' => 'add webhook callback', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
            ['path' => '/settings/integrations/analytics', 'section' => 'account_management', 'label' => 'analytics', 'keywords' => 'google analytics tracking measurement gtag', 'permission' => self::ADMIN, 'scope' => self::SCOPE_ALL],
        ];
    }
}
