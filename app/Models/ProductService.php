<?php

namespace App\Models;

use Database\Factories\ProductServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sku', 'barcode', 'name', 'description', 'type', 'category_id', 'unit_of_measure_id', 'default_cost', 'selling_price', 'reorder_level', 'is_inventory', 'status', 'created_by', 'updated_by'])]
class ProductService extends Model
{
    /** @use HasFactory<ProductServiceFactory> */
    use HasFactory;

    protected $attributes = [
        'default_cost' => 0, 'selling_price' => 0, 'reorder_level' => 0,
        'is_inventory' => false, 'status' => 'active',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return [
            'default_cost' => 'decimal:4', 'selling_price' => 'decimal:4',
            'reorder_level' => 'decimal:4', 'is_inventory' => 'boolean',
        ];
    }
}
