<?php

namespace App\Logging;

use Monolog\LogRecord;

class RedactSensitiveContext
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(context: $this->redact($record->context), extra: $this->redact($record->extra));
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function redact(array $values): array
    {
        return collect($values)->mapWithKeys(function (mixed $value, string $key): array {
            if (preg_match('/password|token|secret|authorization|cookie|tin|account_number|email|phone|address/i', $key)) {
                return [$key => '[REDACTED]'];
            }

            return [$key => is_array($value) ? $this->redact($value) : $value];
        })->all();
    }
}
