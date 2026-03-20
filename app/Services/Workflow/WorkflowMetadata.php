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

namespace App\Services\Workflow;

use App\Models\Company;

class WorkflowMetadata
{
    private const NUMBER_OPERATORS = ['=', '!=', '>', '>=', '<', '<='];
    private const STRING_OPERATORS = ['=', '!=', 'contains', 'starts_with', 'is_empty'];
    private const STATUS_OPERATORS = ['=', '!='];

    private const CUSTOM_FIELD_ENTITY_MAP = [
        'invoice' => 'invoice',
        'quote' => 'invoice',
        'credit' => 'invoice',
        'recurring_invoice' => 'invoice',
        'client' => 'client',
        'payment' => 'payment',
        'task' => 'task',
        'project' => 'project',
        'expense' => 'expense',
        'vendor' => 'vendor',
        'purchase_order' => 'purchase_order',
    ];

    public static function triggers(): array
    {
        return [
            [
                'entity' => 'invoice',
                'label' => 'invoice',
                'events' => [
                    ['event' => 'created', 'label' => 'created'],
                    ['event' => 'sent', 'label' => 'sent'],
                    ['event' => 'paid', 'label' => 'paid'],
                    ['event' => 'viewed', 'label' => 'viewed'],
                    ['event' => 'cancelled', 'label' => 'cancelled'],
                    ['event' => 'reversed', 'label' => 'reversed'],
                    ['event' => 'overdue', 'label' => 'overdue'],
                ],
            ],
            [
                'entity' => 'quote',
                'label' => 'quote',
                'events' => [
                    ['event' => 'created', 'label' => 'created'],
                    ['event' => 'sent', 'label' => 'sent'],
                    ['event' => 'approved', 'label' => 'approved'],
                    ['event' => 'rejected', 'label' => 'rejected'],
                    ['event' => 'converted', 'label' => 'converted'],
                ],
            ],
            [
                'entity' => 'client',
                'label' => 'client',
                'events' => [
                    ['event' => 'created', 'label' => 'created'],
                    ['event' => 'updated', 'label' => 'updated_at'],
                ],
            ],
            [
                'entity' => 'payment',
                'label' => 'payment',
                'events' => [
                    ['event' => 'completed', 'label' => 'completed'],
                    ['event' => 'failed', 'label' => 'failed'],
                    ['event' => 'refunded', 'label' => 'refunded'],
                ],
            ],
            [
                'entity' => 'task',
                'label' => 'task',
                'events' => [
                    ['event' => 'created', 'label' => 'created'],
                    ['event' => 'updated', 'label' => 'updated_at'],
                ],
            ],
            [
                'entity' => 'project',
                'label' => 'project',
                'events' => [
                    ['event' => 'created', 'label' => 'created'],
                    ['event' => 'updated', 'label' => 'updated_at'],
                ],
            ],
            [
                'entity' => 'expense',
                'label' => 'expense',
                'events' => [
                    ['event' => 'created', 'label' => 'created'],
                    ['event' => 'updated', 'label' => 'updated_at'],
                ],
            ],
            [
                'entity' => 'credit',
                'label' => 'credit',
                'events' => [
                    ['event' => 'created', 'label' => 'created'],
                    ['event' => 'sent', 'label' => 'sent'],
                ],
            ],
            [
                'entity' => 'recurring_invoice',
                'label' => 'recurring_invoice',
                'events' => [
                    ['event' => 'started', 'label' => 'active'],
                    ['event' => 'paused', 'label' => 'paused'],
                    ['event' => 'sent', 'label' => 'created'],
                ],
            ],
            [
                'entity' => 'purchase_order',
                'label' => 'purchase_order',
                'events' => [
                    ['event' => 'created', 'label' => 'created'],
                    ['event' => 'sent', 'label' => 'sent'],
                ],
            ],
            [
                'entity' => 'vendor',
                'label' => 'vendor',
                'events' => [
                    ['event' => 'created', 'label' => 'created'],
                ],
            ],
        ];
    }

