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

class WorkflowMetadata
{
    public static function triggers(): array
    {
        return [
            [
                'entity' => 'invoice',
                'label' => 'Invoice',
                'events' => [
                    ['event' => 'created', 'label' => 'Created'],
                    ['event' => 'sent', 'label' => 'Sent / Emailed'],
                    ['event' => 'paid', 'label' => 'Paid'],
                    ['event' => 'viewed', 'label' => 'Viewed by Client'],
                    ['event' => 'cancelled', 'label' => 'Cancelled'],
                    ['event' => 'reversed', 'label' => 'Reversed'],
                ],
            ],
            [
                'entity' => 'quote',
                'label' => 'Quote',
                'events' => [
                    ['event' => 'created', 'label' => 'Created'],
                    ['event' => 'sent', 'label' => 'Sent / Emailed'],
                    ['event' => 'approved', 'label' => 'Approved by Client'],
                    ['event' => 'rejected', 'label' => 'Rejected by Client'],
                    ['event' => 'converted', 'label' => 'Converted to Invoice'],
                ],
            ],
            [
                'entity' => 'client',
                'label' => 'Client',
                'events' => [
                    ['event' => 'created', 'label' => 'Created'],
                    ['event' => 'updated', 'label' => 'Updated'],
                ],
            ],
            [
                'entity' => 'payment',
                'label' => 'Payment',
                'events' => [
                    ['event' => 'completed', 'label' => 'Completed'],
                    ['event' => 'failed', 'label' => 'Failed'],
                    ['event' => 'refunded', 'label' => 'Refunded'],
                ],
            ],
            [
                'entity' => 'task',
                'label' => 'Task',
                'events' => [
                    ['event' => 'created', 'label' => 'Created'],
                    ['event' => 'updated', 'label' => 'Updated'],
                ],
            ],
            [
                'entity' => 'project',
                'label' => 'Project',
                'events' => [
                    ['event' => 'created', 'label' => 'Created'],
                    ['event' => 'updated', 'label' => 'Updated'],
                ],
            ],
            [
                'entity' => 'expense',
                'label' => 'Expense',
                'events' => [
                    ['event' => 'created', 'label' => 'Created'],
                    ['event' => 'updated', 'label' => 'Updated'],
                ],
            ],
        ];
    }

    public static function actions(): array
    {
        return [
            [
                'type' => 'send_email',
                'label' => 'Send Email',
                'icon' => 'email',
                'category' => 'communication',
                'params_schema' => [
                    'entity_ref' => ['type' => 'entity_reference', 'label' => 'Entity to Email', 'required' => true],
                    'template' => ['type' => 'select', 'label' => 'Email Template', 'required' => true, 'options' => ['invoice', 'quote', 'credit', 'purchase_order', 'payment', 'reminder1', 'reminder2', 'reminder3', 'statement']],
                ],
            ],
            [
                'type' => 'convert',
                'label' => 'Convert Entity',
                'icon' => 'transform',
                'category' => 'entity',
                'params_schema' => [
                    'from' => ['type' => 'select', 'label' => 'Source', 'options' => ['quote']],
                    'to' => ['type' => 'select', 'label' => 'Target', 'options' => ['invoice', 'project']],
                ],
            ],
            [
                'type' => 'assign_user',
                'label' => 'Assign User',
                'icon' => 'person_add',
                'category' => 'entity',
                'params_schema' => [
                    'entity_ref' => ['type' => 'entity_reference', 'label' => 'Entity', 'required' => true],
                    'strategy' => ['type' => 'select', 'label' => 'Strategy', 'options' => ['specific', 'round_robin']],
                    'user_id' => ['type' => 'user_select', 'label' => 'Assign To', 'required' => false],
                ],
            ],
            [
                'type' => 'update_field',
                'label' => 'Update Field',
                'icon' => 'edit',
                'category' => 'entity',
                'params_schema' => [
                    'entity_ref' => ['type' => 'entity_reference', 'label' => 'Entity', 'required' => true],
                    'field' => ['type' => 'field_select', 'label' => 'Field', 'required' => true],
                    'value' => ['type' => 'dynamic', 'label' => 'New Value', 'required' => true],
                ],
            ],
            [
                'type' => 'create_task',
                'label' => 'Create Task',
                'icon' => 'add_task',
                'category' => 'creation',
                'params_schema' => [
                    'description' => ['type' => 'string', 'label' => 'Description', 'required' => true, 'supports_variables' => true],
                    'project_ref' => ['type' => 'entity_reference', 'label' => 'Project (optional)', 'required' => false],
                    'assigned_user_id' => ['type' => 'user_select', 'label' => 'Assign To', 'required' => false],
                    'due_date_offset' => ['type' => 'number', 'label' => 'Due in (days)', 'required' => false],
                ],
            ],
            [
                'type' => 'notify_user',
                'label' => 'Notify Team Member',
                'icon' => 'notifications',
                'category' => 'communication',
                'params_schema' => [
                    'to' => ['type' => 'select', 'label' => 'Notify', 'options' => ['assigned_user', 'creator', 'specific_user', 'all_admins']],
                    'user_id' => ['type' => 'user_select', 'label' => 'Specific User', 'required' => false],
                    'message' => ['type' => 'textarea', 'label' => 'Message', 'required' => true, 'supports_variables' => true],
                ],
            ],
            [
                'type' => 'send_webhook',
                'label' => 'Send Webhook',
                'icon' => 'webhook',
                'category' => 'integration',
                'params_schema' => [
                    'url' => ['type' => 'url', 'label' => 'Webhook URL', 'required' => true],
                    'method' => ['type' => 'select', 'label' => 'HTTP Method', 'options' => ['POST', 'PUT', 'GET']],
                ],
            ],
        ];
    }

