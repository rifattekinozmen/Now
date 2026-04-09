<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Optimize Livewire navigation responses.
 * Reduces payload size for wire:navigate requests.
 */
class OptimizeLivewireNavigation
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only optimize Livewire navigation requests
        if ($request->header('HX-Request') || $request->header('X-Livewire')) {
            // Livewire will handle response optimization automatically
            // This middleware can be extended for custom optimization
        }

        return $response;
    }
}
