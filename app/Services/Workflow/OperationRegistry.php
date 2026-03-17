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

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RecurringInvoice;

class OperationRegistry
{
    public static function operations(): array
    {
        return [
            'invoice' => [
                // --- Status transitions ---
                'mark_sent' => [
                    'label' => 'Mark Sent',
                    'category' => 'status',
                    'method' => 'markSent',
                    'guard' => ['status_id', '=', Invoice::STATUS_DRAFT],
                    'assert' => ['status_id', '=', Invoice::STATUS_SENT],
                    'events' => true,
                ],
                'mark_paid' => [
                    'label' => 'Mark Paid',
                    'category' => 'status',
                    'method' => 'markPaid',
                    'guard' => ['balance', '>', 0],
                    'assert' => ['status_id', '=', Invoice::STATUS_PAID],
                    'events' => true,
                ],
                'cancel' => [
                    'label' => 'Cancel',
                    'category' => 'status',
                    'method' => 'handleCancellation',
                    'guard' => ['status_id', 'in', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL]],
                    'assert' => ['status_id', '=', Invoice::STATUS_CANCELLED],
                    'events' => true,
                ],
                'reverse_cancellation' => [
                    'label' => 'Reverse Cancellation',
                    'category' => 'status',
                    'method' => 'reverseCancellation',
                    'guard' => ['status_id', '=', Invoice::STATUS_CANCELLED],
                    'assert' => ['status_id', '!=', Invoice::STATUS_CANCELLED],
                    'events' => true,
                ],
                'mark_viewed' => [
                    'label' => 'Mark Viewed',
                    'category' => 'status',
                    'method' => 'markViewed',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],

                // --- Billing ---
                'auto_bill' => [
                    'label' => 'Auto Bill',
                    'category' => 'billing',
                    'method' => 'autoBill',
                    'guard' => ['balance', '>', 0],
                    'assert' => ['balance', '<', '$pre.balance'],
                    'events' => true,
                ],

                // --- Communication ---
                'send_email' => [
                    'label' => 'Send Email',
                    'category' => 'communication',
                    'method' => 'sendEmail',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                    'pre_call' => 'markSent',
                ],

                // --- Document ---
                'delete_pdf' => [
                    'label' => 'Delete PDF',
                    'category' => 'document',
                    'method' => 'deletePdf',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'adjust_inventory' => [
                    'label' => 'Adjust Inventory',
                    'category' => 'document',
                    'method' => 'adjustInventory',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],

                // --- Configuration ---
                'fill_defaults' => [
                    'label' => 'Fill Defaults',
                    'category' => 'configuration',
                    'method' => 'fillDefaults',
                    'guard' => ['status_id', '=', Invoice::STATUS_DRAFT],
                    'assert' => null,
                    'events' => false,
                ],
                'set_due_date' => [
                    'label' => 'Set Due Date',
                    'category' => 'configuration',
                    'method' => 'setDueDate',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'apply_number' => [
                    'label' => 'Apply Number',
                    'category' => 'configuration',
                    'method' => 'applyNumber',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'create_invitations' => [
                    'label' => 'Create Invitations',
                    'category' => 'configuration',
                    'method' => 'createInvitations',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
            ],

            'quote' => [
                'mark_sent' => [
                    'label' => 'Mark Sent',
                    'category' => 'status',
                    'method' => 'markSent',
                    'guard' => ['status_id', '=', Quote::STATUS_DRAFT],
                    'assert' => ['status_id', '=', Quote::STATUS_SENT],
                    'events' => true,
                ],
                'approve' => [
                    'label' => 'Approve',
                    'category' => 'status',
                    'method' => 'approve',
                    'guard' => ['status_id', '=', Quote::STATUS_SENT],
                    'assert' => ['status_id', '=', Quote::STATUS_APPROVED],
                    'events' => true,
                ],
                'approve_no_conversion' => [
                    'label' => 'Approve (No Conversion)',
                    'category' => 'status',
                    'method' => 'approveWithNoConversion',
                    'guard' => ['status_id', '=', Quote::STATUS_SENT],
                    'assert' => ['status_id', '=', Quote::STATUS_APPROVED],
                    'events' => true,
                ],
                'reject' => [
                    'label' => 'Reject',
                    'category' => 'status',
                    'method' => 'reject',
                    'guard' => ['status_id', 'in', [Quote::STATUS_SENT, Quote::STATUS_APPROVED]],
                    'assert' => null,
                    'events' => true,
                ],
                'send_email' => [
                    'label' => 'Send Email',
                    'category' => 'communication',
                    'method' => 'sendEmail',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                    'pre_call' => 'markSent',
                ],
                'apply_number' => [
                    'label' => 'Apply Number',
                    'category' => 'configuration',
                    'method' => 'applyNumber',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'create_invitations' => [
                    'label' => 'Create Invitations',
                    'category' => 'configuration',
                    'method' => 'createInvitations',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'fill_defaults' => [
                    'label' => 'Fill Defaults',
                    'category' => 'configuration',
                    'method' => 'fillDefaults',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'delete_pdf' => [
                    'label' => 'Delete PDF',
                    'category' => 'document',
                    'method' => 'deletePdf',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
            ],

            'credit' => [
                'mark_sent' => [
                    'label' => 'Mark Sent',
                    'category' => 'status',
                    'method' => 'markSent',
                    'guard' => ['status_id', '=', Credit::STATUS_DRAFT],
                    'assert' => ['status_id', '=', Credit::STATUS_SENT],
                    'events' => true,
                ],
                'mark_paid' => [
                    'label' => 'Mark Paid',
                    'category' => 'status',
                    'method' => 'markPaid',
                    'guard' => null,
                    'assert' => null,
                    'events' => true,
                ],
                'send_email' => [
                    'label' => 'Send Email',
                    'category' => 'communication',
                    'method' => 'sendEmail',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'apply_number' => [
                    'label' => 'Apply Number',
                    'category' => 'configuration',
                    'method' => 'applyNumber',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'create_invitations' => [
                    'label' => 'Create Invitations',
                    'category' => 'configuration',
                    'method' => 'createInvitations',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'fill_defaults' => [
                    'label' => 'Fill Defaults',
                    'category' => 'configuration',
                    'method' => 'fillDefaults',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'delete_pdf' => [
                    'label' => 'Delete PDF',
                    'category' => 'document',
                    'method' => 'deletePdf',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
            ],

            'payment' => [
                'send_email' => [
                    'label' => 'Send Receipt',
                    'category' => 'communication',
                    'method' => 'sendEmail',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'apply_number' => [
                    'label' => 'Apply Number',
                    'category' => 'configuration',
                    'method' => 'applyNumber',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'reverse' => [
                    'label' => 'Reverse Payment',
                    'category' => 'status',
                    'method' => 'reversePayment',
                    'guard' => null,
                    'assert' => null,
                    'events' => true,
                ],
            ],

            'recurring_invoice' => [
                'start' => [
                    'label' => 'Start / Activate',
                    'category' => 'status',
                    'method' => 'start',
                    'guard' => ['status_id', 'in', [RecurringInvoice::STATUS_DRAFT, RecurringInvoice::STATUS_PAUSED]],
                    'assert' => ['status_id', '=', RecurringInvoice::STATUS_ACTIVE],
                    'events' => true,
                ],
                'stop' => [
                    'label' => 'Pause',
                    'category' => 'status',
                    'method' => 'stop',
                    'guard' => ['status_id', '=', RecurringInvoice::STATUS_ACTIVE],
                    'assert' => ['status_id', '=', RecurringInvoice::STATUS_PAUSED],
                    'events' => true,
                ],
                'send_now' => [
                    'label' => 'Send Now',
                    'category' => 'communication',
                    'method' => 'sendNow',
                    'guard' => null,
                    'assert' => null,
                    'events' => true,
                ],
                'increase_price' => [
                    'label' => 'Increase Price',
                    'category' => 'billing',
                    'method' => 'increasePrice',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                    'args' => [
                        'percentage' => ['type' => 'number', 'label' => 'Percentage', 'required' => true],
                    ],
                ],
                'update_price' => [
                    'label' => 'Update Prices from Products',
                    'category' => 'billing',
                    'method' => 'updatePrice',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'apply_number' => [
                    'label' => 'Apply Number',
                    'category' => 'configuration',
                    'method' => 'applyNumber',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'create_invitations' => [
                    'label' => 'Create Invitations',
                    'category' => 'configuration',
                    'method' => 'createInvitations',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
            ],

            'purchase_order' => [
                'mark_sent' => [
                    'label' => 'Mark Sent',
                    'category' => 'status',
                    'method' => 'markSent',
                    'guard' => null,
                    'assert' => null,
                    'events' => true,
                ],
                'send_email' => [
                    'label' => 'Send Email',
                    'category' => 'communication',
                    'method' => 'sendEmail',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'add_to_inventory' => [
                    'label' => 'Add to Inventory',
                    'category' => 'billing',
                    'method' => 'add_to_inventory',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'expense' => [
                    'label' => 'Create Expense from PO',
                    'category' => 'billing',
                    'method' => 'expense',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                    'produces_entity' => true,
                ],
                'apply_number' => [
                    'label' => 'Apply Number',
                    'category' => 'configuration',
                    'method' => 'applyNumber',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
                'create_invitations' => [
                    'label' => 'Create Invitations',
                    'category' => 'configuration',
                    'method' => 'createInvitations',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
            ],

            'client' => [
                'apply_number' => [
                    'label' => 'Apply Number',
                    'category' => 'configuration',
                    'method' => 'applyNumber',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
            ],

            'vendor' => [
                'apply_number' => [
                    'label' => 'Apply Number',
                    'category' => 'configuration',
                    'method' => 'applyNumber',
                    'guard' => null,
                    'assert' => null,
                    'events' => false,
                ],
            ],
        ];
    }

    /**
     * Lifecycle operations available on all entities.
     * Dispatched through the entity's repository, not service().
     */
    public static function lifecycleOperations(): array
    {
        return [
            'archive' => ['label' => 'Archive', 'category' => 'lifecycle'],
            'restore' => ['label' => 'Restore', 'category' => 'lifecycle'],
            'delete' => ['label' => 'Delete', 'category' => 'lifecycle'],
        ];
    }

    public static function get(string $entityType, string $operation): ?array
    {
        return self::operations()[$entityType][$operation] ?? null;
    }

    public static function getLifecycle(string $operation): ?array
    {
        return self::lifecycleOperations()[$operation] ?? null;
    }

    /**
     * Get all operations for a given entity type, grouped by category.
     */
    public static function forEntity(string $entityType): array
    {
        $ops = self::operations()[$entityType] ?? [];
        $grouped = [];

        foreach ($ops as $key => $op) {
            $category = $op['category'];
            $grouped[$category][] = [
                'operation' => $key,
                'label' => $op['label'],
                'args' => $op['args'] ?? [],
                'guard' => $op['guard'] ? [
                    'field' => $op['guard'][0],
                    'operator' => $op['guard'][1],
                    'value' => $op['guard'][2],
                ] : null,
            ];
        }

        // Add lifecycle operations
        foreach (self::lifecycleOperations() as $key => $op) {
            $grouped['lifecycle'][] = [
                'operation' => $key,
                'label' => $op['label'],
                'args' => [],
                'guard' => null,
            ];
        }

        return $grouped;
    }
}
