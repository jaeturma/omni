<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property numeric-string $approved_adjustments
 * @property numeric-string $credit_adjustments
 * @property numeric-string $customer_withholding
 * @property numeric-string $difference
 * @property numeric-string $gross_sales
 * @property numeric-string $ledger_revenue
 * @property numeric-string $operational_net_sales
 * @property numeric-string $receipt_basis
 * @property int $critical_difference_count
 * @property array<string, mixed> $source_snapshot
 */
class TaxReconciliation extends Model
{
    protected $fillable = ['tax_obligation_id', 'tax_base_rule', 'gross_sales', 'credit_adjustments', 'operational_net_sales', 'receipt_basis', 'ledger_revenue', 'customer_withholding', 'approved_adjustments', 'difference', 'critical_difference_count', 'parameters', 'source_snapshot', 'generated_at', 'generated_by'];

    protected $attributes = ['gross_sales' => 0, 'credit_adjustments' => 0, 'operational_net_sales' => 0, 'receipt_basis' => 0, 'ledger_revenue' => 0, 'customer_withholding' => 0, 'approved_adjustments' => 0, 'difference' => 0, 'critical_difference_count' => 0];

    /** @return BelongsTo<TaxObligation, $this> */
    public function taxObligation(): BelongsTo
    {
        return $this->belongsTo(TaxObligation::class);
    }

    /** @return HasMany<TaxReconciliationAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(TaxReconciliationAdjustment::class)->latest('id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    protected function casts(): array
    {
        return ['gross_sales' => 'decimal:4', 'credit_adjustments' => 'decimal:4', 'operational_net_sales' => 'decimal:4', 'receipt_basis' => 'decimal:4', 'ledger_revenue' => 'decimal:4', 'customer_withholding' => 'decimal:4', 'approved_adjustments' => 'decimal:4', 'difference' => 'decimal:4', 'critical_difference_count' => 'integer', 'parameters' => 'array', 'source_snapshot' => 'array', 'generated_at' => 'datetime'];
    }
}
