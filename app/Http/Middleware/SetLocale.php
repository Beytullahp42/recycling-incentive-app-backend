<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasHeader('Accept-Language')) {
            $locale = $request->header('Accept-Language');

            $lang = substr($locale, 0, 2);

            if (in_array($lang, ['en', 'tr', 'es'])) {
                App::setLocale($lang);
            }
        }

        return $next($request);
    }
}
