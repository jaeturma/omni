<?php

namespace App\Models;

use App\Enums\AccountingSourceType;
use App\Enums\JournalEntryStatus;
use App\Enums\JournalEntryType;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/** @property string $journal_number
 * @property Carbon $journal_date
 * @property Carbon $document_date
 * @property string $total_debit
 * @property string $total_credit
 * @property JournalEntryStatus $status
 * @property AccountingSourceType $source_type
 * @property int|null $source_id
 */
#[Fillable(['journal_number', 'journal_date', 'document_date', 'fiscal_period_id', 'journal_type', 'source_type', 'source_id', 'reference_number', 'description', 'total_debit', 'total_credit', 'status', 'posted_at', 'posted_by', 'reversed_at', 'reversed_by', 'reversal_entry_id', 'reverses_entry_id', 'correction_of_id', 'reversal_reason', 'auto_reverse_on', 'is_auto_reversal', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class JournalEntry extends Model
{
    protected $attributes = ['total_debit' => '0.0000', 'total_credit' => '0.0000', 'status' => 'draft'];

    protected static function booted(): void
    {
        static::updating(function (self $entry): void {
            if ($entry->getRawOriginal('status') !== JournalEntryStatus::Draft->value) {
                throw new DomainException('Posted, reversed, and voided journal entries are immutable.');
            }
        });
        static::deleting(function (self $entry): void {
            if ($entry->status !== JournalEntryStatus::Draft) {
                throw new DomainException('Only draft journal entries may be deleted.');
            }
        });
    }

    /** @return HasMany<JournalEntryLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('line_number');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversalEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_entry_id');
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    public function correctedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'correction_of_id');
    }

    public function correctionEntry(): HasOne
    {
        return $this->hasOne(self::class, 'correction_of_id');
    }

    protected function casts(): array
    {
        return ['journal_date' => 'date', 'document_date' => 'date', 'journal_type' => JournalEntryType::class,
            'source_type' => AccountingSourceType::class, 'status' => JournalEntryStatus::class,
            'total_debit' => 'decimal:4', 'total_credit' => 'decimal:4', 'posted_at' => 'datetime',
            'reversed_at' => 'datetime', 'auto_reverse_on' => 'date', 'is_auto_reversal' => 'boolean',
            'voided_at' => 'datetime'];
    }
}
