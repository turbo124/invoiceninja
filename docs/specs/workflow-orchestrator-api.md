# Workflow Orchestrator - Full Technical Specification

## Overview

A stateful workflow orchestrator that chains entity operations into multi-step business processes. Every operation the system supports (mark sent, auto-bill, convert, archive, etc.) is available as a workflow action. Each workflow is a blueprint defining steps; each workflow run is an execution instance tied to a real entity.

---

## 1. Database Schema

### workflows table

```sql
CREATE TABLE workflows (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id       INT UNSIGNED NOT NULL,
    user_id          INT UNSIGNED NOT NULL,
    name             VARCHAR(255) NOT NULL,
    description      TEXT NULL,
    trigger_entity   VARCHAR(50) NOT NULL,
    trigger_event    VARCHAR(50) NOT NULL,
    trigger_conditions TEXT NULL,           -- JSON array of condition objects
    steps            TEXT NOT NULL,          -- JSON array of step definitions
    is_deleted       TINYINT(1) DEFAULT 0,
    is_template      TINYINT(1) DEFAULT 0,
    runs_count       INT UNSIGNED DEFAULT 0,
    last_run_at      TIMESTAMP(6) NULL,
    created_at       TIMESTAMP(6) NULL,
    updated_at       TIMESTAMP(6) NULL,
    deleted_at       TIMESTAMP(6) NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_workflows_trigger (company_id, trigger_entity, trigger_event, deleted_at),
    INDEX idx_workflows_deleted (company_id, deleted_at)
);
```

### workflow_runs table

```sql
CREATE TABLE workflow_runs (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id      INT UNSIGNED NOT NULL,
    company_id       INT UNSIGNED NOT NULL,
    user_id          INT UNSIGNED NOT NULL,
    entity_type      VARCHAR(191) NOT NULL,  -- FQCN of trigger entity
    entity_id        INT UNSIGNED NOT NULL,
    current_step_id  VARCHAR(50) NULL,
    status           VARCHAR(20) DEFAULT 'active',
    waiting_for      VARCHAR(100) NULL,      -- event pattern or '__timer__'
    waiting_since    TIMESTAMP(6) NULL,
    wait_until       TIMESTAMP(6) NULL,
    context          TEXT NULL,               -- JSON: accumulated entity IDs
    step_history     TEXT NULL,               -- JSON: array of step log entries
    error_message    TEXT NULL,
    completed_at     TIMESTAMP(6) NULL,
    created_at       TIMESTAMP(6) NULL,
    updated_at       TIMESTAMP(6) NULL,
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    INDEX idx_runs_status (company_id, status),
    INDEX idx_runs_entity (entity_type, entity_id),
    INDEX idx_runs_waiting (status, waiting_for),
    INDEX idx_runs_timer (status, wait_until)
);
```

**Note:** `company_id` and `user_id` use `INT UNSIGNED` (via `$table->unsignedInteger()`) to match the existing Invoice Ninja schema conventions for these foreign keys.

---

## 2. Models

### Workflow

- **Fillable:** name, description, trigger_entity, trigger_event, trigger_conditions, steps, is_template
- **Casts:** trigger_conditions => array, steps => array, is_deleted => boolean, is_template => boolean, last_run_at => datetime, created_at => timestamp, updated_at => timestamp, deleted_at => timestamp
- **Relationships:** company (belongsTo), user (belongsTo), runs (hasMany WorkflowRun), activeRuns (hasMany, filtered)
- **Helper methods:**
  - `findStep(string $stepId): ?array` — locate step by ID in steps array
  - `firstStep(): ?array` — return steps[0]
  - `nextStep(string $currentStepId): ?array` — follow explicit `next` reference or sequential order

### WorkflowRun

- **Fillable:** workflow_id, company_id, user_id, entity_type, entity_id, current_step_id, status, waiting_for, waiting_since, wait_until, context, step_history, error_message, completed_at
- **Casts:** context => array, step_history => array, waiting_since => datetime, wait_until => datetime, completed_at => datetime, created_at => timestamp, updated_at => timestamp
- **Relationships:** workflow (belongsTo), company (belongsTo), user (belongsTo)
- **Status constants:** STATUS_ACTIVE, STATUS_WAITING, STATUS_COMPLETED, STATUS_FAILED, STATUS_CANCELLED, STATUS_TIMED_OUT
- **Helper methods:**
  - `entity(): ?BaseModel` — load the trigger entity by entity_type/entity_id
  - `isActive(): bool` — true if active or waiting
  - `isTerminal(): bool` — true if completed, failed, cancelled, or timed_out
  - `logStep(array $step, string $status, ?array $result, ?string $error): void` — append to step_history
  - `mergeContext(array $data): void` — merge key-value pairs into context

---

## 3. Transformers

### WorkflowTransformer

```json
{
    "id": "hashed",
    "user_id": "hashed",
    "name": "string",
    "description": "string",
    "trigger_entity": "string",
    "trigger_event": "string",
    "trigger_conditions": [],
    "steps": [],
    "is_deleted": false,
    "is_template": false,
    "runs_count": 0,
    "last_run_at": null,
    "created_at": 1710410400,
    "updated_at": 1710410400,
    "archived_at": 0
}
```

### WorkflowRunTransformer

