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
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Locale
{
    private array $locales = [
        'en',
        'it',
        'de',
        'fr',
        'pt_BR',
        'nl',
        'es',
        'nb_NO',
        'da',
        'ja',
        'sv',
        'es_ES',
        'fr_CA',
        'lt',
        'pl',
        'cs',
        'hr',
        'sq',
        'el',
        'en_GB',
        'pt_PT',
        'sl',
        'fi',
        'ro',
        'tr_TR',
        'th',
        'mk_MK',
        'zh_TW',
        'ru_RU',
        'ar',
        'fa',
        'lv_LV',
        'sr',
        'sk',
        'et',
        'bg',
        'he',
        'km_KH',
        'lo_LA',
        'hu',
        'fr_CH',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        /*LOCALE SET */
        if ($request->has('lang') && in_array($request->input('lang', 'en'), $this->locales)) {
            $locale = $request->input('lang');
            App::setLocale($locale);
        } elseif (auth()->guard('contact')->user()) {
            App::setLocale(auth()->guard('contact')->user()->client()->setEagerLoads([])->first()->locale());
        } elseif (auth()->user()) {
            try {
                App::setLocale(auth()->user()->company()->getLocale());
            } catch (\Exception $e) {
            }
        } else {
            App::setLocale(config('ninja.i18n.locale'));
        }

        return $next($request);
    }
}
