<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class WorkflowFilters extends QueryFilters
{
    public function filter(string $filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->where(function ($query) use ($filter) {
            $query->where('name', 'like', '%'.$filter.'%')
                  ->orWhere('description', 'like', '%'.$filter.'%')
                  ->orWhere('trigger_entity', 'like', '%'.$filter.'%');
        });
    }

    public function status(string $value = ''): Builder
    {
        if (strlen($value) == 0) {
            return $this->builder;
        }

        if ($value === 'active') {
            return $this->builder->whereNull('deleted_at');
        }

        if ($value === 'archived') {
            return $this->builder->whereNotNull('deleted_at');
        }

        return $this->builder;
    }

    public function trigger(string $trigger = ''): Builder
    {
        if (strlen($trigger) == 0) {
            return $this->builder;
        }

        return $this->builder->where('trigger_entity', $trigger);
    }

    public function entityFilter(): Builder
    {
        return $this->builder->company();
    }
}
