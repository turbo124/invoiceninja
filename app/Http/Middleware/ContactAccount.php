<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use App\Models\Account;
use App\Utils\Ninja;
use Closure;
use Illuminate\Http\Request;

class ContactAccount
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Ninja::isHosted()) {
            /** @var \App\Models\Account $account */
            $account = Account::first();

            session()->put('account_key', $account->key);
        }

        return $next($request);
    }
}
