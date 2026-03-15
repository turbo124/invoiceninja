# Workflow Orchestrator - API Specification

## Overview

A stateful workflow orchestrator that chains entity operations into multi-step business processes. Each workflow is a blueprint defining steps; each workflow run is an execution instance tied to a real entity.

---

## 1. Database Schema

### workflows table

```sql
CREATE TABLE workflows (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id       BIGINT UNSIGNED NOT NULL,
    user_id          BIGINT UNSIGNED NOT NULL,
    name             VARCHAR(255) NOT NULL,
    description      TEXT NULL,
    trigger_entity   VARCHAR(50) NOT NULL,
    trigger_event    VARCHAR(50) NOT NULL,
    trigger_conditions TEXT NULL,
    steps            TEXT NOT NULL,
    is_active        TINYINT(1) DEFAULT 1,
    is_deleted       TINYINT(1) DEFAULT 0,
    is_template      TINYINT(1) DEFAULT 0,
    runs_count       INT UNSIGNED DEFAULT 0,
    last_run_at      TIMESTAMP(6) NULL,
    created_at       TIMESTAMP(6) NULL,
    updated_at       TIMESTAMP(6) NULL,
    deleted_at       TIMESTAMP(6) NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_workflows_trigger (company_id, trigger_entity, trigger_event, is_active),
    INDEX idx_workflows_deleted (company_id, deleted_at)
);
```

### workflow_runs table

```sql
CREATE TABLE workflow_runs (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id      BIGINT UNSIGNED NOT NULL,
    company_id       BIGINT UNSIGNED NOT NULL,
    user_id          BIGINT UNSIGNED NOT NULL,
    entity_type      VARCHAR(100) NOT NULL,
    entity_id        BIGINT UNSIGNED NOT NULL,
    current_step_id  VARCHAR(50) NULL,
    status           VARCHAR(20) DEFAULT 'active',
    waiting_for      VARCHAR(100) NULL,
    waiting_since    TIMESTAMP(6) NULL,
    wait_until       TIMESTAMP(6) NULL,
    context          TEXT NULL,
    step_history     TEXT NULL,
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

---

## 2. Models

### Workflow

- Fillable: name, description, trigger_entity, trigger_event, trigger_conditions, steps, is_active, is_template
- Casts: trigger_conditions => array, steps => array, is_active => boolean, is_deleted => boolean, is_template => boolean
- Relationships: company, user, runs (hasMany WorkflowRun)

### WorkflowRun

- Fillable: workflow_id, entity_type, entity_id, current_step_id, status, waiting_for, waiting_since, wait_until, context, step_history, error_message, completed_at
- Casts: context => array, step_history => array, waiting_since => datetime, wait_until => datetime, completed_at => datetime
- Relationships: company, user, workflow (belongsTo)
- Statuses: active, waiting, completed, failed, cancelled, timed_out

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
    "is_active": true,
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
    "context": {"deal": 42, "quote": 89},
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
GET    /api/v1/workflows
POST   /api/v1/workflows
GET    /api/v1/workflows/{id}
PUT    /api/v1/workflows/{id}
DELETE /api/v1/workflows/{id}
POST   /api/v1/workflows/bulk
```

### Templates
```
GET    /api/v1/workflows/templates
POST   /api/v1/workflows/from_template
```

### Runs
```
GET    /api/v1/workflow_runs
GET    /api/v1/workflow_runs/{id}
POST   /api/v1/workflow_runs/{id}/cancel
POST   /api/v1/workflow_runs/{id}/retry
POST   /api/v1/workflow_runs/{id}/advance
```

### Metadata
```
GET    /api/v1/workflows/metadata/triggers
GET    /api/v1/workflows/metadata/actions
GET    /api/v1/workflows/metadata/fields
```

---

## 5. Step Types

| Type | Description | Advances When |
|------|-------------|---------------|
| action | Execute something | Immediately after execution |
| wait_for_event | Pause until event fires | Matching event fires |
| wait_delay | Pause for duration | wait_until timestamp reached |
| branch | Evaluate conditions, jump | Immediately based on conditions |
| end | Terminate workflow run | Terminal |

---

## 6. Step Definition Schema

```typescript
interface WorkflowStep {
    id: string;
    name: string;
    type: 'action' | 'wait_for_event' | 'wait_delay' | 'branch' | 'end';
    position: {x: number, y: number};
    action?: string;
    params?: Record<string, any>;
    output_key?: string;
    event?: string;
    timeout_days?: number;
    on_timeout?: string;
    delay_days?: number;
    delay_hours?: number;
    conditions?: BranchCondition[];
    default_next?: string;
    end_status?: string;
    next?: string;
    color?: string;
    notes?: string;
}
```

---

## 7. Action Types

- send_email - Send email using template
- convert - Convert entity (deal->quote, quote->invoice, quote->project)
- update_field - Update a field on an entity
- assign_user - Assign user (specific, round_robin, least_loaded)
- create_task - Create a task with description, project, assignee
- create_invoice - Create invoice from deal/project
- notify_user - Notify team member with message
- send_webhook - POST to external URL
- auto_bill - Auto-bill an invoice

---

## 8. Template Variables

Supported in action params with `supports_variables: true`:
```
{{deal.name}}, {{client.name}}, {{invoice.number}}, {{quote.amount}},
{{project.name}}, {{user.name}}, {{workflow.name}}, {{date}}
```

---

## 9. Engine Architecture

### Event Flow
1. Laravel event fires (e.g., InvoiceWasPaid)
2. AdvanceWorkflows listener catches event
3. ProcessWorkflowEvent job dispatched (queued)
4. WorkflowEngine::onEvent() called
5. Finds matching workflows by trigger_entity + trigger_event
6. Evaluates trigger_conditions
7. Starts new WorkflowRun or resumes waiting runs

### Execution Loop
WorkflowEngine::advanceRun() executes steps sequentially until hitting a wait or end:
- action -> execute handler, store output in context, move to next
- wait_for_event -> set status=waiting, pause execution
- wait_delay -> set wait_until timestamp, pause execution
- branch -> evaluate conditions, jump to matching step
- end -> set status=completed/lost/cancelled

### Timer-Based Advancement
Cron job (every 15 minutes) checks:
- workflow_runs WHERE status='waiting' AND wait_until <= now()
- Advances past delay steps or fires timeout handlers

---

## 10. Feature Gating

- Account::FEATURE_WORKFLOWS constant for plan checking
- MODULE_WORKFLOWS = 32768 (2^15) bitwise flag for company toggle
- Add 'company.workflows' to first_load array in BaseController

---

## 11. Pre-Built Templates

1. **Sales Pipeline** - Deal -> Quote -> Approval -> Invoice -> Payment -> Project
2. **Client Onboarding** - Welcome email -> Task creation -> Follow-up
3. **Overdue Collection** - Reminder 1 -> Reminder 2 -> Final notice -> Notify manager
4. **Quote Follow-up** - Send -> Wait -> Follow-up -> Expire
5. **Project Delivery** - Create tasks -> Notify team -> Invoice -> Payment -> Archive
