<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'email' ? 'email' : 'audit';
        return view('admin.logs', [
            'tab' => $tab,
            'audit' => $tab === 'audit' ? AuditLog::with('user')->orderByDesc('audit_log_id')->paginate(25)->withQueryString() : null,
            'emails' => $tab === 'email' ? EmailLog::orderByDesc('email_log_id')->paginate(25)->withQueryString() : null,
        ]);
    }
}
