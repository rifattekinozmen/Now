<?php

namespace App\Http\Middleware;

use App\Authorization\LogisticsPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLogisticsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        if (! $user->can(LogisticsPermission::ADMIN) && ! $user->can(LogisticsPermission::VIEW)) {
            abort(403);
        }

        return $next($request);
    }
}
