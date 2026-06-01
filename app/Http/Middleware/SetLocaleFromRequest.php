<?php

namespace App\Http\Middleware;

use App\Support\LocalizedValue;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromRequest
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $language = LocalizedValue::normalize($request->header('Accept-Language'));

        app()->setLocale($language);
        $request->attributes->set('language', $language);

        return $next($request);
    }
}
