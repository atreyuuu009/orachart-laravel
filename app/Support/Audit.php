<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/** Writes sys_audit_logs. Never lets an audit failure break the user's action. */
class Audit
{
    public static function log(string $action, ?string $table = null, $recordId = null, ?string $description = null, ?array $old = null, ?array $new = null, ?int $userId = null): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId ?? Auth::id(),
                'action_type' => $action,
                'table_affected' => $table,
                'record_id' => $recordId !== null ? (string) $recordId : null,
                'description' => $description ? mb_substr($description, 0, 500) : null,
                'old_value' => $old,
                'new_value' => $new,
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
