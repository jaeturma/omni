<?php

namespace App\Services;

use App\Enums\PostingSourceType;
use App\Models\PostingRule;
use DomainException;

class ResolvePostingRule
{
    public function resolve(PostingSourceType $sourceType, string $date, array $dimensions = []): PostingRule
    {
        $rules = PostingRule::query()
            ->with(['debitAccount', 'creditAccount'])
            ->where('source_type', $sourceType)
            ->effectiveOn($date)
            ->get()
            ->filter(fn (PostingRule $rule) => collect(PostingRule::DIMENSIONS)->every(
                fn (string $dimension) => $rule->getAttribute($dimension) === null
                    || (isset($dimensions[$dimension]) && (string) $rule->getAttribute($dimension) === (string) $dimensions[$dimension])
            ));

        if ($rules->isEmpty()) {
            throw new DomainException('No effective posting rule matches the source and dimensions.');
        }

        $highestSpecificity = $rules->max(fn (PostingRule $rule) => $rule->specificity());
        $matches = $rules->filter(fn (PostingRule $rule) => $rule->specificity() === $highestSpecificity);
        if ($matches->count() !== 1) {
            throw new DomainException('The posting rule mapping is ambiguous.');
        }

        $rule = $matches->firstOrFail();
        $rule->debitAccount->assertPostable();
        $rule->creditAccount->assertPostable();

        return $rule;
    }

    /** @return array{source_type: string, date: string, total_debit: string, total_credit: string, lines: array<int, array<string, mixed>>} */
    public function preview(PostingSourceType $sourceType, string $date, string $amount, array $dimensions = []): array
    {
        $rule = $this->resolve($sourceType, $date, $dimensions);
        $normalizedAmount = bcadd($amount, '0', 4);
        $roles = $sourceType->roles();

        return [
            'source_type' => $sourceType->value,
            'date' => $date,
            'total_debit' => $normalizedAmount,
            'total_credit' => $normalizedAmount,
            'lines' => [
                ['side' => 'debit', 'role' => $roles['debit'], 'account' => $rule->debitAccount, 'amount' => $normalizedAmount],
                ['side' => 'credit', 'role' => $roles['credit'], 'account' => $rule->creditAccount, 'amount' => $normalizedAmount],
            ],
        ];
    }
}
