# Workflow Orchestrator - UI Specification

## For React UI Implementation

---

## 1. Navigation

Add "Workflows" to sidebar under Settings section.
Icon: `account_tree` (Material Icons).

---

## 2. Screen Map

```
/settings/workflows                    -> Workflow List (DataTable)
/settings/workflows/create             -> Workflow Builder (visual editor)
/settings/workflows/:id/edit           -> Workflow Builder (editing existing)
/settings/workflows/:id                -> Workflow Detail (read-only + run history)
/settings/workflows/templates          -> Template Gallery
/settings/workflow_runs                -> Run Monitor (global)
/settings/workflow_runs/:id            -> Run Detail (step timeline)
```

---

## 3. Workflow List

Standard DataTable (same pattern as Webhooks, Schedules).

Columns: Name (link), Trigger (display as "When Invoice is Paid"), Status (toggle), Runs (badge), Last Run (relative time), Actions (Edit/Clone/Archive/Delete).

Top bar: + New Workflow, From Template, Filter (Active/Archived/All), Search.

Bulk actions: Activate, Deactivate, Archive, Restore, Delete.

---

## 4. Template Gallery

Card-grid layout. Each template card shows:
- Icon + name
- Flow summary (Deal -> Quote -> Invoice -> Payment)
- Trigger description
- Step count + category
- [Preview] and [Use This Template] buttons

Categories: Sales, Billing, Onboarding, Operations.

---

## 5. Workflow Builder (Visual Editor)

### Layout

Three-panel layout:
- LEFT: Trigger config (top) + Step palette (bottom)
- CENTER: Canvas area (React Flow)
- BOTTOM: Properties panel (contextual, appears when node selected)

### Trigger Panel

Entity dropdown + Event dropdown (populated from GET /api/v1/workflows/metadata/triggers).
Optional conditions with field/operator/value rows.
AND/OR toggle for condition matching.

### Step Palette

Draggable step types grouped by category (from GET /api/v1/workflows/metadata/actions):
- Actions: Send Email, Convert Entity, Assign User, Update Field, Create Task, Create Invoice, Auto-Bill, Notify, Webhook
- Waits: Wait for Event, Wait/Delay
- Flow: Branch/Decision, End Workflow

### Canvas

Nodes: Rounded rectangles with icon, name, status indicator.
Connections: Directional arrows.
Interactions: Pan (drag), zoom (scroll), click node (properties), right-click (context menu), + button on edges.

Node colors by type:
- Trigger: Blue (#3B82F6)
- Action: Green (#10B981)
- Wait Event: Amber (#F59E0B)
- Wait Delay: Amber (#F59E0B)
- Branch: Purple (#8B5CF6)
- End (completed): Grey (#6B7280)
- End (lost): Red (#EF4444)

### Properties Panel

Contextual form that changes based on selected node type.
Each action type has its own properties form matching the params_schema from the metadata endpoint.

Key interaction: Entity Reference dropdown ($context variables) dynamically computed from DAG position - only shows entities created by prior steps.

### Recommended Library

**@xyflow/react** (React Flow) for the canvas.

---

## 6. Workflow Run Monitor

DataTable showing active and recent runs.

Columns: Workflow (link), Entity (type + link), Status (color badge), Current Step, Waiting Since, Started, Actions (View/Cancel/Advance).

Status badge colors: active=Blue, waiting=Amber (pulsing), completed=Green, failed=Red, cancelled=Grey, timed_out=Orange.

Filters: Status, Workflow, Entity type.

Polling: Since no WebSocket support exists, poll GET /api/v1/workflow_runs every 10 seconds when viewing active/waiting runs.

---

## 7. Run Detail View

Step-by-step timeline with status icons:
- Completed: green check
- Waiting (current): amber hourglass
- Active/Running: blue spinner
- Failed: red X (expandable error)
- Skipped: grey skip
- Pending: grey circle
- End: flag

Shows context panel with accumulated entity references ($deal -> Deal #D-0015, $quote -> Quote #Q-0089).

Admin actions: Cancel Run, Advance Past Wait.

---

## 8. Entity Widget

On entity detail views (Invoice, Quote, Deal, etc.), show "Active Workflows" widget if the entity has runs.

API: GET /api/v1/workflow_runs?entity_type=X&entity_id=Y

---

## 9. Component Architecture

```
WorkflowModule/
  pages/
    WorkflowList.tsx, WorkflowTemplates.tsx, WorkflowBuilder.tsx,
    WorkflowDetail.tsx, WorkflowRunList.tsx, WorkflowRunDetail.tsx
  components/
    builder/
      WorkflowCanvas.tsx, TriggerConfigPanel.tsx, StepPalette.tsx,
      PropertiesPanel.tsx
      nodes/ (TriggerNode, ActionNode, WaitEventNode, WaitDelayNode, BranchNode, EndNode)
      edges/ (WorkflowEdge with + button)
      properties/ (per-action property forms)
    runs/
      RunTimeline.tsx, RunStepEntry.tsx, RunContextPanel.tsx
    shared/
      ConditionBuilder.tsx, EntityRefSelector.tsx, VariableChips.tsx, WorkflowStatusBadge.tsx
    widgets/
      EntityWorkflowWidget.tsx
  hooks/
    useWorkflowMetadata.ts, useWorkflowBuilder.ts, useWorkflowRuns.ts, useContextVariables.ts
  helpers/
    stepToNode.ts, nodeToStep.ts, validateWorkflow.ts, workflowTemplates.ts
  types/
    workflow.ts
```

---

## 10. Client-Side Validation

Before save, validate:
1. Trigger configured (entity + event selected)
2. At least one step after trigger
3. All steps connected (no orphans)
4. No circular references (DAG must be acyclic)
5. All required params filled
6. Entity references valid (only reference entities from prior steps)
7. Branch conditions complete
8. At least one End node reachable from every path
9. Wait timeouts have valid destination step_ids

Display: Red border on invalid nodes, error list panel, tooltip on hover.

---

## 11. Responsive

- Builder: Desktop only (show message on mobile)
- List/Monitor: Responsive (standard DataTable)
- Run Detail: Works on mobile (vertical timeline)
