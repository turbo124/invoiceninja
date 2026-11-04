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

use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;

/**
 * Class ContactTransformer.
 */
class ClientContactProfileTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @param ClientContact $contact
     *
     * @return array
     */
    public function transform(ClientContact $contact)
    {
        array_merge(parent::transform($contact), [
            'contact_key' => '',
            'send_email' => '',
            'cc_only' => '',
            'last_login' => 0,
            'password' => empty($contact->password) ? '' : '**********',
            'link' => '',
            'can_sign' => '',
        ]);
    }
}
