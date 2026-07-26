<?php

namespace App\Models;

use App\Enums\PostingSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read Account $debitAccount
 * @property-read Account $creditAccount
 */
#[Fillable([
    'name', 'source_type', 'debit_account_id', 'credit_account_id', 'product_category_id',
    'service_category_id', 'expense_category', 'customer_type', 'supplier_type',
    'financial_account_id', 'tax_code', 'warehouse_id', 'effective_from', 'effective_to',
    'is_active', 'created_by', 'updated_by', 'activated_at', 'activated_by',
    'deactivated_at', 'deactivated_by',
])]
class PostingRule extends Model
{
    public const DIMENSIONS = [
        'product_category_id', 'service_category_id', 'expense_category', 'customer_type',
        'supplier_type', 'financial_account_id', 'tax_code', 'warehouse_id',
    ];

    protected $attributes = ['is_active' => true];

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }

    public function specificity(): int
    {
        return collect(self::DIMENSIONS)->filter(fn (string $dimension) => $this->getAttribute($dimension) !== null)->count();
    }

    protected function casts(): array
    {
        return [
            'source_type' => PostingSourceType::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }
}
