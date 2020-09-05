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

namespace App\Http\Middleware;

use App\Libraries\MultiDB;
use App\Models\CompanyToken;
use Closure;

class SetEmailDb
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $error = [
            'message' => 'Email not set or not found',
            'errors' => new \stdClass,
        ];

        if ($request->input('email') && config('ninja.db.multi_db_enabled')) {
            if (! MultiDB::userFindAndSetDb($request->input('email'))) {
                return response()->json($error, 403);
            }
        } else {
            return response()->json($error, 403);
        }

        return $next($request);
    }
}