```json
{
    "id": "hashed",
    "workflow_id": "hashed",
    "user_id": "hashed",
    "entity_type": "Invoice",
    "entity_id": "hashed",
    "entity_hashed_id": "hashed",
    "current_step_id": "step_3",
    "status": "waiting",
    "waiting_for": "quote.approved|quote.rejected",
    "waiting_since": 1710410400,
    "wait_until": 1713002400,
    "context": {"trigger": 42, "quote": 89},
    "step_history": [],
    "error_message": "",
    "completed_at": null,
    "created_at": 1710410400,
    "updated_at": 1710410400
}
```

---

## 4. API Endpoints

### Workflow CRUD
```
GET    /api/v1/workflows                    — List workflows (filtered)
POST   /api/v1/workflows                    — Create workflow
GET    /api/v1/workflows/{id}               — Show workflow
PUT    /api/v1/workflows/{id}               — Update workflow
DELETE /api/v1/workflows/{id}               — Delete workflow
POST   /api/v1/workflows/bulk               — Bulk: archive, restore, delete, cancel_runs
```

### Templates
```
GET    /api/v1/workflows/templates           — List built-in + user templates
POST   /api/v1/workflows/from_template       — Create workflow from template (template_id or template_key)
```

### Runs
```
GET    /api/v1/workflow_runs                 — List runs (filtered by status, workflow, entity)
GET    /api/v1/workflow_runs/{id}            — Show run with step_history
POST   /api/v1/workflow_runs/{id}/cancel     — Cancel active/waiting run
POST   /api/v1/workflow_runs/{id}/retry      — Retry failed run from current step
POST   /api/v1/workflow_runs/{id}/advance    — Admin: skip past wait step
```

### Metadata
```
GET    /api/v1/workflows/metadata/triggers    — Available trigger entities + events
GET    /api/v1/workflows/metadata/actions     — Available action types + params schemas
GET    /api/v1/workflows/metadata/operations  — Full operation registry (entity → operations)
GET    /api/v1/workflows/metadata/fields      — Available fields per entity for conditions/branches
```

---

## 5. Step Types

| Type | Description | Advances When |
|------|-------------|---------------|
| `action` | Execute an operation or complex action | Immediately after execution |
| `wait_for_event` | Pause until a domain event fires | Matching event fires on the relevant entity |
| `wait_delay` | Pause for a duration | `wait_until` timestamp reached (cron-checked) |
| `branch` | Evaluate conditions, jump to target step | Immediately based on condition evaluation |
| `end` | Terminate workflow run | Terminal — run marked completed |

---

## 6. Step Definition Schema

```typescript
interface WorkflowStep {
    id: string;                         // Unique within workflow (e.g., "step_1")
    name: string;                       // Display label
    type: 'action' | 'wait_for_event' | 'wait_delay' | 'branch' | 'end';
    position: {x: number, y: number};   // Canvas coordinates for visual editor

    // Action steps
    action?: string;                    // Action type key (see Section 7)
    params?: Record<string, any>;       // Action-specific parameters
    output_key?: string;                // Store result entity_id in context[key]
    on_guard_fail?: 'skip' | 'stop' | string; // What to do when pre-condition not met (default: skip)
    on_error?: 'stop' | string;         // What to do on execution failure (default: stop). Value can be step_id.
    max_retries?: number;               // Max automatic retries for transient failures (default: 0)

    // Wait for event steps
    event?: string;                     // Event pattern: "entity.event" or pipe-separated "a|b"
    satisfied_when?: {                  // Pre-check: if entity already matches, skip wait
        field: string;                  // Field reference: "$entity.field_name"
        operator: string;              // =, !=, >, >=, <, <=, contains, starts_with, is_empty
        value: any;
    };
    timeout_days?: number;              // Max wait duration
    on_timeout?: string;                // Step ID to jump to on timeout

    // Wait delay steps
    delay_days?: number;
    delay_hours?: number;

    // Branch steps
    conditions?: BranchCondition[];     // Evaluated in order, first match wins
    default_next?: string;              // Fallback step if no condition matches

    // End steps
    end_status?: string;                // Custom status label (default: "completed")

    // Navigation (all step types)
    next?: string;                      // Explicit next step ID (overrides sequential order)

    // UI metadata
    color?: string;
    notes?: string;
}

interface BranchCondition {
    label: string;
    if: {
        field: string;      // Field reference: "$entity.field_name"
        operator: string;   // =, !=, >, >=, <, <=, contains, starts_with, is_empty
        value: any;
    };
    goto: string;           // Step ID to jump to
}
```

---

## 7. Action System Architecture

Actions are split into two categories: **registry operations** (entity service method calls dispatched via the OperationRegistry) and **complex actions** (dedicated handler classes for multi-entity or external operations).

### 7.1 Action Type Keys

| Action Key | Category | Dispatch | Description |
|------------|----------|----------|-------------|
| `entity_operation` | registry | OperationRegistry | Any registered service method call |
| `lifecycle_operation` | registry | OperationRegistry | Archive, restore, delete via repository |
| `convert` | complex | ConvertAction | Convert entity (quote→invoice, quote→project) |
| `create_task` | complex | CreateTaskAction | Create a new task with template variables |
| `clone_to` | complex | CloneAction | Clone entity to same or different type |
| `apply_payment` | complex | ApplyPaymentAction | Apply payment to invoice(s) |
| `apply_credit` | complex | ApplyCreditAction | Apply credit to invoice |
| `send_webhook` | complex | SendWebhookAction | HTTP request to external URL |
| `notify_user` | complex | NotifyUserAction | In-app/email notification to team member |
| `assign_user` | complex | AssignUserAction | Assign user (specific or round-robin) |
| `update_field` | complex | UpdateFieldAction | Update a fillable field on an entity |
| `send_email` | complex | SendEmailAction | Send email using entity invitation system |