    public static function fields(): array
    {
        return [
            'invoice' => [
                ['field' => 'amount', 'label' => 'Amount', 'type' => 'number', 'operators' => ['=', '>', '>=', '<', '<=']],
                ['field' => 'balance', 'label' => 'Balance', 'type' => 'number', 'operators' => ['=', '>', '>=', '<', '<=']],
                ['field' => 'status_id', 'label' => 'Status', 'type' => 'status', 'operators' => ['=', '!='], 'options' => [['value' => 1, 'label' => 'Draft'], ['value' => 2, 'label' => 'Sent'], ['value' => 3, 'label' => 'Partial'], ['value' => 4, 'label' => 'Paid']]],
                ['field' => 'days_overdue', 'label' => 'Days Overdue', 'type' => 'number', 'operators' => ['=', '>', '>=', '<', '<=']],
            ],
            'quote' => [
                ['field' => 'amount', 'label' => 'Amount', 'type' => 'number', 'operators' => ['=', '>', '>=', '<', '<=']],
                ['field' => 'status_id', 'label' => 'Status', 'type' => 'status', 'operators' => ['=', '!='], 'options' => [['value' => 1, 'label' => 'Draft'], ['value' => 2, 'label' => 'Sent'], ['value' => 3, 'label' => 'Approved'], ['value' => 5, 'label' => 'Rejected']]],
            ],
            'client' => [
                ['field' => 'balance', 'label' => 'Outstanding Balance', 'type' => 'number', 'operators' => ['=', '>', '>=', '<', '<=']],
                ['field' => 'paid_to_date', 'label' => 'Paid to Date', 'type' => 'number', 'operators' => ['=', '>', '>=', '<', '<=']],
            ],
            'task' => [
                ['field' => 'status_id', 'label' => 'Status', 'type' => 'relation', 'operators' => ['=', '!=']],
            ],
            'project' => [
                ['field' => 'budgeted_hours', 'label' => 'Budgeted Hours', 'type' => 'number', 'operators' => ['=', '>', '<']],
                ['field' => 'current_hours', 'label' => 'Current Hours', 'type' => 'number', 'operators' => ['=', '>', '>=']],
                ['field' => 'budget_utilization_pct', 'label' => 'Budget Used %', 'type' => 'number', 'operators' => ['>', '>=', '<', '<=']],
            ],
        ];
    }

    public static function templates(): array
    {
        return [
            [
                'key' => 'overdue_collection',
                'name' => 'Overdue Collection',
                'description' => 'Automated reminder sequence for overdue invoices',
                'category' => 'billing',
                'trigger_entity' => 'invoice',
                'trigger_event' => 'sent',
                'steps_count' => 7,
            ],
            [
                'key' => 'quote_followup',
                'name' => 'Quote Follow-up',
                'description' => 'Automated follow-up when quotes are not responded to',
                'category' => 'sales',
                'trigger_entity' => 'quote',
                'trigger_event' => 'sent',
                'steps_count' => 5,
            ],
            [
                'key' => 'client_onboarding',
                'name' => 'Client Onboarding',
                'description' => 'Welcome email and task creation for new clients',
                'category' => 'onboarding',
                'trigger_entity' => 'client',
                'trigger_event' => 'created',
                'steps_count' => 4,
            ],
        ];
    }

    public static function getTemplate(string $key): ?array
    {
        return match ($key) {
            'overdue_collection' => [
                'name' => 'Overdue Collection',
                'description' => 'Automated reminder sequence for overdue invoices',
                'trigger_entity' => 'invoice',
                'trigger_event' => 'sent',
                'trigger_conditions' => [],
                'steps' => [
                    ['id' => 'wait_overdue', 'name' => 'Wait until overdue', 'type' => 'wait_delay', 'delay_days' => 7, 'position' => ['x' => 250, 'y' => 100]],
                    ['id' => 'reminder_1', 'name' => 'Send Reminder 1', 'type' => 'action', 'action' => 'send_email', 'params' => ['entity_ref' => '$trigger', 'template' => 'reminder1'], 'position' => ['x' => 250, 'y' => 200]],
                    ['id' => 'wait_2', 'name' => 'Wait 14 days', 'type' => 'wait_delay', 'delay_days' => 14, 'position' => ['x' => 250, 'y' => 300]],
                    ['id' => 'reminder_2', 'name' => 'Send Reminder 2', 'type' => 'action', 'action' => 'send_email', 'params' => ['entity_ref' => '$trigger', 'template' => 'reminder2'], 'position' => ['x' => 250, 'y' => 400]],
                    ['id' => 'wait_3', 'name' => 'Wait 30 days', 'type' => 'wait_delay', 'delay_days' => 30, 'position' => ['x' => 250, 'y' => 500]],
                    ['id' => 'notify_admin', 'name' => 'Notify Account Manager', 'type' => 'action', 'action' => 'notify_user', 'params' => ['to' => 'assigned_user', 'message' => 'Invoice {{invoice.number}} for {{client.name}} is 51+ days overdue'], 'position' => ['x' => 250, 'y' => 600]],
                    ['id' => 'end', 'name' => 'End', 'type' => 'end', 'end_status' => 'completed', 'position' => ['x' => 250, 'y' => 700]],
                ],
            ],
            'quote_followup' => [
                'name' => 'Quote Follow-up',
                'description' => 'Automated follow-up when quotes are not responded to',
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
                'name' => 'Client Onboarding',
                'description' => 'Welcome sequence for new clients',
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
            default => null,
        };
    }
}
