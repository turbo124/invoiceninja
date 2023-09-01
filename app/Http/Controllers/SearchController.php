<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\GenericSearchRequest;
use App\Models\Invoice;

class SearchController extends Controller
{
    public function __invoke(GenericSearchRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // $searchRequests = [
        //     [
        //     'collection' => 'invoices',
        //     'q' => $request->input('search')
        //     ],
        //     [
        //     'collection' => 'clients',
        //     'q' => $request->input('search')
        //     ]
        // ];

        $searchRequests = collect(['invoice','client','quote','credit','payment'])->map(function ($collection) use ($request, $user) {
            
            if($user->hasPermission('view_all') || $user->hasPermission('view_' . $collection)) {
                return [
                    'collection' => $collection.'s',
                    'q' => $request->input('search')
                ];
            }
            else {
                return [
                    'collection' => $collection,
                    'q' => $request->input('search'),
                    'user_id' => $user->id
                ];
            }

        })->toArray();

        $query = Invoice::search('')->searchMulti($searchRequests)->paginateRaw();
    
        nlog($query);

        return response()->json($query, 200);
    }
}
