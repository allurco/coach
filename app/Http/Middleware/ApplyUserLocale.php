<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserLocale
{
    /**
     * If the request has an authenticated user with a stored locale,
     * switch the app locale to it for the rest of this request. Falls
     * back to APP_LOCALE when no user is logged in or no preference
     * is set yet, so guests + console flows are unaffected.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if ($locale && in_array($locale, ['pt_BR', 'en'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
