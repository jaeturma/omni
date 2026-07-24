<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['financial_account_id', 'statement_start_date', 'statement_end_date', 'source_filename', 'file_hash', 'column_mapping', 'imported_by', 'imported_at', 'finalized_at', 'finalized_by', 'rolled_back_at', 'rolled_back_by'])]
class BankStatementImport extends Model
{
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    protected function casts(): array
    {
        return ['statement_start_date' => 'date', 'statement_end_date' => 'date', 'column_mapping' => 'array',
            'imported_at' => 'datetime', 'finalized_at' => 'datetime', 'rolled_back_at' => 'datetime'];
    }
}
