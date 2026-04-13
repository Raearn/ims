<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string[]  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (in_array($request->user()->role, $roles)) {
            return $next($request);
        }

        // Default: If unauthorized for this route, redirect based on their primary role dashboard
        if ($request->user()->isAdmin()) {
            return redirect()->route('dashboard');
        }

        if ($request->user()->isSupervisor()) {
            return redirect()->route('supervisor.dashboard');
        }

        if ($request->user()->isTechnical()) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()->route('home');
        }

        abort(403, 'Unauthorized access.');
    }
}
