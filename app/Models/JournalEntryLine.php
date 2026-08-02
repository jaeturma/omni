<?php

namespace App\Models;

use App\Enums\JournalEntryStatus;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $account_id
 * @property string|null $description
 * @property numeric-string $debit
 * @property numeric-string $credit
 * @property int|null $customer_id
 * @property int|null $supplier_id
 * @property int|null $financial_account_id
 * @property int|null $warehouse_id
 * @property int|null $product_id
 * @property string|null $source_line_type
 * @property int|null $source_line_id
 * @property string $running_balance
 */
#[Fillable(['account_id', 'line_number', 'description', 'debit', 'credit', 'customer_id', 'supplier_id', 'financial_account_id', 'warehouse_id', 'product_id', 'source_line_type', 'source_line_id'])]
class JournalEntryLine extends Model
{
    protected static function booted(): void
    {
        $assertDraft = function (self $line): void {
            $status = JournalEntry::query()->whereKey($line->journal_entry_id)->value('status');
            if ($status !== JournalEntryStatus::Draft) {
                throw new DomainException('Lines belonging to a posted, reversed, or voided journal entry are immutable.');
            }
        };
        static::updating($assertDraft);
        static::deleting($assertDraft);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<ProductService, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductService::class, 'product_id');
    }

    protected function casts(): array
    {
        return ['debit' => 'decimal:4', 'credit' => 'decimal:4'];
    }
}
