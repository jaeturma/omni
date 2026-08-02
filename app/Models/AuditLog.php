<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $event
 * @property string $module
 * @property string $subject_type
 * @property string|null $subject_id
 * @property string|null $reason
 * @property string|null $source_action
 * @property string $correlation_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $occurred_at
 * @property User|null $actor
 */
#[Fillable(['event', 'module', 'actor_id', 'subject_type', 'subject_id', 'before_values', 'after_values', 'reason', 'source_action', 'correlation_id', 'ip_address', 'user_agent', 'protected_metadata', 'occurred_at'])]
class AuditLog extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit records are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit records are append-only.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return ['before_values' => 'array', 'after_values' => 'array', 'protected_metadata' => 'array', 'occurred_at' => 'datetime'];
    }
}
