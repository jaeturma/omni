<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property Carbon $cutover_date
 * @property string $status
 * @property int $created_by
 * @property array<string, mixed>|null $report_snapshot
 * @property BackupRun $backupRun
 */
#[Fillable(['cutover_date', 'status', 'legacy_freeze_reference', 'source_documents_reference', 'backup_run_id', 'rollback_rehearsal_reference', 'cash_confirmed', 'owner_equity_confirmed', 'sequence_confirmed', 'tax_control_confirmed', 'report_snapshot', 'notes', 'created_by', 'reviewed_by', 'reviewed_at', 'activated_by', 'activated_at'])]
class ProductionCutover extends Model
{
    protected $attributes = ['status' => 'draft', 'cash_confirmed' => false, 'owner_equity_confirmed' => false, 'sequence_confirmed' => false, 'tax_control_confirmed' => false];

    protected static function booted(): void
    {
        static::updating(function (self $cutover): void {
            $activationFields = ['status', 'activated_by', 'activated_at', 'updated_at'];
            if ($cutover->getRawOriginal('status') === 'activated'
                || ($cutover->getRawOriginal('status') === 'approved' && array_diff(array_keys($cutover->getDirty()), $activationFields))) {
                throw new LogicException('Approved and activated cutover records are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Cutover records cannot be deleted.'));
    }

    /** @return BelongsTo<BackupRun, $this> */
    public function backupRun(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    protected function casts(): array
    {
        return ['cutover_date' => 'date', 'cash_confirmed' => 'boolean', 'owner_equity_confirmed' => 'boolean', 'sequence_confirmed' => 'boolean', 'tax_control_confirmed' => 'boolean', 'report_snapshot' => 'array', 'reviewed_at' => 'datetime', 'activated_at' => 'datetime'];
    }
}
