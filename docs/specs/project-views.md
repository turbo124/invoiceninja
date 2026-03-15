# Project Views Specification (Kanban, Gantt, Milestones)

## Overview
Enhanced project visualization with Kanban board, Gantt/timeline chart, milestones, budget burn-rate tracking, and project templates.

## Database

### projects table additions
- start_date (date, nullable)
- status_id (smallint, default 1: not_started, 2: in_progress, 3: completed, 4: on_hold, 5: cancelled)
- budgeted_cost (float, default 0)

### project_milestones table (new)
- id, company_id, project_id, user_id
- name, due_date, completed_date, sort_order
- is_completed, is_deleted, timestamps + soft deletes

### project_templates table (new)
- id, company_id, user_id, name
- template_data (JSON: project settings + task blueprints + milestones)
- is_deleted, timestamps + soft deletes

## Gantt Data Endpoint

`GET /api/v1/projects/{project}/gantt`

Returns tasks with start/end dates, progress, dependencies, plus milestones.

## Board Endpoint

`GET /api/v1/projects/{project}/board`

Returns tasks grouped by status with ordering, optimized for Kanban rendering.

## Budget Burn-Rate

`GET /api/v1/projects/{project}/burn_rate`

Returns hours (budgeted/used/remaining), cost (budget/spent/remaining), and projections (daily burn rate, projected total, over_budget flag).

## Project Templates

- Save: `POST /api/v1/project_templates` - captures project structure as reusable template
- Apply: `POST /api/v1/projects/from_template/{template_id}` - creates project with tasks/milestones

## Files to Create/Modify

- 3 new migrations
- `app/Models/ProjectMilestone.php` - New
- `app/Models/ProjectTemplate.php` - New
- `app/Models/Project.php` - Add fields + relationships
- `app/Services/Project/ProjectService.php` - burnRate(), gantt methods
- New controllers, transformers, routes
