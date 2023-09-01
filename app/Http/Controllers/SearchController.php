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

use App\Models\Invoice;
use App\Models\ClientContact;
use App\Http\Controllers\Controller;
use App\Http\Requests\Search\GenericSearchRequest;

class SearchController extends Controller
{
    private function queryKeys(string $collection): string
    {
        $query_by = '';

        return match($collection){
            'invoice' => $query_by = 'line_items,number,hashed_id',
            'client' => $query_by = 'name',
            'client_contacts' => $query_by = 'first_name',
            'quote' => $query_by = 'line_items,number,hashed_id',
            'credit' => $query_by = 'line_items,number,hashed_id',
            'payment' => $query_by = 'number,transaction_reference',
        };

        return $query_by;
    }

    public function __invoke(GenericSearchRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $searchRequests = collect(['invoice','client','quote','credit','payment','client_contacts'])->map(function ($collection) use ($request, $user) {
            
            if($user->hasPermission('view_all') || $user->hasPermission('view_' . $collection)) {
                return [
                    'collection' => $collection.'s',
                    'q' => $request->input('search'),
                    'group_by'    => 'hashed_id',
                    'group_limit' => '1',
                    'per_page' => 100,
                    'company_id' => $user->company()->id,
                    'query_by' => $this->queryKeys($collection),
                ];
            }
            else {
                return [
                    'collection' => $collection,
                    'q' => $request->input('search'),
                    'group_by'    => 'hashed_id',
                    'group_limit' => '1',
                    'user_id' => $user->id,
                    'per_page' => 100,
                    'company_id' => $user->company()->id,
                    'query_by' => $this->queryKeys($collection),
                ];
            }

        })->toArray();

        $query = ClientContact::search('')->searchMulti($searchRequests)->paginateRaw();


        // nlog($query->count());
        // nlog($query->items());
        // nlog($query->perPage());
    
        $results = $query->items();

        $invoices = $results['results'][0];
        $clients = $results['results'][1];
        $quotes = $results['results'][2];
        $credits = $results['results'][3];
        $payments = $results['results'][4];
        $client_contacts = $results['results'][5];

        if(isset($invoices['request_params']['collection_name'])) {

            nlog($invoices['request_params']['collection_name']);
            nlog($invoices['request_params']['per_page']);
            nlog($invoices['request_params']['q']);
            nlog("invoices");
        }
        else {
            nlog($invoices);
        }
        
        if(isset($clients['request_params']['collection_name'])) {
            nlog($clients['request_params']['collection_name']);
            nlog($clients['request_params']['per_page']);
            nlog($clients['request_params']['q']);
        }
        else {
            nlog("clients");
            nlog($clients);
        }

        if(isset($client_contacts['request_params']['collection_name'])) {
            nlog($client_contacts['request_params']['collection_name']);
            nlog($client_contacts['request_params']['per_page']);
            nlog($client_contacts['request_params']['q']);
        } else {
            nlog("client_contacts");
            nlog($client_contacts);
        }

        if(isset($quotes['request_params']['collection_name'])) {
            nlog($quotes['request_params']['collection_name']);
            nlog($quotes['request_params']['per_page']);
            nlog($quotes['request_params']['q']);
        }
        else {
            nlog("quotes");
            nlog($quotes);
        }
        if(isset($credits['request_params']['collection_name'])) {
        
            nlog($credits['request_params']['collection_name']);
            nlog($credits['request_params']['per_page']);
            nlog($credits['request_params']['q']);
        }
        else {
            nlog("credits");
            nlog($credits);
        }
        if(isset($payments['request_params']['collection_name'])) {

                nlog($payments['request_params']['collection_name']);
                nlog($payments['request_params']['per_page']);
                nlog($payments['request_params']['q']);
        }
        else {
            nlog("payments");
            nlog($payments);
        }

        return response()->json($query, 200);
    }
}
