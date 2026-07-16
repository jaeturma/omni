<?php

namespace App\Models;

use Database\Factories\BankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'name', 'swift_code', 'status', 'created_by', 'updated_by'])]
class Bank extends Model
{
    /** @use HasFactory<BankFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'active'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
