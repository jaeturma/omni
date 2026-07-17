<?php

namespace App\Actions;

use App\Enums\GovernmentDeductionStatus;
use App\Models\GovernmentDeduction;
use App\Models\SalesInvoice;
use App\Models\TaxRateSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveGovernmentDeduction
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, int $userId, ?GovernmentDeduction $deduction = null): GovernmentDeduction
    {
        return DB::transaction(function () use ($data, $userId, $deduction) {
            $invoice = SalesInvoice::findOrFail($data['sales_invoice_id']);
            $rate = TaxRateSetting::query()->whereKey($data['tax_rate_setting_id'])->where('active', true)
                ->where('tax_type', $data['deduction_type'])->whereDate('effective_from', '<=', $invoice->invoice_date)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $invoice->invoice_date))->first();
            if (! $rate) {
                throw ValidationException::withMessages(['tax_rate_setting_id' => 'Select a matching tax rate effective on the invoice date.']);
            }
            $amount = bcdiv(bcmul((string) $data['gross_basis'], $rate->rate, 10), '100', 4);
            $status = filled($data['certificate_number'] ?? null) && filled($data['certificate_date'] ?? null)
                ? GovernmentDeductionStatus::Received : GovernmentDeductionStatus::Pending;
            $values = [
                'customer_id' => $invoice->customer_id, 'sales_invoice_id' => $invoice->id,
                'customer_payment_id' => $data['customer_payment_id'] ?? null, 'tax_rate_setting_id' => $rate->id,
                'deduction_type' => $data['deduction_type'], 'certificate_type' => $data['certificate_type'],
                'certificate_number' => $data['certificate_number'] ?? null, 'certificate_date' => $data['certificate_date'] ?? null,
                'covered_from' => $data['covered_from'], 'covered_to' => $data['covered_to'],
                'gross_basis' => $data['gross_basis'], 'rate' => $rate->rate, 'amount' => $amount,
                'status' => $status, 'notes' => $data['notes'] ?? null,
                'attachment_reference' => $data['attachment_reference'] ?? null, 'updated_by' => $userId,
            ];
            if ($deduction) {
                $deduction->update($values);
            } else {
                $deduction = GovernmentDeduction::create($values + ['created_by' => $userId]);
            }

            return $deduction;
        });
    }
}