### 7.2 Operation Registry

The `OperationRegistry` is the single source of truth mapping every entity type + operation name to its service method, with metadata for guards (pre-conditions), assertions (post-conditions), parameter requirements, and UI labels.

#### Registry Structure

```php
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
            'delete'  => ['label' => 'Delete',  'category' => 'lifecycle'],
        ];
    }

    public static function get(string $entityType, string $operation): ?array { ... }
}
```

#### Registry Entry Fields

| Field | Type | Description |
|-------|------|-------------|
| `label` | string | Human-readable name for UI |
| `category` | string | Grouping: status, billing, communication, document, configuration, lifecycle |
| `method` | string | Service method name to call: `$entity->service()->{method}()->save()` |
| `guard` | array\|null | Pre-condition: `[field, operator, value]`. If fails, step is skipped (not an error). |
| `assert` | array\|null | Post-condition: `[field, operator, value]`. Checked on entity after `->save()` returns. `$pre.field` references the value before execution. |
| `events` | bool | Whether the service call fires domain events (affects cascading workflows) |
| `pre_call` | string\|null | Optional service method to call before the main method (e.g., `markSent` before `sendEmail`) |
| `args` | array\|null | Parameter schema for operations that require arguments |
| `produces_entity` | bool | Whether the operation creates a new entity (for context storage) |

### 7.3 Step Definition for Registry Operations

```json
{
    "id": "step_1",
    "name": "Mark Invoice Sent",
    "type": "action",
    "action": "entity_operation",
    "params": {
        "entity_ref": "$trigger",
        "operation": "mark_sent",
        "args": {}
    },
    "on_guard_fail": "skip",
    "on_error": "stop",
    "max_retries": 0,
    "output_key": null,
    "next": "step_2"
}
```

For lifecycle operations:

```json
{
    "id": "step_5",
    "name": "Archive Invoice",
    "type": "action",
    "action": "lifecycle_operation",
    "params": {
        "entity_ref": "$trigger",
        "operation": "archive"
    }
}
```

### 7.4 Complex Action Params Schemas

These remain as dedicated handler classes with their own parameter schemas:

| Action | Params |
|--------|--------|
| `convert` | `from` (select: quote), `to` (select: invoice, project) |
| `create_task` | `description` (string, supports_variables), `project_ref` (entity_reference), `assigned_user_id` (user_select), `due_date_offset` (number), `status_id` (select), `priority_id` (select) |
| `clone_to` | `entity_ref` (entity_reference), `target_type` (select: invoice, quote, credit, recurring_invoice) |
| `apply_payment` | `invoice_ref` (entity_reference), `amount` (number\|"full"), `reference` (string) |
| `apply_credit` | `credit_ref` (entity_reference), `invoice_ref` (entity_reference), `amount` (number\|"full") |
| `send_webhook` | `url` (url), `method` (select: POST, PUT, GET), `headers` (key-value) |
| `notify_user` | `to` (select: assigned_user, creator, specific_user, all_admins), `user_id` (user_select), `message` (textarea, supports_variables) |
| `assign_user` | `entity_ref` (entity_reference), `strategy` (select: specific, round_robin), `user_id` (user_select) |
| `update_field` | `entity_ref` (entity_reference), `field` (field_select), `value` (dynamic) |
| `send_email` | `entity_ref` (entity_reference), `template` (select: invoice, quote, credit, etc.) |

---

## 8. Execution Lifecycle

### 8.1 Event Flow

```
1. Laravel event fires (e.g., InvoiceWasPaid)
2. AdvanceWorkflows listener catches event
3. ProcessWorkflowEvent job dispatched (queued, 2-second delay)
4. WorkflowEngine::onEvent() called
5. Finds matching workflows by company_id + trigger_entity + trigger_event (archived/soft-deleted workflows are excluded automatically)
6. Evaluates trigger_conditions (matchAll or matchAny)
7. Starts new WorkflowRun for each matching workflow
8. Also checks for waiting runs that match this event and resumes them
```

### 8.2 Execution Loop

`WorkflowEngine::advanceRun()` runs a while loop (max 50 iterations) that processes steps until hitting a wait, end, or failure:

```
while status == ACTIVE and steps < 50:
    step = workflow.findStep(run.current_step_id)

    match step.type:
        action         → executeAction()    → moves to next step
        wait_for_event → enterWait()        → sets STATUS_WAITING, pauses loop
        wait_delay     → enterDelay()       → sets STATUS_WAITING, pauses loop
        branch         → evaluateBranch()   → jumps to matching step
        end            → endRun()           → sets STATUS_COMPLETED
```

### 8.3 Action Execution (EntityOperationAction)

This is the core dispatch for registry operations. The lifecycle for every `entity_operation` step:

