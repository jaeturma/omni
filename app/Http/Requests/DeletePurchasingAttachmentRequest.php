<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeletePurchasingAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchasing-attachments.delete') ?? false;
    }

    public function rules(): array
    {
        return ['deletion_reason' => ['required', 'string', 'max:500']];
    }
}
