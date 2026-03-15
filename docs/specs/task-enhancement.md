# Task Enhancement Specification

## Overview
Transform tasks from basic time-tracking entries into actionable work items with priorities, deadlines, checklists, and dependencies.

## Database Migration

New migration: `database/migrations/2026_xx_xx_add_task_enhancements.php`

```php
Schema::table('tasks', function (Blueprint $table) {
    $table->unsignedSmallInteger('priority_id')->default(0);     // 0=none, 1=low, 2=medium, 3=high, 4=urgent
    $table->date('due_date')->nullable();                         // Task-level deadline
    $table->text('checklist')->nullable();                        // JSON array of checklist items
    $table->string('blocked_by')->nullable();                     // Comma-separated task hashed IDs
    $table->index('priority_id');
    $table->index('due_date');
});
```

## Model Changes (Task.php)

Add to `$fillable`: `'priority_id', 'due_date', 'checklist', 'blocked_by'`

Add to `$casts`: `'checklist' => 'array'`

Add constants:
```php
public const PRIORITY_NONE = 0;
public const PRIORITY_LOW = 1;
public const PRIORITY_MEDIUM = 2;
public const PRIORITY_HIGH = 3;
public const PRIORITY_URGENT = 4;
```

Add to `$bulk_update_columns`: `'priority_id', 'due_date'`

New methods:
```php
public function isOverdue(): bool
public function isBlocked(): bool
public function checklistProgress(): array
```

## Checklist Data Structure

```json
[
    {"id": "uuid-1", "description": "Design mockup", "checked": false, "sort_order": 0},
    {"id": "uuid-2", "description": "Write tests", "checked": true, "sort_order": 1}
]
```

## Request Validation

```php
'priority_id' => 'sometimes|integer|in:0,1,2,3,4',
'due_date' => 'sometimes|nullable|date:Y-m-d',
'checklist' => 'sometimes|nullable|array',
'checklist.*.id' => 'required|string',
'checklist.*.description' => 'required|string|max:500',
'checklist.*.checked' => 'required|boolean',
'checklist.*.sort_order' => 'sometimes|integer',
'blocked_by' => 'sometimes|nullable|string',
```

## Transformer Changes (TaskTransformer.php)

Add to transform() return:
```php
'priority_id' => (int) $task->priority_id,
'due_date' => $task->due_date ?: '',
'checklist' => $task->checklist ?: [],
'blocked_by' => $task->blocked_by ?: '',
'is_overdue' => (bool) $task->isOverdue(),
```

## Filter Enhancements (TaskFilters.php)

New methods: `priority()`, `due_before()`, `overdue()`

## Files to Modify

- `database/migrations/new` - Add columns
- `app/Models/Task.php` - Fillable, casts, constants, helper methods
- `app/Http/Requests/Task/StoreTaskRequest.php` - Validation rules
- `app/Http/Requests/Task/UpdateTaskRequest.php` - Validation rules
- `app/Transformers/TaskTransformer.php` - New fields
- `app/Filters/TaskFilters.php` - New filters
- `app/Export/CSV/TaskExport.php` - New export columns
- `app/Factory/TaskFactory.php` - Defaults
