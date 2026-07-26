<?php

namespace App\Models;

use App\Enums\JournalEntryStatus;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'line_number', 'description', 'debit', 'credit', 'customer_id', 'supplier_id', 'financial_account_id', 'warehouse_id', 'product_id', 'source_line_type', 'source_line_id'])]
class JournalEntryLine extends Model
{
    protected static function booted(): void
    {
        $assertDraft = function (self $line): void {
            $status = JournalEntry::query()->whereKey($line->journal_entry_id)->value('status');
            if ($status !== JournalEntryStatus::Draft) {
                throw new DomainException('Lines belonging to a posted, reversed, or voided journal entry are immutable.');
            }
        };
        static::updating($assertDraft);
        static::deleting($assertDraft);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return ['debit' => 'decimal:4', 'credit' => 'decimal:4'];
    }
}
