<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Filters;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * SystemLogFilters.
 */
class SystemLogFilters extends QueryFilters
{
    public function type_id(int $type_id) :Builder
    {
        return $this->builder->where('type_id', $type_id);
    }

    public function category_id(int $category_id) :Builder
    {
        return $this->builder->where('category_id', $category_id);
    }

    public function event_id(int $event_id) :Builder
    {
        return $this->builder->where('event_id', $event_id);
    }

    public function client_id(int $client_id) :Builder
    {
        return $this->builder->where('client_id', $client_id);
    }

    /**
     * Filter based on search text.
     *
     * @param  string query filter
     * @return Illuminate\Database\Query\Builder
     * @deprecated
     */
    public function filter(string $filter = '') : Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder;

        // return  $this->builder->where(function ($query) use ($filter) {
        //     $query->where('vendors.name', 'like', '%'.$filter.'%')
        //                   ->orWhere('vendors.id_number', 'like', '%'.$filter.'%')
        //                   ->orWhere('vendor_contacts.first_name', 'like', '%'.$filter.'%')
        //                   ->orWhere('vendor_contacts.last_name', 'like', '%'.$filter.'%')
        //                   ->orWhere('vendor_contacts.email', 'like', '%'.$filter.'%')
        //                   ->orWhere('vendors.custom_value1', 'like', '%'.$filter.'%')
        //                   ->orWhere('vendors.custom_value2', 'like', '%'.$filter.'%')
        //                   ->orWhere('vendors.custom_value3', 'like', '%'.$filter.'%')
        //                   ->orWhere('vendors.custom_value4', 'like', '%'.$filter.'%');
        // });
    }

    /**
     * Sorts the list based on $sort.
     *
     * @param  string sort formatted as column|asc
     * @return Illuminate\Database\Query\Builder
     */
    public function sort(string $sort) : Builder
    {
        $sort_col = explode('|', $sort);

        return $this->builder->orderBy($sort_col[0], $sort_col[1]);
    }

    /**
     * Returns the base query.
     *
     * @param  int company_id
     * @return Illuminate\Database\Query\Builder
     * @deprecated
     */
    public function baseQuery(int $company_id, User $user) : Builder
    {
    }

    /**
     * Filters the query by the users company ID.
     *
     * @param $company_id The company Id
     * @return Illuminate\Database\Query\Builder
     */
    public function entityFilter()
    {

        //return $this->builder->whereCompanyId(auth()->user()->company()->id);
        return $this->builder->company();
    }
}
