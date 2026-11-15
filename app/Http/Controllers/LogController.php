<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'email' ? 'email' : 'audit';
        $query = AuditLog::with('user');
        if ($tab === 'audit') {
            if ($request->filled('category')) {
                $prefixes = ['payments' => ['pay_%'], 'appointments' => ['apt_%'], 'odontogram' => ['odo_%'], 'users' => ['usr_%'], 'settings' => ['sch_%', 'sys_%']];
                if (isset($prefixes[$request->category])) $query->where(function ($q) use ($prefixes, $request) { foreach ($prefixes[$request->category] as $prefix) $q->orWhere('table_affected', 'like', $prefix); });
            }
            if ($request->input('kind') === 'logins') $query->whereIn('action_type', ['login', 'logout', 'login_failed', 'lockout']);
            if ($request->input('kind') === 'changes') $query->whereIn('action_type', ['create', 'update', 'delete']);
            if ($request->filled('action_type')) $query->where('action_type', $request->action_type);
            if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
            if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
            if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->date_to);
        }
        return view('admin.logs', [
            'tab' => $tab,
            'audit' => $tab === 'audit' ? $query->orderByDesc('audit_log_id')->paginate(25)->withQueryString() : null,
            'actions' => DB::table('sys_audit_logs')->select('action_type')->distinct()->orderBy('action_type')->pluck('action_type'),
            'users' => \App\Models\User::orderBy('first_name')->orderBy('last_name')->get(),
            'emails' => $tab === 'email' ? EmailLog::orderByDesc('email_log_id')->paginate(25)->withQueryString() : null,
        ]);
    }
}
