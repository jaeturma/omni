<?php

namespace Database\Seeders;

use App\Models\InventoryAdjustmentReason;
use Illuminate\Database\Seeder;

class InventoryAdjustmentReasonSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'damaged' => 'Damaged', 'lost' => 'Lost', 'found' => 'Found',
            'encoding_error' => 'Encoding Error', 'opening_balance_correction' => 'Opening Balance Correction',
            'obsolete' => 'Obsolete', 'expired' => 'Expired', 'other' => 'Other',
        ] as $code => $name) {
            InventoryAdjustmentReason::query()->updateOrCreate(['code' => $code], ['name' => $name, 'active' => true]);
        }
    }
}
