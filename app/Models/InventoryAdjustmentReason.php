<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'active'])]
class InventoryAdjustmentReason extends Model
{
    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
