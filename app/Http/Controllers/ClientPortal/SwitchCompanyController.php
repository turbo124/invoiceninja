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

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;

class SwitchCompanyController extends Controller
{
    use MakesHash;

    public function __invoke(string $contact)
    {
        auth()->guard('contact')->user()->loadMissing('company');

        $client_contact = ClientContact::query()
            ->where('email', auth()->guard('contact')->user()->email)
            ->where('id', $this->transformKeys($contact))
            ->whereHas('company', fn ($q) => $q->where(
                'companies.account_id',
                auth()->guard('contact')->user()->company->account_id
            ))
            ->whereHas('client', fn ($q) => $q->where('is_deleted', false))
            ->firstOrFail();

        
        request()->session()->invalidate();
        request()->session()->regenerate(true);
        request()->session()->regenerateToken();
        
        auth()->guard('contact')->loginUsingId($client_contact->id, true);

        return redirect('/client/dashboard');
    }
}
