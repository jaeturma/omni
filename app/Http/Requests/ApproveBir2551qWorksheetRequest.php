<?php

namespace App\Http\Requests;

use App\Models\Bir2551qWorksheet;
use Illuminate\Foundation\Http\FormRequest;

class ApproveBir2551qWorksheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $worksheet = $this->route('bir_2551q_worksheet');

        return $worksheet instanceof Bir2551qWorksheet && (bool) $this->user()?->can('approve', $worksheet);
    }

    public function rules(): array
    {
        return ['confirm_freeze' => ['required', 'accepted']];
    }
}
