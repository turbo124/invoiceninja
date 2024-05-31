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

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

class ValidateSignature
{
    /**
     * The names of the parameters that should be ignored.
     *
     * @var array<int, string>
     */
    protected $ignore = [
        'q',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  string|null  $relative
     *
     * @throws \Illuminate\Routing\Exceptions\InvalidSignatureException
     */
    public function handle(Request $request, Closure $next, $relative = null): Response
    {
        $ignore = property_exists($this, 'except') ? $this->except : $this->ignore;

        if ($request->hasValidSignatureWhileIgnoring($ignore, $relative !== 'relative')) {
            return $next($request);
        }

        throw new InvalidSignatureException();
    }
}