```
1. RESOLVE — Resolve entity from params.entity_ref via ContextResolver
2. LOOKUP  — Find operation in OperationRegistry by entity type + operation name
3. GUARD   — Check pre-condition against entity state
               If guard fails → skip or route to on_failure step (NOT an error)
4. SNAPSHOT — Capture pre-execution state of assertion fields
5. PRE-CALL — If registry defines pre_call, execute it first (e.g., markSent before sendEmail)
6. EXECUTE — Call $entity->service()->{method}()->save()
               Returns the entity object itself
7. ASSERT  — Check post-condition on returned entity
               Compare against snapshot for relative assertions ($pre.balance)
               If assertion fails → classify as ASSERTION_FAILED
8. RESULT  — Return result array with action, entity_type, entity_id
               If output_key set and produces_entity, store in context
```

```php
class EntityOperationAction implements WorkflowActionInterface
{
    public function execute(array $params, array $context, WorkflowRun $run, Company $company): ?array
    {
        // 1. Resolve
        $entity = ContextResolver::resolveEntity($params['entity_ref'] ?? '$trigger', $context, $run);
        if (!$entity) {
            throw new WorkflowOperationException(
                "Cannot resolve entity: {$params['entity_ref']}",
                OperationFailureType::PERMANENT
            );
        }

        // 2. Lookup
        $entityKey = Str::snake(class_basename($entity));
        $operation = $params['operation'];
        $registry = OperationRegistry::get($entityKey, $operation);
        if (!$registry) {
            throw new WorkflowOperationException(
                "Unknown operation '{$operation}' for entity '{$entityKey}'",
                OperationFailureType::PERMANENT
            );
        }

        // 3. Guard
        if ($registry['guard'] && !$this->guardPasses($entity, $registry['guard'])) {
            throw new WorkflowOperationException(
                "Guard failed: {$registry['guard'][0]} {$registry['guard'][1]} {$registry['guard'][2]}",
                OperationFailureType::GUARD_FAILED
            );
        }

        // 4. Snapshot
        $preState = $this->capturePreState($entity, $registry['assert']);

        // 5. Pre-call
        if (!empty($registry['pre_call'])) {
            $entity = $entity->service()->{$registry['pre_call']}()->save();
        }

        // 6. Execute
        $method = $registry['method'];
        $args = $this->resolveArgs($registry, $params['args'] ?? []);
        $entity = $entity->service()->{$method}(...$args)->save();

        // 7. Assert
        if ($registry['assert'] && !$this->assertPasses($entity, $registry['assert'], $preState)) {
            throw new WorkflowOperationException(
                "Assertion failed after '{$operation}': expected {$registry['assert'][0]} "
                . "{$registry['assert'][1]} {$registry['assert'][2]}, "
                . "got {$entity->{$registry['assert'][0]}}",
                OperationFailureType::ASSERTION_FAILED
            );
        }

        // 8. Result
        return [
            'action' => 'entity_operation',
            'operation' => $operation,
            'entity_type' => $entityKey,
            'entity_id' => $entity->id,
        ];
    }
}
```

### 8.4 Failure Classification

```php
enum OperationFailureType: string
{
    case PERMANENT = 'permanent';             // Bad data, missing entity, invalid state — do not retry
    case TRANSIENT = 'transient';             // Network timeout, gateway error, rate limit — retryable
    case GUARD_FAILED = 'guard_failed';       // Pre-condition not met — not an error, skip step
    case ASSERTION_FAILED = 'assertion_failed'; // Operation ran but didn't achieve expected result
}
```

```php
class WorkflowOperationException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly OperationFailureType $failureType,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
```

### 8.5 Engine Error Handling

The `advanceRun()` catch block routes failures based on classification. Guard failures and execution errors are handled via **separate step-level fields** (`on_guard_fail` and `on_error`) because they represent fundamentally different situations with different user intent:

- **Guard failure** = "the entity isn't in the right state for this operation" (precondition)
- **Execution error** = "the operation ran (or tried to run) and something went wrong" (failure)

The workflow author must declare how to handle each independently.

```php
try {
    match ($step['type']) {
        'action' => $this->executeAction($run, $step, $company),
        // ...
    };
} catch (WorkflowOperationException $e) {
    match ($e->failureType) {
        OperationFailureType::GUARD_FAILED =>
            $this->routeFailure($run, $step, $e, $step['on_guard_fail'] ?? 'skip'),
        OperationFailureType::TRANSIENT =>
            $this->handleTransientFailure($run, $step, $e),
        OperationFailureType::ASSERTION_FAILED,
        OperationFailureType::PERMANENT =>
            $this->routeFailure($run, $step, $e, $step['on_error'] ?? 'stop'),
    };
} catch (\Throwable $e) {
    // Unknown/unclassified errors always route via on_error
    $this->routeFailure($run, $step, $e, $step['on_error'] ?? 'stop');
}
```

#### Failure Routing

Both `on_guard_fail` and `on_error` accept the same value types but have different defaults:

```php
private function routeFailure(WorkflowRun $run, array $step, \Throwable $e, string $action): void
{
    match ($action) {
        'skip' => $this->skipStep($run, $step, $e->getMessage()),
        'stop' => $this->failRun($run, $step, $e),
        default => $this->gotoStep($run, $action, $step, $e->getMessage()), // action is a step_id
    };
}
```

| Value | Behavior |
|-------|----------|
| `skip` | Log step as "skipped", advance to next step. Run continues. |
| `stop` | Log step as "failed", set STATUS_FAILED. Run terminates. Admin notified. |
| `{step_id}` | Log step with failure reason, jump to the specified step (error-handling branch). |

#### on_guard_fail vs on_error

