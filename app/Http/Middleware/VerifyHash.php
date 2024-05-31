<?php

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use App\Models\PaymentHash;
use Closure;
use Illuminate\Http\Request;

class VerifyHash
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('payment_hash')) {
            $ph = PaymentHash::query()->with('fee_invoice')->where('hash', $request->payment_hash)->first();

            if ($ph) {
                auth()->guard('contact')->loginUsingId($ph->fee_invoice->invitations->first()->contact->id, true);
            }

            return $next($request);
        }

        abort(404, 'Unable to verify payment hash');
    }
}
