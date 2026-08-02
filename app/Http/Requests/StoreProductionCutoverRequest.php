<?php

namespace App\Http\Requests;

use App\Models\ProductionCutover;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductionCutoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', ProductionCutover::class);
    }

    public function rules(): array
    {
        return [
            'cutover_date' => ['required', 'date'],
            'legacy_freeze_reference' => ['required', 'string', 'max:255'],
            'source_documents_reference' => ['required', 'string', 'max:255'],
            'backup_run_id' => ['required', 'integer', 'exists:backup_runs,id'],
            'rollback_rehearsal_reference' => ['required', 'string', 'max:255'],
            'cash_confirmed' => ['accepted'],
            'owner_equity_confirmed' => ['accepted'],
            'sequence_confirmed' => ['accepted'],
            'tax_control_confirmed' => ['accepted'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->date('cutover_date') && ProductionCutover::query()->whereDate('cutover_date', $this->date('cutover_date'))->exists()) {
                $validator->errors()->add('cutover_date', 'A controlled cutover already exists for this date.');
            }
        }];
    }
}
