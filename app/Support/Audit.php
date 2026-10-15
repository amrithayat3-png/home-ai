<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class Audit
{
    /**
     * Write one line to the audit trail: who did what, to which record, from which address.
     * A failure to write is reported to the log but never stops the user's action.
     *
     * @param  array<string, mixed>  $details
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        array $details = [],
        ?string $label = null,
        ?Authenticatable $by = null,
    ): void {
        try {
            $user = $by ?? auth()->user();

            AuditLog::create([
                'user_id' => $user?->getAuthIdentifier(),
                'user_label' => $user ? trim(($user->name ?? '').' <'.($user->email ?? '').'>') : null,
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $label,
                'details' => $details === [] ? null : $details,
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
