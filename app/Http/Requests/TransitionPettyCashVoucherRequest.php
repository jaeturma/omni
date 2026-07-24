<?php

namespace App\Http\Requests;

use App\Enums\PettyCashVoucherStatus;
use App\Models\Expense;
use App\Models\PettyCashVoucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransitionPettyCashVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        $voucher = $this->route('petty_cash_voucher');
        if (! $voucher instanceof PettyCashVoucher) {
            return false;
        }

        return match ($this->input('status')) {
            'released' => (bool) $this->user()?->can('release', $voucher),
            'liquidated' => (bool) $this->user()?->can('liquidate', $voucher),
            'overdue' => (bool) $this->user()?->can('markOverdue', $voucher),
            'voided' => (bool) $this->user()?->can('void', $voucher),
            default => false,
        };
    }

    public function rules(): array
    {
        $liquidating = $this->input('status') === 'liquidated';

        return [
            'status' => ['required', Rule::enum(PettyCashVoucherStatus::class)],
            'amount_liquidated' => [Rule::requiredIf($liquidating), 'nullable', 'decimal:0,4', 'gte:0'],
            'amount_returned' => [Rule::requiredIf($liquidating), 'nullable', 'decimal:0,4', 'gte:0'],
            'receipt_available' => [Rule::requiredIf($liquidating), 'nullable', 'boolean'],
            'expense_id' => ['nullable', 'integer', 'exists:expenses,id', Rule::unique('petty_cash_vouchers', 'expense_id')->ignore($this->route('petty_cash_voucher'))],
            'reason' => [Rule::requiredIf($this->input('status') === 'voided'), 'nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('status') !== 'liquidated') {
                return;
            }
            $voucher = $this->route('petty_cash_voucher');
            if (! $voucher instanceof PettyCashVoucher) {
                return;
            }
            $accounted = bcadd((string) $this->input('amount_liquidated'), (string) $this->input('amount_returned'), 4);
            if (bccomp($accounted, $voucher->amount_released, 4) !== 0) {
                $validator->errors()->add('amount_liquidated', 'Liquidated and returned amounts must equal the amount released.');
            }
            $expense = $this->filled('expense_id') ? Expense::find($this->integer('expense_id')) : null;
            if ($expense && (! in_array($expense->status->value, ['approved', 'paid', 'reimbursable'], true)
                || $expense->payee_name !== $voucher->payee
                || bccomp($expense->net_cash_paid, (string) $this->input('amount_liquidated'), 4) !== 0)) {
                $validator->errors()->add('expense_id', 'The approved expense payee and cash amount must match this liquidation.');
            }
        }];
    }
}