| Field | Default | When it fires | Typical use |
|-------|---------|---------------|-------------|
| `on_guard_fail` | `skip` | Entity doesn't meet the operation's pre-condition (e.g., already sent, balance is zero) | `skip` — "do this if applicable, otherwise move on" |
| `on_error` | `stop` | Operation threw an exception, assertion failed, or retries exhausted | `stop` — "something is genuinely broken, halt and notify" |

This separation is critical because the same operation can mean different things in different workflows:

**Workflow A** (overdue collection) — `mark_sent` is a safety net:
```json
{"action": "entity_operation", "params": {"operation": "mark_sent"}, "on_guard_fail": "skip"}
```
"Make sure it's sent before we start reminders. If it's already sent, fine."

**Workflow B** (new invoice pipeline) — `mark_sent` is a required transition:
```json
{"action": "entity_operation", "params": {"operation": "mark_sent"}, "on_guard_fail": "stop"}
```
"This should only run on draft invoices. If it's already sent, something is wrong — stop."

**Workflow C** (with error handling branch) — route guard failure to custom logic:
```json
{"action": "entity_operation", "params": {"operation": "mark_sent"}, "on_guard_fail": "handle_already_sent"}
```
"If already sent, jump to a branch that checks why and takes different action."

#### Transient Failure

Network/gateway/rate-limit errors. Retryable with backoff. Handled separately from `on_error` because retries happen automatically before the error route is invoked.

```php
private function handleTransientFailure(WorkflowRun $run, array $step, WorkflowOperationException $e): void
{
    $maxRetries = $step['max_retries'] ?? 0;
    $retryCount = $this->getStepRetryCount($run, $step['id']);

    if ($retryCount >= $maxRetries) {
        // Exhausted retries — now route via on_error
        $this->routeFailure($run, $step, $e, $step['on_error'] ?? 'stop');
        return;
    }

    // Schedule retry with exponential backoff: 5min, 15min, 45min
    $backoffMinutes = 5 * pow(3, $retryCount);
    $run->logStep($step, 'retry_scheduled', [
        'retry_count' => $retryCount + 1,
        'next_retry_at' => now()->addMinutes($backoffMinutes)->toIso8601String(),
        'error' => $e->getMessage(),
    ]);

    $run->update([
        'status' => WorkflowRun::STATUS_WAITING,
        'waiting_for' => '__retry__',
        'waiting_since' => now(),
        'wait_until' => now()->addMinutes($backoffMinutes),
    ]);
}
```

The cron job (`WorkflowTimerCron`) already processes runs where `wait_until <= now()`. Retry runs use `waiting_for = '__retry__'` to distinguish from timer delays. When the cron picks them up, it re-executes the current step (same `current_step_id` — it was never advanced). Once retries are exhausted, the failure routes through `on_error`.

#### Permanent Failure / Unknown Error

Routed via `on_error`. Default is `stop` — run terminates and user is notified.

```php
private function failRun(WorkflowRun $run, array $step, \Throwable $e, ?string $overrideMessage = null): void
{
    $message = $overrideMessage ?? $e->getMessage();

    $run->logStep($step, 'failed', null, $message);
    $run->update([
        'status' => WorkflowRun::STATUS_FAILED,
        'error_message' => $message,
    ]);

    nlog("Workflow run {$run->id} failed at step {$step['id']}: {$message}");

    // Fire event for notification system
    event(new WorkflowRunFailed($run, $step, $message));
}
```

### 8.6 Step History Entry Structure

Each entry in `step_history` JSON array:

```json
{
    "step_id": "step_1",
    "step_name": "Mark Invoice Sent",
    "step_type": "action",
    "status": "completed",
    "started_at": 1710410400,
    "completed_at": 1710410402,
    "result": {
        "action": "entity_operation",
        "operation": "mark_sent",
        "entity_type": "invoice",
        "entity_id": 42
    },
    "error": null
}
```

Possible `status` values: `started`, `completed`, `waiting`, `failed`, `skipped`, `retry_scheduled`, `timed_out`, `guard_failed`.

### 8.7 Context Accumulation

Context starts with the trigger entity and accumulates entity references as steps produce output:

```json
// After trigger (invoice created):
{"trigger": 42, "invoice": 42}

// After convert quote→invoice step with output_key "new_invoice":
{"trigger": 89, "quote": 89, "new_invoice": 42}

// After create_task step with output_key "task":
{"trigger": 89, "quote": 89, "new_invoice": 42, "task": 17}
```

Entity references use `$` prefix in step params: `$trigger`, `$quote`, `$new_invoice`, `$task`.

---

## 9. Waiting & Timer System

### 9.1 Wait for Event

```json
{
    "type": "wait_for_event",
    "event": "quote.approved|quote.rejected",
    "satisfied_when": {"field": "$quote.status_id", "operator": "in", "value": [4, 6]},
    "timeout_days": 3,
    "on_timeout": "followup_step"
}
```

- **Pre-check:** On entering a `wait_for_event` step, the engine evaluates `satisfied_when` (if present) against the current entity state. If the condition is already true (e.g., the quote was approved before the run reached this step), the wait is skipped and the run advances immediately. This handles out-of-order events without requiring event journaling.
- Sets `status = waiting`, `waiting_for = "quote.approved|quote.rejected"`
- When any matching event fires, `WorkflowEngine::onEvent()` finds waiting runs via the `idx_runs_waiting` index
- Event matching verifies the entity is in the run's context (same entity, not a different quote)
- If `timeout_days` elapses without a match → cron advances to `on_timeout` step or marks `STATUS_TIMED_OUT`

