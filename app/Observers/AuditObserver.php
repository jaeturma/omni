<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditObserver
{
    private const ACTOR_FIELDS = ['created_by', 'updated_by', 'posted_by', 'voided_by', 'reversed_by', 'uploaded_by', 'prepared_by', 'filed_by', 'recorded_by', 'reviewed_by', 'approved_by', 'deleted_by'];

    public function __construct(private readonly AuditLogger $logger) {}

    public function created(Model $model): void
    {
        if ($this->shouldAudit($model)) {
            $this->logger->log($this->module($model).'.created', $model, after: $model->getAttributes());
        }
    }

    public function updated(Model $model): void
    {
        if (! $this->shouldAudit($model)) {
            return;
        }
        $changes = $model->getChanges();
        $before = array_intersect_key($model->getOriginal(), $changes);
        $action = 'updated';
        if (array_key_exists('active', $changes)) {
            $action = $model->getAttribute('active') ? 'activated' : 'deactivated';
        } elseif (array_key_exists('status', $changes)) {
            $status = $model->getAttribute('status');
            $action = (string) ($status instanceof BackedEnum ? $status->value : $status);
        }
        $reason = collect(['reason', 'void_reason', 'reversal_reason', 'reopen_reason', 'revision_reason', 'deletion_reason'])
            ->map(fn (string $field) => $model->getAttribute($field))->first(fn (mixed $value) => filled($value));
        $this->logger->log($this->module($model).'.'.$action, $model, $before, $changes, is_scalar($reason) ? (string) $reason : null);
    }

    public function deleted(Model $model): void
    {
        if ($this->shouldAudit($model)) {
            $this->logger->log($this->module($model).'.deleted', $model, $model->getOriginal(), reason: (string) ($model->getAttribute('deletion_reason') ?? ''));
        }
    }

    private function shouldAudit(Model $model): bool
    {
        return config('audit.enabled', true)
            && (! app()->runningUnitTests() || config('audit.capture_during_tests', false))
            && ! $model instanceof AuditLog
            && ($model instanceof User || array_intersect(self::ACTOR_FIELDS, array_keys($model->getAttributes())) !== []);
    }

    private function module(Model $model): string
    {
        return Str::snake(class_basename($model));
    }
}
