<?php

namespace App\Models;

use App\Enums\AccountClass;
use App\Enums\AccountType;
use App\Enums\CashFlowClassification;
use App\Enums\CurrentClassification;
use App\Enums\NormalBalance;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property NormalBalance $normal_balance */
#[Fillable([
    'code', 'name', 'account_class', 'account_type', 'normal_balance', 'current_classification',
    'cash_flow_classification', 'parent_id',
    'is_header', 'is_postable', 'is_control_account', 'control_account_type',
    'is_active', 'is_system', 'description', 'display_order',
])]
class Account extends Model
{
    protected $attributes = [
        'is_header' => false,
        'is_postable' => true,
        'is_control_account' => false,
        'is_active' => true,
        'is_system' => false,
        'display_order' => 0,
    ];

    protected static function booted(): void
    {
        static::updating(function (self $account): void {
            $protected = ['code', 'account_class', 'account_type', 'normal_balance', 'is_header', 'is_postable', 'is_control_account', 'control_account_type'];
            if (($account->getOriginal('is_system') || $account->getOriginal('is_control_account')) && $account->isDirty($protected)) {
                throw new DomainException('System and control account classifications cannot be changed.');
            }
        });

        static::deleting(function (self $account): void {
            if ($account->is_system || $account->is_control_account) {
                throw new DomainException('System and control accounts cannot be deleted.');
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('code');
    }

    public function wouldCreateCycle(?int $parentId): bool
    {
        while ($parentId !== null) {
            if ($parentId === $this->getKey()) {
                return true;
            }

            $parentId = self::query()->whereKey($parentId)->value('parent_id');
        }

        return false;
    }

    public function assertPostable(): void
    {
        if ($this->is_header || ! $this->is_postable || ! $this->is_active) {
            throw new DomainException('Only active postable accounts may receive journal entries.');
        }
    }

    protected function casts(): array
    {
        return [
            'account_class' => AccountClass::class,
            'account_type' => AccountType::class,
            'normal_balance' => NormalBalance::class,
            'current_classification' => CurrentClassification::class,
            'cash_flow_classification' => CashFlowClassification::class,
            'is_header' => 'boolean',
            'is_postable' => 'boolean',
            'is_control_account' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'display_order' => 'integer',
        ];
    }
}
