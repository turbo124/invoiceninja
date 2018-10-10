<?php

namespace App\Http\Middleware;

use Closure;
use Session;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Guard;

/**
 * Class RedirectIfAuthenticated.
 */
class RedirectIfAuthenticated
{
    /**
     * The Guard implementation.
     *
     * @var Guard
     */
    protected $auth;

    /**
     * Create a new filter instance.
     *
     * @param Guard $auth
     */
    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {
        if (auth()->guard($guard)->check()) {
            Session::reflash();

            switch ($guard) {
                case 'client':
                    if (session('contact_key')) {
                        return redirect('/client/dashboard');
                    }
                    break;
                default:
                    return redirect('/dashboard');
                    break;
            }
        }

        return $next($request);
    }
}
