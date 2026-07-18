<?php

namespace App\Http\Requests;

use App\Models\SupplierPayment;
use Illuminate\Foundation\Http\FormRequest;

class AllocateSupplierPaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $allocations = collect($this->input('allocations', []))->filter(fn ($allocation) => bccomp((string) ($allocation['amount'] ?? '0'), '0', 4) === 1)->values()->all();
        $this->merge(['allocations' => $allocations]);
    }

    public function authorize(): bool
    {
        $payment = $this->route('supplier_payment');

        return $payment instanceof SupplierPayment && (bool) $this->user()?->can('allocate', $payment);
    }

    public function rules(): array
    {
        return ['allocations' => ['required', 'array', 'min:1'], 'allocations.*.supplier_invoice_id' => ['required', 'integer', 'distinct', 'exists:supplier_invoices,id'], 'allocations.*.amount' => ['required', 'decimal:0,4', 'gt:0']];
    }
}
