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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
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

        // If user is supervisor and accessing admin dashboard, redirect to supervisor dashboard
        if ($request->user()->isSupervisor() && in_array('admin', $roles)) {
            return redirect()->route('supervisor.dashboard');
        }

        // Default: If unauthorized for this route, redirect based on their primary role dashboard
        if ($request->user()->isAdmin()) {
            return redirect()->route('dashboard');
        }

        if ($request->user()->isSupervisor()) {
            return redirect()->route('supervisor.dashboard');
        }

        // Fallback for technical or other roles
        abort(403, 'Unauthorized access.');
    }
}
