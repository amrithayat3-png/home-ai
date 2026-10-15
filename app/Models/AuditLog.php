<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line in the audit trail. Rows are written once and never changed or deleted by the application.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }
}
