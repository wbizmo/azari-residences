<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys((array) config('localization.supported_locales', ['en' => 'English']));
        $candidate = $request->user()?->locale
            ?: $request->session()->get('locale')
            ?: $request->getPreferredLanguage($supported)
            ?: config('localization.default_locale', 'en');

        if (! in_array($candidate, $supported, true)) {
            $candidate = (string) config('localization.fallback_locale', 'en');
        }

        app()->setLocale($candidate);
        $request->session()->put('locale', $candidate);

        return $next($request);
    }
}
