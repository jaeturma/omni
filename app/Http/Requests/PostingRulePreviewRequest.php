<?php

namespace App\Http\Requests;

use App\Enums\PostingSourceType;
use App\Models\PostingRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostingRulePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('preview', PostingRule::class);
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', Rule::enum(PostingSourceType::class)],
            'posting_date' => ['required', 'date'],
            'amount' => ['required', 'decimal:0,4', 'gt:0'],
            'product_category_id' => ['nullable', 'integer'],
            'service_category_id' => ['nullable', 'integer'],
            'expense_category' => ['nullable', 'string', 'max:50'],
            'customer_type' => ['nullable', 'string', 'max:50'],
            'supplier_type' => ['nullable', 'string', 'max:50'],
            'financial_account_id' => ['nullable', 'integer'],
            'tax_code' => ['nullable', 'string', 'max:40'],
            'warehouse_id' => ['nullable', 'integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (PostingRule::DIMENSIONS as $dimension) {
            $value = $this->input($dimension);
            $normalized[$dimension] = filled($value)
                ? (str_ends_with($dimension, '_id') ? $value : str($value)->trim()->lower()->toString())
                : null;
        }
        $this->merge($normalized);
    }
}
