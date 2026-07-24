<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bank_statement_line_id', 'cash_transaction_id', 'matched_amount', 'confirmed_by', 'confirmed_at'])]
class BankReconciliationMatch extends Model
{
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_statement_line_id');
    }

    public function cashTransaction(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class);
    }

    protected function casts(): array
    {
        return ['matched_amount' => 'decimal:4', 'confirmed_at' => 'datetime'];
    }
}