#### satisfied_when check (enterWait)

```php
private function enterWait(WorkflowRun $run, array $step, Company $company): void
{
    // Pre-check: if the entity already satisfies the wait condition, skip it
    if (!empty($step['satisfied_when'])) {
        $fieldValue = ContextResolver::resolveField(
            $step['satisfied_when']['field'],
            $run->context ?? [],
            $run
        );

        if ($this->evaluateCondition($fieldValue, $step['satisfied_when']['operator'], $step['satisfied_when']['value'])) {
            $run->logStep($step, 'skipped', ['reason' => 'satisfied_when already met']);
            $this->moveToNextStep($run, $step);
            return;
        }
    }

    // Normal wait entry — park the run
    $waitData = [
        'status' => WorkflowRun::STATUS_WAITING,
        'waiting_for' => $step['event'],
        'waiting_since' => now(),
    ];

    if (!empty($step['timeout_days'])) {
        $waitData['wait_until'] = now()->addDays($step['timeout_days']);
    }

    $run->update($waitData);
    $run->logStep($step, 'waiting');
}
```

### 9.2 Wait Delay

```json
{
    "type": "wait_delay",
    "delay_days": 7,
    "delay_hours": 0
}
```

- Sets `status = waiting`, `waiting_for = "__timer__"`, `wait_until = now + delay`
- Cron job picks it up when `wait_until <= now()` and advances to next step

### 9.3 Retry Timer

Same mechanism as wait_delay, but uses `waiting_for = "__retry__"` to distinguish. When cron picks up a retry, it re-executes the current step (does not advance `current_step_id`).

### 9.4 Cron Job

`WorkflowTimerCron` runs every 15 minutes. Finds all runs with `status = 'waiting' AND wait_until <= now()`. For each:

- `__timer__` → advance to next step
- `__retry__` → re-execute current step via `advanceRun()`
- Event wait with `on_timeout` → jump to timeout handler step
- Event wait without `on_timeout` → mark `STATUS_TIMED_OUT`

---

## 10. Failure Notification

### 10.1 Automatic System Notification

Every `STATUS_FAILED` transition fires a `WorkflowRunFailed` event. A listener sends a notification to the workflow creator (`workflow.user_id`) via the existing Invoice Ninja notification system.

Notification contains:
- Workflow name
- Failed step name
- Error message
- Link to the run detail view
- "Retry" action button

### 10.2 Workflow-Level Error Handling

Workflow authors can build explicit error-handling branches using `on_failure` routing:

```json
[
    {
        "id": "bill_step",
        "type": "action",
        "action": "entity_operation",
        "params": {"entity_ref": "$trigger", "operation": "auto_bill"},
        "on_guard_fail": "skip",
        "on_error": "notify_billing_error",
        "max_retries": 2,
        "next": "success_step"
    },
    {
        "id": "notify_billing_error",
        "type": "action",
        "action": "notify_user",
        "params": {
            "to": "assigned_user",
            "message": "Auto-billing failed for {{invoice.number}}. Please process manually."
        },
        "next": "end_failed"
    },
    {
        "id": "end_failed",
        "type": "end",
        "end_status": "billing_failed"
    }
]
```

---

## 11. Trigger Events

### 11.1 Currently Wired Events

These Laravel events are mapped in `AdvanceWorkflows` listener and dispatch `ProcessWorkflowEvent`:

| Entity | Event | Laravel Event Class |
|--------|-------|-------------------|
| invoice | created | InvoiceWasCreated |
| invoice | sent | InvoiceWasEmailed |
| invoice | paid | InvoiceWasPaid |
| invoice | viewed | InvoiceWasViewed |
| invoice | cancelled | InvoiceWasCancelled |
| invoice | reversed | InvoiceWasReversed |
| quote | created | QuoteWasCreated |
| quote | sent | QuoteWasEmailed |
| quote | approved | QuoteWasApproved |
| client | created | ClientWasCreated |
| client | updated | ClientWasUpdated |
| payment | completed | PaymentCompleted |
| payment | failed | PaymentFailed |
| payment | refunded | PaymentWasRefunded |
| expense | created | ExpenseWasCreated |
| expense | updated | ExpenseWasUpdated |

### 11.2 Events to Add

| Entity | Event | Laravel Event Class |
|--------|-------|-------------------|
| quote | rejected | QuoteWasRejected (if exists, or add) |
| quote | converted | (fire after ConvertQuote service runs) |
| credit | created | CreditWasCreated (already wired but not mapped) |
| credit | sent | (when credit emailed) |
| task | created | TaskWasCreated |
| task | updated | TaskWasUpdated |
| project | created | ProjectWasCreated |
| project | updated | ProjectWasUpdated |
| recurring_invoice | started | (when status → active) |
| recurring_invoice | paused | (when status → paused) |
| recurring_invoice | sent | (when child invoice generated) |
| purchase_order | created | PurchaseOrderWasCreated |
| purchase_order | sent | PurchaseOrderWasEmailed |
| vendor | created | VendorWasCreated |

### 11.3 Cascading Event Control

When a workflow action fires a domain event (e.g., `markSent()` fires `InvoiceWasEmailed`), that event can trigger other workflows, creating cascades. The `events` flag in the registry controls whether `->save()` or `->saveQuietly()` is used. For operations where cascading is intentional (status transitions), `events: true`. For internal housekeeping (fill_defaults, apply_number), `events: false`.

