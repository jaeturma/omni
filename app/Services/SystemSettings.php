<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SystemSettings
{
    public const DEFAULTS = [
        'application_display_name' => 'Omni Mini-ERP', 'default_date_format' => 'Y-m-d', 'default_time_format' => 'h:i A',
        'default_currency' => 'PHP', 'currency_symbol' => '₱', 'decimal_places' => 2, 'quantity_decimal_places' => 4,
        'default_paper_size' => 'A4', 'records_per_page' => 25, 'low_stock_default_threshold' => '5.0000',
        'attachment_max_size' => 10240, 'backup_path_label' => 'Configured backup location',
        'maintenance_contact_name' => '', 'maintenance_contact_email' => '',
    ];

    public const TYPES = [
        'application_display_name' => 'string', 'default_date_format' => 'string', 'default_time_format' => 'string',
        'default_currency' => 'string', 'currency_symbol' => 'string', 'decimal_places' => 'integer',
        'quantity_decimal_places' => 'integer', 'default_paper_size' => 'string', 'records_per_page' => 'integer',
        'low_stock_default_threshold' => 'decimal', 'attachment_max_size' => 'integer', 'backup_path_label' => 'string',
        'maintenance_contact_name' => 'string', 'maintenance_contact_email' => 'string',
    ];

    /** @return array<string, string|int|null> */
    public function all(): array
    {
        $stored = SystemSetting::query()->whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key');
        $values = [];
        foreach (self::DEFAULTS as $key => $default) {
            $values[$key] = $stored->has($key) ? $this->cast($key, $stored->get($key)) : $default;
        }

        return $values;
    }

    public function get(string $key): string|int|null
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new InvalidArgumentException("Unknown system setting: {$key}");
        }
        $setting = SystemSetting::query()->where('key', $key)->first();

        return $setting ? $this->cast($key, $setting->value) : self::DEFAULTS[$key];
    }

    /** @param array<string, mixed> $values */
    public function update(array $values, int $userId): void
    {
        DB::transaction(function () use ($values, $userId): void {
            foreach (self::DEFAULTS as $key => $default) {
                SystemSetting::query()->updateOrCreate(['key' => $key], [
                    'value' => $this->serialize($values[$key] ?? $default), 'type' => self::TYPES[$key], 'updated_by' => $userId,
                ]);
            }
        });
    }

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'application_display_name' => ['required', 'string', 'max:100'], 'default_date_format' => ['required', 'in:Y-m-d,m/d/Y,d/m/Y,M j Y'],
            'default_time_format' => ['required', 'in:H:i,h:i A'], 'default_currency' => ['required', 'string', 'size:3', 'alpha:ascii'],
            'currency_symbol' => ['required', 'string', 'max:10'], 'decimal_places' => ['required', 'integer', 'between:0,4'],
            'quantity_decimal_places' => ['required', 'integer', 'between:0,4'], 'default_paper_size' => ['required', 'in:A4,Letter,Legal'],
            'records_per_page' => ['required', 'integer', 'between:10,100'], 'low_stock_default_threshold' => ['required', 'decimal:0,4', 'between:0,999999'],
            'attachment_max_size' => ['required', 'integer', 'between:1,51200'], 'backup_path_label' => ['required', 'string', 'max:255'],
            'maintenance_contact_name' => ['nullable', 'string', 'max:255'], 'maintenance_contact_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    private function cast(string $key, ?string $value): string|int|null
    {
        return self::TYPES[$key] === 'integer' ? (int) $value : $value;
    }

    private function serialize(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
