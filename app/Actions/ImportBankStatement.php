<?php

namespace App\Actions;

use App\Enums\ReconciliationState;
use App\Models\BankStatementImport;
use App\Models\FinancialAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use SplFileObject;
use Throwable;

class ImportBankStatement
{
    public function handle(array $data, UploadedFile $file, User $user): BankStatementImport
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $mapping = collect(['transaction_date', 'posting_date', 'description', 'reference_number', 'debit', 'credit', 'running_balance'])
            ->mapWithKeys(fn (string $field) => [$field => $data[$field.'_column'] ?? null])->all();
        $lines = $this->parse($file, $mapping, $data['date_format'], $data['statement_start_date'], $data['statement_end_date']);

        return DB::transaction(function () use ($data, $file, $user, $hash, $mapping, $lines): BankStatementImport {
            $account = FinancialAccount::query()->lockForUpdate()->findOrFail($data['financial_account_id']);
            if (BankStatementImport::query()->whereBelongsTo($account)->where('file_hash', $hash)->exists()) {
                throw ValidationException::withMessages(['statement_file' => 'This statement file has already been imported for the selected account.']);
            }

            $import = BankStatementImport::query()->create([
                'financial_account_id' => $account->id, 'statement_start_date' => $data['statement_start_date'],
                'statement_end_date' => $data['statement_end_date'], 'source_filename' => $file->getClientOriginalName(),
                'file_hash' => $hash, 'column_mapping' => $mapping + ['date_format' => $data['date_format']],
                'imported_by' => $user->id, 'imported_at' => now(),
            ]);
            $import->lines()->createMany($lines);

            return $import;
        });
    }

    private function parse(UploadedFile $file, array $mapping, string $dateFormat, string $start, string $end): array
    {
        $csv = new SplFileObject($file->getRealPath());
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        $header = $csv->fgetcsv();
        if (! is_array($header) || $header === [null]) {
            throw ValidationException::withMessages(['statement_file' => 'The CSV file must contain a header row.']);
        }
        $header = array_map(fn ($value) => trim((string) $value, "\xEF\xBB\xBF \t\n\r\0\x0B"), $header);
        if (count($header) !== count(array_unique($header)) || in_array('', $header, true)) {
            throw ValidationException::withMessages(['statement_file' => 'CSV headers must be non-empty and unique.']);
        }
        foreach (array_filter($mapping) as $column) {
            if (! in_array($column, $header, true)) {
                throw ValidationException::withMessages(['statement_file' => "Mapped column [{$column}] was not found in the CSV header."]);
            }
        }

        $lines = [];
        $lineNumber = 1;
        while (! $csv->eof()) {
            $lineNumber++;
            $values = $csv->fgetcsv();
            if (! is_array($values) || $values === [null] || collect($values)->every(fn ($value) => blank($value))) {
                continue;
            }
            if (count($values) !== count($header)) {
                $this->invalidRow($lineNumber, 'does not contain the same number of values as the header');
            }
            $row = array_combine($header, array_map(fn ($value) => (string) $value, $values));
            try {
                $transactionDate = $this->date($row[$mapping['transaction_date']], $dateFormat);
                $postingDate = filled($mapping['posting_date']) ? $this->date($row[$mapping['posting_date']], $dateFormat) : $transactionDate;
                $debit = $this->money($row[$mapping['debit']]);
                $credit = $this->money($row[$mapping['credit']]);
                $balance = filled($mapping['running_balance']) && filled($row[$mapping['running_balance']])
                    ? $this->money($row[$mapping['running_balance']], true) : null;
            } catch (Throwable) {
                $this->invalidRow($lineNumber, 'contains an invalid date or monetary value');
            }
            if ($transactionDate < $start || $transactionDate > $end || $postingDate < $start || $postingDate > $end) {
                $this->invalidRow($lineNumber, 'contains a date outside the statement period');
            }
            if (bccomp($debit, '0', 4) < 0 || bccomp($credit, '0', 4) < 0 || (bccomp($debit, '0', 4) > 0 && bccomp($credit, '0', 4) > 0)) {
                $this->invalidRow($lineNumber, 'must have non-negative debit and credit values, with at most one non-zero value');
            }
            $description = trim($row[$mapping['description']]);
            if ($description === '') {
                $this->invalidRow($lineNumber, 'must contain a description');
            }
            $lines[] = [
                'line_number' => $lineNumber, 'transaction_date' => $transactionDate, 'posting_date' => $postingDate,
                'description' => $description, 'reference_number' => filled($mapping['reference_number']) ? trim($row[$mapping['reference_number']]) ?: null : null,
                'debit' => $debit, 'credit' => $credit, 'running_balance' => $balance,
                'normalized_amount' => bcsub($credit, $debit, 4), 'match_status' => ReconciliationState::Unreconciled->value,
                'original_values' => $row,
            ];
        }
        if ($lines === []) {
            throw ValidationException::withMessages(['statement_file' => 'The CSV file does not contain any statement lines.']);
        }

        return $lines;
    }

    private function date(string $value, string $format): string
    {
        $date = CarbonImmutable::createFromFormat('!'.$format, trim($value));
        if ($date->format($format) !== trim($value)) {
            throw new \InvalidArgumentException;
        }

        return $date->toDateString();
    }

    private function money(string $value, bool $signed = false): string
    {
        $value = str_replace([',', ' ', 'PHP', '₱'], '', trim($value));
        if ($value === '') {
            return '0.0000';
        }
        if (! preg_match($signed ? '/^-?\d+(?:\.\d{1,4})?$/' : '/^\d+(?:\.\d{1,4})?$/', $value)) {
            throw new \InvalidArgumentException;
        }

        return bcadd($value, '0', 4);
    }

    private function invalidRow(int $line, string $message): never
    {
        throw ValidationException::withMessages(['statement_file' => "CSV row {$line} {$message}."]);
    }
}
