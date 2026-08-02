<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\RecordArchive;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordArchiver
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function resolve(string $type, int $id): Customer|Supplier
    {
        $class = match ($type) {
            'customer' => Customer::class,
            'supplier' => Supplier::class,
            default => null,
        };
        abort_unless($class !== null, 404);

        return $class::query()->findOrFail($id);
    }

    public function archive(Customer|Supplier $record, User $actor, string $reason): RecordArchive
    {
        if ($record->status !== 'inactive') {
            throw ValidationException::withMessages(['record' => 'Only inactive master records may be archived.']);
        }

        return DB::transaction(function () use ($record, $actor, $reason): RecordArchive {
            $archive = RecordArchive::query()->create(['subject_type' => $record->getMorphClass(), 'subject_id' => $record->getKey(), 'archived_at' => now(), 'archived_by' => $actor->id, 'reason' => $reason]);
            $this->audit->log(Str::snake(class_basename($record)).'.archived', $record, after: ['archive_id' => $archive->id], reason: $reason);

            return $archive;
        });
    }
}
