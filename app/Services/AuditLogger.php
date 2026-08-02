<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    private ?string $correlationId = null;

    /** @param array<string, mixed> $before @param array<string, mixed> $after @param array<string, mixed> $metadata */
    public function log(string $event, ?Model $subject = null, array $before = [], array $after = [], ?string $reason = null, array $metadata = []): AuditLog
    {
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::query()->create([
            'event' => $event,
            'module' => Str::before($event, '.'),
            'actor_id' => auth()->id() ?? $this->actorFrom($subject),
            'subject_type' => $subject?->getMorphClass() ?? 'system',
            'subject_id' => $subject?->getKey() === null ? null : (string) $subject->getKey(),
            'before_values' => $this->redact($before) ?: null,
            'after_values' => $this->redact($after) ?: null,
            'reason' => $reason,
            'source_action' => $request?->route()?->getName() ?? ($request?->route()?->getActionName()),
            'correlation_id' => $this->correlationId ??= (string) Str::uuid(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'protected_metadata' => $this->redact($metadata) ?: null,
            'occurred_at' => now(),
        ]);
    }

    private function actorFrom(?Model $subject): ?int
    {
        if (! $subject) {
            return null;
        }

        foreach (['updated_by', 'created_by', 'posted_by', 'uploaded_by', 'prepared_by', 'filed_by', 'recorded_by', 'reviewed_by', 'approved_by', 'deleted_by'] as $key) {
            if (is_numeric($subject->getAttribute($key))) {
                return (int) $subject->getAttribute($key);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function redact(array $values): array
    {
        return collect($values)->mapWithKeys(function (mixed $value, string $key): array {
            if (preg_match('/password|token|secret|remember/i', $key)) {
                return [$key => '[REDACTED]'];
            }
            if (preg_match('/(^|_)(tin|account_number|swift_code)$/i', $key) && filled($value)) {
                return [$key => Str::mask((string) $value, '*', 0, max(0, Str::length((string) $value) - 4))];
            }
            if (preg_match('/email|phone|address|contact_person|user_agent/i', $key) && filled($value)) {
                return [$key => '[PROTECTED]'];
            }

            return [$key => is_array($value) ? $this->redact($value) : $value];
        })->all();
    }
}