    public static function actions(): array
    {
        return [
            [
                'type' => 'send_email',
                'label' => 'send_email',
                'icon' => 'email',
                'category' => 'communication',
                'entities' => ['invoice', 'quote', 'credit', 'purchase_order', 'recurring_invoice'],
                'params_schema' => [
                    'to' => ['type' => 'select', 'label' => 'user', 'options' => ['assigned_user', 'creator', 'specific_user', 'all_admins']],
                    'template' => ['type' => 'select', 'label' => 'template', 'required' => true, 'options' => ['invoice', 'quote', 'credit', 'purchase_order', 'reminder1', 'reminder2', 'reminder3', 'custom1','custom2','custom3', 'reminder_endless', 'custom']],
                ],
            ],
            [
                'type' => 'notify_user',
                'label' => 'notifications',
                'icon' => 'notifications',
                'category' => 'communication',
                'entities' => ['invoice', 'quote', 'credit', 'client', 'payment', 'task', 'project', 'expense', 'recurring_invoice', 'purchase_order', 'vendor'],
                'params_schema' => [
                    'to' => ['type' => 'select', 'label' => 'user', 'options' => ['assigned_user', 'creator', 'specific_user', 'all_admins']],
                    'user_id' => ['type' => 'user_select', 'label' => 'user', 'required' => false],
                    'subject' => ['type' => 'string', 'label' => 'subject', 'required' => true, 'supports_variables' => true],
                    'body' => ['type' => 'textarea', 'label' => 'message', 'required' => true, 'supports_variables' => true],
                ],
            ],
            [
                'type' => 'send_webhook',
                'label' => 'webhook',
                'icon' => 'webhook',
                'category' => 'communication',
                'entities' => ['invoice', 'quote', 'credit', 'client', 'payment', 'task', 'project', 'expense', 'recurring_invoice', 'purchase_order', 'vendor'],
                'params_schema' => [
                    'url' => ['type' => 'url', 'label' => 'webhook_url', 'required' => true],
                    'method' => ['type' => 'select', 'label' => 'method', 'options' => ['POST', 'PUT', 'GET']],
                    'headers' => ['type' => 'key_value', 'label' => 'headers', 'required' => false],
                ],
            ],
            [
                'type' => 'create_task',
                'label' => 'create_task',
                'icon' => 'add_task',
                'category' => 'automation',
                'entities' => ['invoice', 'quote', 'credit', 'client', 'payment', 'task', 'project', 'expense', 'recurring_invoice', 'purchase_order', 'vendor'],
                'params_schema' => [
                    'description' => ['type' => 'string', 'label' => 'description', 'required' => true, 'supports_variables' => true],
                    'assigned_user_id' => ['type' => 'user_select', 'label' => 'assigned_user', 'required' => false],
                ],
            ],
            [
                'type' => 'assign_user',
                'label' => 'assigned_user',
                'icon' => 'person_add',
                'category' => 'automation',
                'entities' => ['invoice', 'quote', 'client', 'task', 'project'],
                'params_schema' => [
                    'user_id' => ['type' => 'user_select', 'label' => 'assigned_to', 'required' => false],
                ],
            ],
            [
                'type' => 'convert',
                'label' => 'convert',
                'icon' => 'transform',
                'category' => 'automation',
                'entities' => ['quote'],
                'params_schema' => [
                    'from' => ['type' => 'select', 'label' => 'source', 'options' => ['quote']],
                    'to' => ['type' => 'select', 'label' => 'convert_to', 'options' => ['invoice', 'project']],
                ],
            ],
            [
                'type' => 'mark_sent',
                'label' => 'mark_sent',
                'icon' => 'mark_email_read',
                'category' => 'automation',
                'entities' => ['invoice', 'quote'],
                'params_schema' => [],
            ],
            [
                'type' => 'auto_bill',
                'label' => 'auto_bill',
                'icon' => 'credit_card',
                'category' => 'automation',
                'entities' => ['invoice'],
                'params_schema' => [],
            ],
            [
                'type' => 'approve',
                'label' => 'approve',
                'icon' => 'check_circle',
                'category' => 'automation',
                'entities' => ['quote'],
                'params_schema' => [],
            ],
            [
                'type' => 'increase_price',
                'label' => 'increase_price',
                'icon' => 'trending_up',
                'category' => 'automation',
                'entities' => ['recurring_invoice'],
                'params_schema' => [
                    'percentage' => ['type' => 'number', 'label' => 'percentage', 'required' => true],
                ],
            ],
            [
                'type' => 'update_price',
                'label' => 'update_price',
                'icon' => 'sync',
                'category' => 'automation',
                'entities' => ['recurring_invoice'],
                'params_schema' => [],
            ],
            [
                'type' => 'add_to_inventory',
                'label' => 'add_to_inventory',
                'icon' => 'inventory',
                'category' => 'automation',
                'entities' => ['purchase_order'],
                'params_schema' => [],
            ],
            [
                'type' => 'expense_from_po',
                'label' => 'expense',
                'icon' => 'receipt',
                'category' => 'automation',
                'entities' => ['purchase_order'],
                'params_schema' => [],
            ],
            [
                'type' => 'archive',
                'label' => 'archive',
                'icon' => 'archive',
                'category' => 'automation',
                'entities' => ['invoice', 'quote', 'credit', 'expense', 'recurring_invoice', 'purchase_order', 'vendor'],
                'params_schema' => [],
            ],
        ];
    }