**Safety:** The 50-step execution limit in `advanceRun()` prevents infinite loops within a single run. Cross-run cascades are bounded by the 2-second dispatch delay and the fact that `ProcessWorkflowEvent` has `$tries = 2`.

---

## 12. Context Resolution

### 12.1 Entity Resolution

`ContextResolver::resolveEntity($ref, $context, $run)` resolves entity references:

- `$trigger` → loads entity from `$run->entity_type` / `$run->entity_id`
- `$quote`, `$invoice`, etc. → loads from `$context[$key]` using entity class map
- Returns `BaseModel` or `null`

### 12.2 Field Resolution

`ContextResolver::resolveField($fieldRef, $context, $run)` resolves dotted references:

- `$invoice.balance` → resolves invoice entity, returns `->balance`
- `$client.paid_to_date` → resolves client entity, returns `->paid_to_date`
- Computed fields: `days_overdue`, `budget_utilization_pct`, `days_until_expiry`

### 12.3 Template Variables

`TemplateVariableResolver::resolve($template, $context, $run)` replaces `{{entity.field}}` placeholders:

- `{{invoice.number}}` → invoice number
- `{{client.name}}` → client name
- `{{date.today}}` → current date
- `{{workflow.name}}` → workflow name
- Unresolved placeholders are left as-is

### 12.4 Entity Map

| Context Key | Model Class |
|-------------|-------------|
| invoice | App\Models\Invoice |
| quote | App\Models\Quote |
| credit | App\Models\Credit |
| client | App\Models\Client |
| project | App\Models\Project |
| task | App\Models\Task |
| expense | App\Models\Expense |
| payment | App\Models\Payment |
| recurring_invoice | App\Models\RecurringInvoice |
| purchase_order | App\Models\PurchaseOrder |
| vendor | App\Models\Vendor |

---

## 13. Branch Conditions

Branch steps evaluate conditions in order and jump to the first matching step:

```json
{
    "type": "branch",
    "conditions": [
        {
            "label": "High value",
            "if": {"field": "$invoice.amount", "operator": ">", "value": 10000},
            "goto": "premium_path"
        },
        {
            "label": "Overdue",
            "if": {"field": "$invoice.days_overdue", "operator": ">", "value": 30},
            "goto": "escalation_path"
        }
    ],
    "default_next": "standard_path"
}
```

### Supported Operators

| Operator | Numeric | String | Description |
|----------|---------|--------|-------------|
| `=` | Yes | Yes | Equal |
| `!=` | Yes | Yes | Not equal |
| `>` | Yes | No | Greater than |
| `>=` | Yes | No | Greater than or equal |
| `<` | Yes | No | Less than |
| `<=` | Yes | No | Less than or equal |
| `contains` | No | Yes | String contains |
| `starts_with` | No | Yes | String starts with |
| `is_empty` | No | Yes | Value is empty/null |
| `in` | Yes | Yes | Value in array (for guards) |

### Trigger Conditions

Trigger conditions on the workflow itself support the same operators. Multiple conditions can be combined with `matchAll` (AND, default) or `matchAny` (OR) via the `trigger_conditions_match_all` flag.

---

## 14. API Metadata Endpoints

### GET /api/v1/workflows/metadata/triggers

Returns available trigger entities and their events (see Section 11).

### GET /api/v1/workflows/metadata/actions

Returns complex action types with their parameter schemas (see Section 7.4).

### GET /api/v1/workflows/metadata/operations

Returns the full operation registry grouped by entity type and category:

```json
{
    "data": {
        "invoice": {
            "status": [
                {
                    "operation": "mark_sent",
                    "label": "Mark Sent",
                    "args": [],
                    "guard": {"field": "status_id", "operator": "=", "value": 1}
                },
                {
                    "operation": "mark_paid",
                    "label": "Mark Paid",
                    "args": [],
                    "guard": {"field": "balance", "operator": ">", "value": 0}
                }
            ],
            "billing": [ ... ],
            "communication": [ ... ],
            "lifecycle": [
                {"operation": "archive", "label": "Archive", "args": []},
                {"operation": "restore", "label": "Restore", "args": []},
                {"operation": "delete", "label": "Delete", "args": []}
            ]
        },
        "quote": { ... },
        ...
    }
}
```

### GET /api/v1/workflows/metadata/fields

Returns available fields per entity type for use in trigger conditions and branch steps (see Section 13).

---

## 15. Feature Gating

- `Account::FEATURE_WORKFLOWS` constant for plan checking
- `MODULE_WORKFLOWS = 32768` (2^15) bitwise flag for company toggle
- Add `'company.workflows'` to `first_load` array in `BaseController`

---

## 16. Pre-Built Templates

### Overdue Collection

Trigger: Invoice sent. Wait 7 days → Reminder 1 → Wait 14 days → Reminder 2 → Wait 30 days → Notify account manager.

### Quote Follow-up

Trigger: Quote sent. Wait for response (3 day timeout) → If responded: end. If timeout: follow-up email → notify sales rep.

### Client Onboarding

Trigger: Client created. Create onboarding task → Assign account manager (round-robin) → Notify all admins.

### Auto-Bill Overdue (new)

