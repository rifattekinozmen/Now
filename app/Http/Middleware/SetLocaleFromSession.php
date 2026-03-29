<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromSession
{
    /**
     * @var list<string>
     */
    private const SUPPORTED = ['en', 'tr'];

    public function handle(Request $request, Closure $next): Response
    {
        $fromSession = $request->session()->get('locale');

        if (is_string($fromSession) && in_array($fromSession, self::SUPPORTED, true)) {
            App::setLocale($fromSession);
        }

        return $next($request);
    }
}
