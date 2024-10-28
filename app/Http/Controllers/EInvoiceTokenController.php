<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Http\Requests\EInvoice\UpdateTokenRequest;
use Illuminate\Http\Response;

class EInvoiceTokenController extends BaseController
{
    public function __invoke(UpdateTokenRequest $request): Response
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $user->account->update([
            'e_invoice_token' => $request->input('token'),
        ]);

        return response()->noContent();
    }
}
