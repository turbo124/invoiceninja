<?php

namespace App\Filters;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Builder;

class WorkflowRunFilters extends QueryFilters
{
    use MakesHash;

    public function filter(string $filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->where(function ($query) use ($filter) {
            $query->where('entity_type', 'like', '%'.$filter.'%')
                  ->orWhere('status', 'like', '%'.$filter.'%')
                  ->orWhere('waiting_for', 'like', '%'.$filter.'%');
        });
    }

    public function status(string $value = ''): Builder
    {
        if (strlen($value) == 0) {
            return $this->builder;
        }

        return $this->builder->where('status', $value);
    }

    public function workflow_id(string $value = ''): Builder
    {
        if (strlen($value) == 0) {
            return $this->builder;
        }

        return $this->builder->where('workflow_id', $this->decodePrimaryKey($value));
    }

    public function entity_type(string $value = ''): Builder
    {
        if (strlen($value) == 0) {
            return $this->builder;
        }

        return $this->builder->where('entity_type', $value);
    }

    public function entity_id(string $value = ''): Builder
    {
        if (strlen($value) == 0) {
            return $this->builder;
        }

        return $this->builder->where('entity_id', $this->decodePrimaryKey($value));
    }

    public function entityFilter(): Builder
    {
        return $this->builder->company();
    }
}
