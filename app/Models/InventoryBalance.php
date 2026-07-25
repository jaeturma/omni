<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string $quantity_on_hand
 * @property numeric-string $weighted_average_cost
 */
#[Fillable(['product_service_id', 'warehouse_id', 'opening_balance_line_id', 'quantity_on_hand', 'weighted_average_cost', 'updated_by'])]
class InventoryBalance extends Model
{
    protected $attributes = ['quantity_on_hand' => 0, 'weighted_average_cost' => 0];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductService::class, 'product_service_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    protected function casts(): array
    {
        return ['quantity_on_hand' => 'decimal:4', 'weighted_average_cost' => 'decimal:4'];
    }
}