Trigger: Invoice sent. Wait 7 days → Branch on balance > 0 → Auto-bill (with retry, on_failure → notify) → End.

### Purchase Order to Expense (new)

Trigger: Purchase order sent. Wait for approval event → Add to inventory → Create expense from PO → Notify assigned user → End.

---

## 17. File Structure

```
app/
  Services/
    Workflow/
      WorkflowEngine.php                    — Core execution loop, event handling, timer processing
      OperationRegistry.php                 — Entity operation definitions (NEW)
      ContextResolver.php                   — Entity + field resolution from context
      TemplateVariableResolver.php          — {{variable}} placeholder replacement
      WorkflowMetadata.php                  — Triggers, actions, fields, templates for UI
      WorkflowOperationException.php        — Classified exception with OperationFailureType (NEW)
      OperationFailureType.php              — Enum: permanent, transient, guard_failed, assertion_failed (NEW)
      Actions/
        WorkflowActionInterface.php         — Interface: execute(params, context, run, company): ?array
        EntityOperationAction.php           — Generic dispatch via OperationRegistry (NEW)
        LifecycleOperationAction.php        — Archive/restore/delete via repository (NEW)
        SendEmailAction.php                 — Send email using entity invitation system
        ConvertAction.php                   — Quote→Invoice, Quote→Project conversion
        CreateTaskAction.php                — Create task with template variables
        CloneAction.php                     — Clone entity to same/different type (NEW)
        ApplyPaymentAction.php              — Apply payment to invoice (NEW)
        ApplyCreditAction.php               — Apply credit to invoice (NEW)
        AssignUserAction.php                — Assign user (specific or round-robin)
        UpdateFieldAction.php               — Update fillable field on entity
        SendWebhookAction.php               — HTTP request to external URL
        NotifyUserAction.php                — In-app/email notification
  Models/
    Workflow.php
    WorkflowRun.php
  Events/
    Workflow/
      WorkflowRunFailed.php                 — Fired on STATUS_FAILED for notification (NEW)
  Listeners/
    Workflow/
      AdvanceWorkflows.php                  — Maps domain events → ProcessWorkflowEvent job
      NotifyWorkflowFailure.php             — Listens to WorkflowRunFailed, sends notification (NEW)
  Jobs/
    Workflow/
      ProcessWorkflowEvent.php              — Queued job: calls WorkflowEngine::onEvent()
    Cron/
      WorkflowTimerCron.php                 — Processes timed-out/delayed/retry runs
  Http/
    Controllers/
      WorkflowController.php                — CRUD, bulk, templates, metadata endpoints
      WorkflowRunController.php             — List, show, cancel, retry, advance
    Requests/
      Workflow/
        StoreWorkflowRequest.php
        UpdateWorkflowRequest.php
        ShowWorkflowRequest.php
        DestroyWorkflowRequest.php
        BulkWorkflowRequest.php
  Filters/
    WorkflowFilters.php
    WorkflowRunFilters.php
  Factory/
    WorkflowFactory.php
  Repositories/
    WorkflowRepository.php
  Transformers/
    WorkflowTransformer.php
    WorkflowRunTransformer.php
database/
  migrations/
    2026_03_14_100000_create_workflows_table.php
```

---

## 18. Execution Safety

| Concern | Mechanism |
|---------|-----------|
| Infinite loops within a run | 50-step limit in `advanceRun()` |
| Cross-run cascading | 2-second dispatch delay, `$tries = 2` on ProcessWorkflowEvent |
| Silent no-ops | Post-condition assertions detect when service methods don't change state |
| Transient failures | Automatic retry with exponential backoff (5m, 15m, 45m) up to `max_retries` |
| Permanent failures | Run terminates, error logged, admin notified |
| Stale wait states | Cron processes `wait_until` every 15 minutes |
| Entity deleted during run | `withTrashed()` on entity resolution; guard/assertion catches invalid state |
| Concurrent runs on same entity | No mutex — multiple workflows can act on the same entity. Each run is independent. |
| Job failure | `ProcessWorkflowEvent` has `$tries = 2`, `$maxExceptions = 2` — fails silently with nlog after retries exhausted |

---

## 19. Summary of Changes from Current Implementation

| Area | Current State | New State |
|------|--------------|-----------|
| Action dispatch | 7 hardcoded handler classes in `$actionHandlers` map | OperationRegistry for ~40 service operations + dedicated classes for ~8 complex actions |
| Error handling | Binary: exception = dead run, no retry | Classified failures: permanent, transient (retryable), guard_failed (skip), assertion_failed |
| Success detection | "No exception = success" | Post-condition assertions on entity state after `->save()` returns |
| Retry | Manual only (admin calls retry endpoint) | Automatic for transient failures with exponential backoff |
| User notification | None | Automatic on STATUS_FAILED via WorkflowRunFailed event |
| Guard conditions | Not implemented | Pre-condition checks per operation; `on_guard_fail` field: skip (default), stop, or goto step_id |
| Error routing | Run dies, no branching | `on_error` field (default: stop): stop, or goto step_id for error-handling branches. Separate from guard handling. |
| Entity coverage | Invoice, Quote, Client, Payment, Expense | + Credit, Recurring Invoice, Purchase Order, Task, Project, Vendor |
| Trigger events | 16 events across 5 entities | Expanded to cover all entity lifecycle events |
| Cron processing | Timer delays + event timeouts | + retry scheduling (`waiting_for = '__retry__'`) |
