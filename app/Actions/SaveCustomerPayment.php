<?php

namespace App\Actions;

use App\Models\CustomerPayment;
use Illuminate\Support\Facades\DB;

class SaveCustomerPayment
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, int $userId, ?CustomerPayment $payment = null): CustomerPayment
    {
        return DB::transaction(function () use ($data, $userId, $payment) {
            $header = [
                'customer_id' => $data['customer_id'], 'payment_method_id' => $data['payment_method_id'],
                'bank_id' => $data['bank_id'] ?? null, 'payment_date' => $data['payment_date'],
                'reference_number' => $data['reference_number'] ?? null,
                'gross_settlement_amount' => $data['gross_settlement_amount'],
                'withholding_amount' => $data['withholding_amount'] ?? '0',
                'other_deductions' => $data['other_deductions'] ?? '0',
                'net_cash_received' => $data['net_cash_received'],
                'unapplied_amount' => $data['gross_settlement_amount'],
                'notes' => $data['notes'] ?? null, 'updated_by' => $userId,
            ];

            if ($payment) {
                $payment->update($header);
            } else {
                $payment = CustomerPayment::create($header + ['created_by' => $userId]);
            }

            return $payment;
        });
    }
}