    /**
     * Get actions filtered to those available for a specific trigger entity.
     */
    public static function actionsForEntity(string $entity): array
    {
        return array_values(array_filter(self::actions(), function ($action) use ($entity) {
            return in_array($entity, $action['entities']);
        }));
    }

    public static function fields(?Company $company = null): array
    {
        $fields = [
            'invoice' => [
                ['field' => 'amount', 'label' => 'amount', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'balance', 'label' => 'balance', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'paid_to_date', 'label' => 'paid_to_date', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'status_id', 'label' => 'status', 'type' => 'status', 'operators' => self::STATUS_OPERATORS, 'options' => [['value' => 1, 'label' => 'draft'], ['value' => 2, 'label' => 'sent'], ['value' => 3, 'label' => 'partial'], ['value' => 4, 'label' => 'paid']]],
                ['field' => 'date', 'label' => 'date', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'due_date', 'label' => 'due_date', 'type' => 'date', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'discount', 'label' => 'discount', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'po_number', 'label' => 'po_number', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'public_notes', 'label' => 'public_notes', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'terms', 'label' => 'terms', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'footer', 'label' => 'footer', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'custom_value1', 'label' => 'custom_value1', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'custom_value2', 'label' => 'custom_value2', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'custom_value3', 'label' => 'custom_value3', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'custom_value4', 'label' => 'custom_value4', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
            ],
            'quote' => [
                ['field' => 'amount', 'label' => 'amount', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'status_id', 'label' => 'status', 'type' => 'status', 'operators' => self::STATUS_OPERATORS, 'options' => [['value' => 1, 'label' => 'draft'], ['value' => 2, 'label' => 'sent'], ['value' => 3, 'label' => 'approved'], ['value' => 5, 'label' => 'rejected']]],
                ['field' => 'due_date', 'label' => 'valid_until', 'type' => 'date', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'po_number', 'label' => 'po_number', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'public_notes', 'label' => 'public_notes', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'discount', 'label' => 'discount', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
            ],
            'client' => [
                ['field' => 'balance', 'label' => 'outstanding', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'paid_to_date', 'label' => 'paid_to_date', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'name', 'label' => 'name', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'id_number', 'label' => 'id_number', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'vat_number', 'label' => 'vat_number', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'country_id', 'label' => 'country', 'type' => 'number', 'operators' => self::STATUS_OPERATORS],
                ['field' => 'credit_balance', 'label' => 'credit_balance', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'payment_balance', 'label' => 'payment_balance', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
            ],
            'payment' => [
                ['field' => 'amount', 'label' => 'amount', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'status_id', 'label' => 'status', 'type' => 'status', 'operators' => self::STATUS_OPERATORS, 'options' => [['value' => 1, 'label' => 'pending'], ['value' => 2, 'label' => 'cancelled'], ['value' => 3, 'label' => 'failed'], ['value' => 4, 'label' => 'completed'], ['value' => 5, 'label' => 'partially_refunded'], ['value' => 6, 'label' => 'refunded']]],
                ['field' => 'transaction_reference', 'label' => 'transaction_reference', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
            ],
            'expense' => [
                ['field' => 'amount', 'label' => 'amount', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'public_notes', 'label' => 'public_notes', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'category_id', 'label' => 'category', 'type' => 'relation', 'operators' => self::STATUS_OPERATORS],
                ['field' => 'tax_amount1', 'label' => 'tax_amount', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
            ],
            'credit' => [
                ['field' => 'amount', 'label' => 'amount', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'balance', 'label' => 'balance', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'status_id', 'label' => 'status', 'type' => 'status', 'operators' => self::STATUS_OPERATORS, 'options' => [['value' => 1, 'label' => 'draft'], ['value' => 2, 'label' => 'sent'], ['value' => 3, 'label' => 'partial'], ['value' => 4, 'label' => 'applied']]],
                ['field' => 'po_number', 'label' => 'po_number', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
            ],
            'recurring_invoice' => [
                ['field' => 'amount', 'label' => 'amount', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'status_id', 'label' => 'status', 'type' => 'status', 'operators' => self::STATUS_OPERATORS, 'options' => [['value' => 1, 'label' => 'draft'], ['value' => 2, 'label' => 'active'], ['value' => 3, 'label' => 'paused'], ['value' => 4, 'label' => 'completed']]],
                ['field' => 'frequency_id', 'label' => 'frequency', 'type' => 'number', 'operators' => self::STATUS_OPERATORS],
                ['field' => 'remaining_cycles', 'label' => 'remaining_cycles', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
            ],
            'purchase_order' => [
                ['field' => 'amount', 'label' => 'amount', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'balance', 'label' => 'balance', 'type' => 'number', 'operators' => self::NUMBER_OPERATORS],
                ['field' => 'status_id', 'label' => 'status', 'type' => 'status', 'operators' => self::STATUS_OPERATORS, 'options' => [['value' => 1, 'label' => 'draft'], ['value' => 2, 'label' => 'sent'], ['value' => 3, 'label' => 'accepted'], ['value' => 4, 'label' => 'received'], ['value' => 5, 'label' => 'cancelled']]],
                ['field' => 'public_notes', 'label' => 'public_notes', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
            ],
            'vendor' => [
                ['field' => 'name', 'label' => 'name', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'id_number', 'label' => 'id_number', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
                ['field' => 'vat_number', 'label' => 'vat_number', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
            ],
            'task' => [
                ['field' => 'status_id', 'label' => 'status', 'type' => 'relation', 'operators' => self::STATUS_OPERATORS],
                ['field' => 'description', 'label' => 'description', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
            ],
            'project' => [
                ['field' => 'budgeted_hours', 'label' => 'budgeted_hours', 'type' => 'number', 'operators' => ['=', '>', '<']],
                ['field' => 'current_hours', 'label' => 'hours', 'type' => 'number', 'operators' => ['=', '>', '>=']],
                ['field' => 'budget_utilization_pct', 'label' => 'budgeted_hours', 'type' => 'number', 'operators' => ['>', '>=', '<', '<=']],
                ['field' => 'name', 'label' => 'name', 'type' => 'string', 'operators' => self::STRING_OPERATORS],
            ],
        ];

        if ($company) {
            self::appendCustomFields($fields, $company);
        }

        return $fields;
    }

    private static function appendCustomFields(array &$fields, Company $company): void
    {
        $customFields = $company->custom_fields;

        if (! $customFields) {
            return;
        }

        foreach (self::CUSTOM_FIELD_ENTITY_MAP as $entity => $cfPrefix) {
            if (! isset($fields[$entity])) {
                continue;
            }

            for ($i = 1; $i <= 4; $i++) {
                $cfKey = "{$cfPrefix}{$i}";

                if (! property_exists($customFields, $cfKey)) {
                    continue;
                }

                $cfDef = $customFields->{$cfKey};

                if (empty($cfDef)) {
                    continue;
                }

                $fields[$entity][] = self::resolveCustomField($cfDef, $i);
            }
        }
    }

    private static function resolveCustomField(string $definition, int $index): array
    {
        $parts = explode('|', $definition, 2);
        $label = $parts[0] ?: "custom_value{$index}";
        $typeDef = $parts[1] ?? 'single_line_text';

        if ($typeDef === 'switch') {
            return [
                'field' => "custom_value{$index}",
                'label' => $label,
                'type' => 'select',
                'operators' => self::STATUS_OPERATORS,
                'options' => [['value' => 'yes', 'label' => 'yes'], ['value' => 'no', 'label' => 'no']],
            ];
        }

        if ($typeDef === 'date') {
            return [
                'field' => "custom_value{$index}",
                'label' => $label,
                'type' => 'date',
                'operators' => self::NUMBER_OPERATORS,
            ];
        }

        // Comma-separated options = dropdown select
        if ($typeDef !== 'single_line_text' && str_contains($typeDef, ',')) {
            $options = array_map(fn ($opt) => ['value' => trim($opt), 'label' => trim($opt)], explode(',', $typeDef));

            return [
                'field' => "custom_value{$index}",
                'label' => $label,
                'type' => 'select',
                'operators' => ['=', '!=', 'in'],
                'options' => $options,
            ];
        }

        // single_line_text — could be string or number, so provide both operator sets
        return [
            'field' => "custom_value{$index}",
            'label' => $label,
            'type' => 'string',
            'operators' => array_values(array_unique(array_merge(self::STRING_OPERATORS, self::NUMBER_OPERATORS))),
        ];
    }

    /**
     * Condition group configuration for trigger_conditions.
     *
     * Groups are OR'd together. Within each group, "match" controls
     * whether conditions are AND'd (all) or OR'd (any).
     */
    public static function conditionGroupMeta(): array
    {
        return [
            'group_logic' => 'or',
            'group_match_options' => [
                ['value' => 'all', 'label' => 'all_conditions'],
                ['value' => 'any', 'label' => 'any_condition'],
            ],
        ];
    }

    /**
     * Date fields available per entity for wait_delay steps.
     * The UI uses this to populate the date field picker.
     *
     * offset_operators: the direction options the UI shows alongside the days input.
     *   - "before" → negative offset_days in engine
     *   - "on"     → offset_days = 0 in engine
     *   - "after"  → positive offset_days in engine
     */
    public static function dateFields(): array
    {
        return [
            'offset_operators' => [
                ['value' => 'before', 'label' => 'before'],
                ['value' => 'on', 'label' => 'on'],
                ['value' => 'after', 'label' => 'after'],
            ],
            'entity_fields' => [
                'invoice' => [
                    ['field' => 'date', 'label' => 'invoice_date'],
                    ['field' => 'due_date', 'label' => 'due_date'],
                ],
                'quote' => [
                    ['field' => 'date', 'label' => 'quote_date'],
                    ['field' => 'due_date', 'label' => 'valid_until'],
                ],
                'credit' => [
                    ['field' => 'date', 'label' => 'credit_date'],
                    ['field' => 'due_date', 'label' => 'due_date'],
                ],
                'purchase_order' => [
                    ['field' => 'date', 'label' => 'date'],
                    ['field' => 'due_date', 'label' => 'due_date'],
                ],
                'expense' => [
                    ['field' => 'date', 'label' => 'expense_date'],
                    ['field' => 'payment_date', 'label' => 'payment_date'],
                ],
                'task' => [
                    ['field' => 'calculated_start_date', 'label' => 'start_date'],
                ],
                'recurring_invoice' => [
                    ['field' => 'next_send_date', 'label' => 'next_send_date'],
                    ['field' => 'due_date_days', 'label' => 'due_date'],
                ],
            ],
        ];
    }

    public static function templates(): array
    {
        return [
            [
                'key' => 'overdue_collection',
                'name' => 'overdue',
                'description' => 'overdue',
                'category' => 'category',
                'trigger_entity' => 'invoice',
                'trigger_event' => 'sent',
                'steps_count' => 11,
            ],
            [
                'key' => 'quote_followup',
                'name' => 'quote',
                'description' => 'quote',
                'category' => 'category',
                'trigger_entity' => 'quote',
                'trigger_event' => 'sent',
                'steps_count' => 5,
            ],
            [
                'key' => 'client_onboarding',
                'name' => 'new_client',
                'description' => 'new_client',
                'category' => 'category',
                'trigger_entity' => 'client',
                'trigger_event' => 'created',
                'steps_count' => 4,
            ],
            [
                'key' => 'auto_bill_overdue',
                'name' => 'auto_bill',
                'description' => 'auto_bill',
                'category' => 'category',
                'trigger_entity' => 'invoice',
                'trigger_event' => 'sent',
                'steps_count' => 5,
            ],
            [
                'key' => 'purchase_order_to_expense',
                'name' => 'purchase_order',
                'description' => 'purchase_order',
                'category' => 'category',
                'trigger_entity' => 'purchase_order',
                'trigger_event' => 'sent',
                'steps_count' => 5,
            ],
        ];
    }

    public static function getTemplate(string $key): ?array
    {
        return match ($key) {
            'overdue_collection' => [
                'name' => 'overdue',
                'description' => 'overdue',
                'trigger_entity' => 'invoice',
                'trigger_event' => 'sent',
                'trigger_conditions' => [],
                'steps' => [
                    ['id' => 'wait_overdue', 'name' => 'Wait 7 days after due date', 'type' => 'wait_delay', 'date_field' => '$trigger.due_date', 'offset_days' => 7, 'position' => ['x' => 250, 'y' => 100]],
                    ['id' => 'check_1', 'name' => 'Still unpaid?', 'type' => 'branch', 'conditions' => [['label' => 'Has balance', 'if' => ['field' => '$trigger.balance', 'operator' => '>', 'value' => 0], 'goto' => 'reminder_1']], 'default_next' => 'end_paid', 'position' => ['x' => 250, 'y' => 150]],
                    ['id' => 'reminder_1', 'name' => 'Send Reminder 1', 'type' => 'action', 'action' => 'send_email', 'params' => ['entity_ref' => '$trigger', 'template' => 'reminder1'], 'position' => ['x' => 250, 'y' => 200]],
                    ['id' => 'wait_2', 'name' => 'Wait 14 days after due date', 'type' => 'wait_delay', 'date_field' => '$trigger.due_date', 'offset_days' => 14, 'position' => ['x' => 250, 'y' => 300]],
                    ['id' => 'check_2', 'name' => 'Still unpaid?', 'type' => 'branch', 'conditions' => [['label' => 'Has balance', 'if' => ['field' => '$trigger.balance', 'operator' => '>', 'value' => 0], 'goto' => 'reminder_2']], 'default_next' => 'end_paid', 'position' => ['x' => 250, 'y' => 350]],
                    ['id' => 'reminder_2', 'name' => 'Send Reminder 2', 'type' => 'action', 'action' => 'send_email', 'params' => ['entity_ref' => '$trigger', 'template' => 'reminder2'], 'position' => ['x' => 250, 'y' => 400]],
                    ['id' => 'wait_3', 'name' => 'Wait 30 days after due date', 'type' => 'wait_delay', 'date_field' => '$trigger.due_date', 'offset_days' => 30, 'position' => ['x' => 250, 'y' => 500]],
                    ['id' => 'check_3', 'name' => 'Still unpaid?', 'type' => 'branch', 'conditions' => [['label' => 'Has balance', 'if' => ['field' => '$trigger.balance', 'operator' => '>', 'value' => 0], 'goto' => 'notify_admin']], 'default_next' => 'end_paid', 'position' => ['x' => 250, 'y' => 550]],
                    ['id' => 'notify_admin', 'name' => 'Notify Account Manager', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'assigned_user', 'message' => 'Invoice {{invoice.number}} for {{client.name}} is 51+ days overdue'], 'position' => ['x' => 250, 'y' => 600]],
                    ['id' => 'end', 'name' => 'End', 'type' => 'end', 'end_status' => 'completed', 'position' => ['x' => 250, 'y' => 700]],
                    ['id' => 'end_paid', 'name' => 'Paid - no action needed', 'type' => 'end', 'end_status' => 'completed', 'position' => ['x' => 450, 'y' => 400]],
                ],
            ],
            'quote_followup' => [
                'name' => 'quote',
                'description' => 'quote',
                'trigger_entity' => 'quote',
                'trigger_event' => 'sent',
                'trigger_conditions' => [],
                'steps' => [
                    ['id' => 'wait_response', 'name' => 'Wait for response', 'type' => 'wait_for_event', 'event' => 'quote.approved|quote.rejected', 'timeout_days' => 3, 'on_timeout' => 'followup', 'position' => ['x' => 250, 'y' => 100]],
                    ['id' => 'end_responded', 'name' => 'Client responded', 'type' => 'end', 'end_status' => 'completed', 'position' => ['x' => 250, 'y' => 200]],
                    ['id' => 'followup', 'name' => 'Send follow-up email', 'type' => 'action', 'action' => 'send_email', 'params' => ['entity_ref' => '$trigger', 'template' => 'quote'], 'position' => ['x' => 450, 'y' => 200]],
                    ['id' => 'notify', 'name' => 'Notify sales rep', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'assigned_user', 'message' => 'Quote {{quote.number}} has not been responded to after follow-up'], 'position' => ['x' => 450, 'y' => 300]],
                    ['id' => 'end_followup', 'name' => 'End', 'type' => 'end', 'end_status' => 'completed', 'position' => ['x' => 450, 'y' => 400]],
                ],
            ],
            'client_onboarding' => [
                'name' => 'new_client',
                'description' => 'new_client',
                'trigger_entity' => 'client',
                'trigger_event' => 'created',
                'trigger_conditions' => [],
                'steps' => [
                    ['id' => 'create_task', 'name' => 'Create onboarding task', 'type' => 'action', 'action' => 'create_task', 'params' => ['description' => 'Onboard new client: {{client.name}}', 'due_date_offset' => 7], 'output_key' => 'task', 'position' => ['x' => 250, 'y' => 100]],
                    ['id' => 'assign', 'name' => 'Assign account manager', 'type' => 'action', 'action' => 'assign_user', 'params' => ['entity_ref' => '$trigger', 'strategy' => 'round_robin'], 'position' => ['x' => 250, 'y' => 200]],
                    ['id' => 'notify', 'name' => 'Notify team', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'all_admins', 'message' => 'New client {{client.name}} has been onboarded and assigned'], 'position' => ['x' => 250, 'y' => 300]],
                    ['id' => 'end', 'name' => 'Complete', 'type' => 'end', 'end_status' => 'completed', 'position' => ['x' => 250, 'y' => 400]],
                ],
            ],
            'auto_bill_overdue' => [
                'name' => 'auto_bill',
                'description' => 'auto_bill',
                'trigger_entity' => 'invoice',
                'trigger_event' => 'sent',
                'trigger_conditions' => [],
                'steps' => [
                    ['id' => 'wait_overdue', 'name' => 'Wait 7 days after due date', 'type' => 'wait_delay', 'date_field' => '$trigger.due_date', 'offset_days' => 7, 'position' => ['x' => 250, 'y' => 100]],
                    ['id' => 'check_balance', 'name' => 'Check balance', 'type' => 'branch', 'conditions' => [['label' => 'Has balance', 'if' => ['field' => '$trigger.balance', 'operator' => '>', 'value' => 0], 'goto' => 'auto_bill']], 'default_next' => 'end_paid', 'position' => ['x' => 250, 'y' => 200]],
                    ['id' => 'auto_bill', 'name' => 'Auto Bill', 'type' => 'action', 'action' => 'entity_operation', 'params' => ['entity_ref' => '$trigger', 'operation' => 'auto_bill'], 'on_guard_fail' => 'skip', 'on_error' => 'notify_error', 'max_retries' => 2, 'next' => 'end_billed', 'position' => ['x' => 250, 'y' => 300]],
                    ['id' => 'notify_error', 'name' => 'Notify billing error', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'assigned_user', 'message' => 'Auto-billing failed for {{invoice.number}}. Please process manually.'], 'next' => 'end_failed', 'position' => ['x' => 450, 'y' => 300]],
                    ['id' => 'end_paid', 'name' => 'Already paid', 'type' => 'end', 'end_status' => 'completed', 'position' => ['x' => 450, 'y' => 200]],
                    ['id' => 'end_billed', 'name' => 'Billed', 'type' => 'end', 'end_status' => 'completed', 'position' => ['x' => 250, 'y' => 400]],
                    ['id' => 'end_failed', 'name' => 'Failed', 'type' => 'end', 'end_status' => 'billing_failed', 'position' => ['x' => 450, 'y' => 400]],
                ],
            ],
            'purchase_order_to_expense' => [
                'name' => 'purchase_order',
                'description' => 'purchase_order',
                'trigger_entity' => 'purchase_order',
                'trigger_event' => 'sent',
                'trigger_conditions' => [],
                'steps' => [
                    ['id' => 'add_inventory', 'name' => 'Add to Inventory', 'type' => 'action', 'action' => 'entity_operation', 'params' => ['entity_ref' => '$trigger', 'operation' => 'add_to_inventory'], 'on_guard_fail' => 'skip', 'position' => ['x' => 250, 'y' => 100]],
                    ['id' => 'create_expense', 'name' => 'Create Expense', 'type' => 'action', 'action' => 'entity_operation', 'params' => ['entity_ref' => '$trigger', 'operation' => 'expense'], 'output_key' => 'expense', 'position' => ['x' => 250, 'y' => 200]],
                    ['id' => 'notify', 'name' => 'Notify assigned user', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'assigned_user', 'message' => 'Expense created from PO for {{purchase_order.number}}'], 'position' => ['x' => 250, 'y' => 300]],
                    ['id' => 'end', 'name' => 'End', 'type' => 'end', 'end_status' => 'completed', 'position' => ['x' => 250, 'y' => 400]],
                ],
            ],
            default => null,
        };
    }
}
