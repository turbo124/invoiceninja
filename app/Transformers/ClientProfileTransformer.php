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
    protected array $defaultIncludes = [];

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
}
