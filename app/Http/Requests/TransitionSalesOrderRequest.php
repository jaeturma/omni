<?php

namespace App\Http\Requests;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $o = $this->route('sales_order');
        if (! $o instanceof SalesOrder) {
            return false;
        }

        return $this->input('status') === SalesOrderStatus::Cancelled->value ? (bool) $this->user()?->can('cancel', $o) : (bool) $this->user()?->can('confirm', $o);
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(SalesOrderStatus::class)], 'reason' => [Rule::requiredIf($this->input('status') === SalesOrderStatus::Cancelled->value), 'nullable', 'string', 'max:2000']];
    }
}
