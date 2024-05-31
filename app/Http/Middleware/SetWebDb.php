<?php

namespace App\Http\Middleware;

use App\Libraries\MultiDB;
use Closure;
use Cookie;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetWebDb
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('ninja.db.multi_db_enabled')) {
            MultiDB::setDB(Cookie::get('db'));
        }

        return $next($request);
    }
}
