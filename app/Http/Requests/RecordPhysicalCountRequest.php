<?php

namespace App\Http\Requests;

use App\Enums\PhysicalCountStatus;
use App\Models\PhysicalCount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RecordPhysicalCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $count = $this->route('physical_count');

        return $count instanceof PhysicalCount
            && $count->status === PhysicalCountStatus::Counting
            && (bool) $this->user()?->can('count', $count);
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer', 'distinct', 'exists:physical_count_lines,id'],
            'lines.*.counted_quantity' => ['required', 'decimal:0,4', 'gte:0'],
            'lines.*.explanation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $count = $this->route('physical_count');
            if (! $count instanceof PhysicalCount) {
                return;
            }
            $lineIds = $count->lines()->pluck('id')->all();
            foreach ($this->array('lines') as $index => $line) {
                if (! in_array((int) ($line['id'] ?? 0), $lineIds, true)) {
                    $validator->errors()->add("lines.$index.id", 'Every count line must belong to this session.');
                }
            }
        }];
    }
}
