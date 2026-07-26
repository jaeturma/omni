<?php

namespace App\Actions;

use App\Models\PostingRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePostingRule
{
    public function handle(array $data, int $userId, ?PostingRule $postingRule = null): PostingRule
    {
        return DB::transaction(function () use ($data, $userId, $postingRule): PostingRule {
            $candidates = PostingRule::query()
                ->where('source_type', $data['source_type'])
                ->when($postingRule?->exists, fn ($query) => $query->whereKeyNot($postingRule->id))
                ->whereDate('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $data['effective_from']))
                ->lockForUpdate()
                ->get();

            $specificity = collect(PostingRule::DIMENSIONS)->filter(fn (string $dimension) => ($data[$dimension] ?? null) !== null)->count();
            $ambiguous = $candidates->contains(function (PostingRule $candidate) use ($data, $specificity): bool {
                if ($candidate->specificity() !== $specificity) {
                    return false;
                }

                return collect(PostingRule::DIMENSIONS)->every(function (string $dimension) use ($candidate, $data): bool {
                    $existing = $candidate->getAttribute($dimension);
                    $incoming = $data[$dimension] ?? null;

                    return $existing === null || $incoming === null || (string) $existing === (string) $incoming;
                });
            });

            if ($ambiguous) {
                throw ValidationException::withMessages([
                    'source_type' => 'This effective period and dimension combination overlaps an equally specific posting rule.',
                ]);
            }

            $postingRule ??= new PostingRule;
            $postingRule->fill($data + ['updated_by' => $userId]);
            if (! $postingRule->exists) {
                $postingRule->created_by = $userId;
                $postingRule->activated_at = now();
                $postingRule->activated_by = $userId;
            }
            $postingRule->save();

            return $postingRule;
        }, 3);
    }
}
