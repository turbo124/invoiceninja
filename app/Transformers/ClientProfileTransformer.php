<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\Client;

class ClientProfileTransformer extends ClientTransformer
{
    protected array $defaultIncludes = [
        'contacts',
        'locations',
    ];

    protected array $availableIncludes = [];

    public function transform(Client $client)
    {
        return array_merge(parent::transform($client), [
            'private_notes' => '',
            'balance' => 0.0,
            'paid_to_date' => 0.0,
            'credit_balance' => 0.0,
            'payment_balance' => 0.0,
            'client_hash' => '',
        ]);
    }

        /**
     * @param Client $client
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function includeContacts(Client $client)
    {
        $transformer = new ClientContactProfileTransformer($this->serializer, $this->user);

        return $this->includeCollection($client->contacts, $transformer, ClientContact::class);
    }

}
