<?php

namespace App\Models;

use App\Enums\ReconciliationState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $transaction_date
 * @property Carbon $posting_date
 * @property numeric-string $debit
 * @property numeric-string $credit
 * @property numeric-string|null $running_balance
 * @property numeric-string $normalized_amount
 * @property ReconciliationState $match_status
 */
#[Fillable(['line_number', 'transaction_date', 'posting_date', 'description', 'reference_number', 'debit', 'credit', 'running_balance', 'normalized_amount', 'match_status', 'matched_transaction_reference', 'original_values'])]
class BankStatementLine extends Model
{
    public function bankStatementImport(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class);
    }

    protected function casts(): array
    {
        return ['transaction_date' => 'date', 'posting_date' => 'date', 'debit' => 'decimal:4', 'credit' => 'decimal:4',
            'running_balance' => 'decimal:4', 'normalized_amount' => 'decimal:4', 'match_status' => ReconciliationState::class,
            'original_values' => 'array'];
    }
}
