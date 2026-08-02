<?php

namespace App\Http\Requests;

use App\Models\TaxPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxReviewCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $period = $this->route('tax_period');

        return $period instanceof TaxPeriod && (bool) $this->user()?->can('comment', $period);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['comment' => ['required_if:status,open', 'nullable', 'string', 'max:5000'], 'status' => ['required', Rule::in(['open', 'resolved'])], 'comment_id' => ['required_if:status,resolved', 'nullable', 'integer', Rule::exists('tax_review_comments', 'id')->where('tax_period_id', $this->route('tax_period')?->getKey())]];
    }
}
