<?php

namespace App\Services;

use App\Models\BusinessProfile;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use App\Support\SensitiveData;
use Illuminate\Support\Collection;

class DataSubjectRegistry
{
    /** @return Collection<int, array<string, mixed>> */
    public function lookup(string $term, bool $reveal = false): Collection
    {
        $like = '%'.$term.'%';
        $customers = Customer::query()->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('tin', 'like', $like))->limit(50)->get()
            ->map(fn (Customer $record): array => $this->row('customer', $record->id, $record->name, $record->email, $record->phone, $record->address, $record->tin, $reveal));
        $suppliers = Supplier::query()->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('tin', 'like', $like))->limit(50)->get()
            ->map(fn (Supplier $record): array => $this->row('supplier', $record->id, $record->name, $record->email, $record->phone, $record->address, $record->tin, $reveal));
        $users = User::query()->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like))->limit(50)->get()
            ->map(fn (User $record): array => $this->row('user', $record->id, $record->name, $record->email, null, null, null, $reveal));
        $proprietors = BusinessProfile::query()->where(fn ($query) => $query->where('proprietor_name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('tin', 'like', $like))->limit(10)->get()
            ->map(fn (BusinessProfile $record): array => $this->row('proprietor', $record->id, $record->proprietor_name, $record->email, $record->phone, $record->registered_address, $record->tin, $reveal));

        return $customers->concat($suppliers)->concat($users)->concat($proprietors)->values();
    }

    /** @return array<string, mixed> */
    private function row(string $type, int $id, string $name, ?string $email, ?string $phone, ?string $address, ?string $tin, bool $reveal): array
    {
        return ['type' => $type, 'id' => $id, 'name' => $name,
            'email' => $reveal ? $email : SensitiveData::email($email),
            'phone' => $reveal ? $phone : SensitiveData::mask($phone, 3, '—'),
            'address' => $reveal ? $address : (filled($address) ? '[PROTECTED]' : '—'),
            'tin' => $reveal ? $tin : SensitiveData::mask($tin, 4, '—')];
    }
}
