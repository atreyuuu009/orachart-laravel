<?php

namespace App\Http\Middleware;

use App\Support\Audit;
use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Logs the user out after sys_settings.session_timeout_minutes (default 15) of inactivity. */
class SessionTimeout
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $limit = max(1, Settings::int('session_timeout_minutes')) * 60;
            $last = (int) $request->session()->get('last_activity', time());

            if (time() - $last > $limit) {
                Audit::log('logout', 'usr_users', Auth::id(), 'Session expired (inactivity)');
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return $request->expectsJson()
                    ? response()->json(['message' => 'Session expired.'], 401)
                    : redirect()->route('login')->with('status', 'You were logged out after a period of inactivity.');
            }
            $request->session()->put('last_activity', time());
        }
        return $next($request);
    }
}
