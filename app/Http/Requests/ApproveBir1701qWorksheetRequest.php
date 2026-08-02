<?php

namespace App\Http\Requests;

use App\Models\Bir1701qWorksheet;
use Illuminate\Foundation\Http\FormRequest;

class ApproveBir1701qWorksheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $worksheet = $this->route('bir_1701q_worksheet');

        return $worksheet instanceof Bir1701qWorksheet && (bool) $this->user()?->can('approve', $worksheet);
    }

    public function rules(): array
    {
        return ['confirm_freeze' => ['required', 'accepted']];
    }
}
