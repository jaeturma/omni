<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Models\Concerns\HasPurchasingAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ExpenseStatus $status
 * @property Carbon $expense_date
 * @property numeric-string $gross_amount
 * @property numeric-string $withholding_amount
 * @property numeric-string $other_deductions
 * @property numeric-string $net_cash_paid
 */
#[Fillable(['fiscal_period_id', 'supplier_id', 'customer_id', 'payment_method_id', 'bank_id', 'document_number_reservation_id', 'expense_number', 'expense_date', 'payee_name', 'expense_category', 'description', 'business_purpose', 'reference_number', 'project_reference', 'receipt_available', 'receipt_reference', 'gross_amount', 'withholding_amount', 'other_deductions', 'net_cash_paid', 'reimbursable', 'notes', 'status', 'approved_at', 'approved_by', 'paid_at', 'paid_by', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class Expense extends Model
{
    use HasPurchasingAttachments;

    public const CATEGORIES = ['utilities', 'rent', 'transportation', 'communications', 'office_supplies', 'repairs_maintenance', 'professional_fees', 'government_fees', 'meals_representation', 'other_business_expense'];

    protected $attributes = ['withholding_amount' => 0, 'other_deductions' => 0, 'net_cash_paid' => 0, 'receipt_available' => false, 'reimbursable' => false, 'status' => 'draft'];

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function numberReservation(): BelongsTo
    {
        return $this->belongsTo(DocumentNumberReservation::class, 'document_number_reservation_id');
    }

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'gross_amount' => 'decimal:4', 'withholding_amount' => 'decimal:4', 'other_deductions' => 'decimal:4', 'net_cash_paid' => 'decimal:4', 'receipt_available' => 'boolean', 'reimbursable' => 'boolean', 'status' => ExpenseStatus::class, 'approved_at' => 'datetime', 'paid_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}
