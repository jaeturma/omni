<?php

namespace App\Services;

use App\Models\Bir1701qWorksheet;
use App\Models\Bir2551qWorksheet;
use App\Models\TaxFiling;
use App\Models\TaxFilingAttachment;
use App\Models\TaxFilingPayment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TaxFilingHistory
{
    public const PERMISSIONS = ['tax-filings.view', 'tax-filings.record', 'tax-filings.amend', 'tax-payments.record', 'tax-attachments.view', 'tax-attachments.upload'];

    /** @param array<string, mixed> $data */
    public function recordFiling(array $data, User $user): TaxFiling
    {
        return DB::transaction(function () use ($data, $user): TaxFiling {
            $worksheet = $this->worksheet((string) $data['worksheet_type'], (int) $data['worksheet_id']);
            if ($worksheet->frozen_at === null) {
                throw ValidationException::withMessages(['worksheet_id' => 'Only an approved and frozen worksheet revision may be recorded as filed.']);
            }
            $worksheetColumn = $worksheet instanceof Bir2551qWorksheet ? 'bir2551q_worksheet_id' : 'bir1701q_worksheet_id';
            if (TaxFiling::query()->where($worksheetColumn, $worksheet->id)->exists()) {
                throw ValidationException::withMessages(['worksheet_reference' => 'This worksheet revision already has a filing record.']);
            }
            $expected = (string) $worksheet->total_amount_payable;
            $declared = (string) $data['amount_declared'];
            if (bccomp($expected, $declared, 4) !== 0) {
                throw ValidationException::withMessages(['amount_declared' => 'The declared amount must reconcile exactly to the frozen worksheet amount payable.']);
            }
            $isAmended = (bool) ($data['is_amended'] ?? false);
            $original = isset($data['original_filing_id']) ? TaxFiling::query()->find($data['original_filing_id']) : null;
            if ($isAmended && (! $original || $original->tax_obligation_id !== $worksheet->tax_obligation_id || $original->is_amended)) {
                throw ValidationException::withMessages(['original_filing_id' => 'Select the original filing for the same tax obligation.']);
            }
            if (! $isAmended && $original) {
                throw ValidationException::withMessages(['original_filing_id' => 'Original linkage is only allowed for amended filings.']);
            }
            $filing = TaxFiling::query()->create([
                'tax_obligation_id' => $worksheet->tax_obligation_id, 'bir2551q_worksheet_id' => $worksheet instanceof Bir2551qWorksheet ? $worksheet->id : null,
                'bir1701q_worksheet_id' => $worksheet instanceof Bir1701qWorksheet ? $worksheet->id : null, 'original_filing_id' => $original?->id,
                'bir_form_number' => $worksheet instanceof Bir2551qWorksheet ? '2551Q' : '1701Q', 'worksheet_revision' => $worksheet->revision_number,
                'filing_channel' => $data['filing_channel'], 'filing_date' => $data['filing_date'], 'return_reference' => $data['return_reference'],
                'is_amended' => $isAmended, 'amendment_reason' => $data['amendment_reason'] ?? null,
                'worksheet_amount_payable' => $expected, 'amount_declared' => $declared, 'declared_difference' => bcsub($declared, $expected, 4),
                'confirmed_at' => now(), 'filed_by' => $user->id, 'reviewed_by' => $data['reviewed_by'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
            $worksheet->taxObligation->update(['status' => 'filed', 'filing_status' => 'filed', 'amendment_status' => $isAmended ? 'amended' : 'original']);

            return $filing;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function recordPayment(TaxFiling $filing, array $data, User $user): TaxFilingPayment
    {
        return DB::transaction(function () use ($filing, $data, $user): TaxFilingPayment {
            $payment = TaxFilingPayment::query()->create(['tax_filing_id' => $filing->id, 'payment_channel' => $data['payment_channel'], 'payment_date' => $data['payment_date'], 'payment_reference' => $data['payment_reference'], 'amount_paid' => $data['amount_paid'], 'bank_or_provider' => $data['bank_or_provider'] ?? null, 'notes' => $data['notes'] ?? null, 'recorded_by' => $user->id]);
            $filing->taxObligation->update(['payment_status' => $filing->fresh()->paymentStatus()]);

            return $payment;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function storeAttachment(TaxFiling $filing, UploadedFile $file, array $data, User $user): TaxFilingAttachment
    {
        $paymentId = $data['tax_filing_payment_id'] ?? null;
        if ($paymentId && ! $filing->payments()->whereKey($paymentId)->exists()) {
            throw ValidationException::withMessages(['tax_filing_payment_id' => 'The payment attachment must belong to this filing.']);
        }
        $name = 'tax-filings/'.$filing->id.'/'.Str::uuid().'.'.$file->extension();
        Storage::disk('local')->putFileAs(dirname($name), $file, basename($name));

        return TaxFilingAttachment::query()->create(['tax_filing_id' => $filing->id, 'tax_filing_payment_id' => $paymentId, 'attachment_type' => $data['attachment_type'], 'original_filename' => $file->getClientOriginalName(), 'stored_filename' => $name, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'file_size' => $file->getSize(), 'file_hash' => hash_file('sha256', $file->getPathname()), 'notes' => $data['notes'] ?? null, 'uploaded_by' => $user->id]);
    }

    private function worksheet(string $type, int $id): Bir2551qWorksheet|Bir1701qWorksheet
    {
        return match ($type) {
            '2551q' => Bir2551qWorksheet::query()->with('taxObligation')->findOrFail($id),
            '1701q' => Bir1701qWorksheet::query()->with('taxObligation')->findOrFail($id),
            default => throw ValidationException::withMessages(['worksheet_reference' => 'Select a supported worksheet revision.']),
        };
    }
}
