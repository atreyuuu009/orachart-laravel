<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Usage: ->middleware('role:admin,dentist')  (role names come from usr_roles.role_name) */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (! $user) return redirect()->route('login');
        if (! $user->hasRole(...$roles)) abort(403, 'You do not have access to this page.');
        return $next($request);
    }
}
