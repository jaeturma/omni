<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_profile_id', 'fiscal_year_id', 'fiscal_year_scope', 'document_type', 'prefix', 'suffix', 'current_number', 'padding', 'reset_rule', 'active', 'created_by', 'updated_by'])]
class DocumentSequence extends Model
{
    public const TYPES = ['quotation', 'sales_order', 'delivery_receipt', 'sales_invoice', 'collection_receipt', 'purchase_invoice', 'supplier_payment', 'expense_voucher', 'inventory_adjustment', 'journal_entry'];

    protected $attributes = ['prefix' => '', 'suffix' => '', 'current_number' => 0, 'padding' => 6, 'reset_rule' => 'never', 'active' => true];

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    /** @return BelongsTo<FiscalYear, $this> */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /** @return HasMany<DocumentNumberReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(DocumentNumberReservation::class)->latest('issued_at');
    }

    public function preview(): string
    {
        return $this->formatNumber($this->current_number + 1);
    }

    public function formatNumber(int $number): string
    {
        $year = $this->fiscalYear ? CarbonImmutable::parse($this->fiscalYear->starts_on)->format('Y') : '';
        $replace = fn (string $value): string => str_replace('{YYYY}', $year, $value);

        return $replace($this->prefix).str_pad((string) $number, $this->padding, '0', STR_PAD_LEFT).$replace($this->suffix);
    }

    protected function casts(): array
    {
        return ['current_number' => 'integer', 'padding' => 'integer', 'active' => 'boolean'];
    }
}
