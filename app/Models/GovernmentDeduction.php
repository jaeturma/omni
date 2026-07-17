<?php

namespace App\Models;

use App\Enums\GovernmentDeductionStatus;
use App\Models\Concerns\HasSalesAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property GovernmentDeductionStatus $status
 * @property Carbon $covered_from
 * @property Carbon $covered_to
 * @property numeric-string $gross_basis
 * @property numeric-string $rate
 * @property numeric-string $amount
 */
#[Fillable(['customer_id', 'sales_invoice_id', 'customer_payment_id', 'tax_rate_setting_id', 'deduction_type', 'certificate_type', 'certificate_number', 'certificate_date', 'covered_from', 'covered_to', 'gross_basis', 'rate', 'amount', 'status', 'notes', 'attachment_reference', 'verified_at', 'verified_by', 'voided_at', 'voided_by', 'void_reason', 'created_by', 'updated_by'])]
class GovernmentDeduction extends Model
{
    use HasSalesAttachments;

    public const DEDUCTION_TYPES = ['percentage_tax_withheld', 'expanded_withholding_tax', 'retention', 'liquidated_damages', 'other_government_deduction'];

    public const CERTIFICATE_TYPES = ['2304', '2306', '2307', 'other'];

    protected $attributes = ['status' => 'pending'];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<SalesInvoice, $this> */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    /** @return BelongsTo<CustomerPayment, $this> */
    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class);
    }

    /** @return BelongsTo<TaxRateSetting, $this> */
    public function taxRateSetting(): BelongsTo
    {
        return $this->belongsTo(TaxRateSetting::class);
    }

    /** @return numeric-string */
    public function netAfterDeduction(): string
    {
        return bcsub($this->gross_basis, $this->amount, 4);
    }

    protected function casts(): array
    {
        return ['certificate_date' => 'date', 'covered_from' => 'date', 'covered_to' => 'date', 'gross_basis' => 'decimal:4',
            'rate' => 'decimal:6', 'amount' => 'decimal:4', 'status' => GovernmentDeductionStatus::class,
            'verified_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}
