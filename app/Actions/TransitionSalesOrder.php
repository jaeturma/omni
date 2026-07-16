<?php

namespace App\Actions;

use App\Enums\SalesOrderStatus;
use App\Models\DocumentSequence;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionSalesOrder
{
    public function __construct(private IssueDocumentNumber $issue) {}

    public function handle(SalesOrder $order, SalesOrderStatus $target, int $userId, ?string $reason = null): SalesOrder
    {
        return DB::transaction(function () use ($order, $target, $userId, $reason) {
            $locked = SalesOrder::lockForUpdate()->findOrFail($order->id);
            if (! $locked->status->canTransitionTo($target)) {
                throw ValidationException::withMessages(['status' => 'This sales-order transition is not allowed.']);
            }$changes = ['status' => $target, 'updated_by' => $userId];
            if ($target === SalesOrderStatus::Confirmed) {
                $seq = DocumentSequence::where('document_type', 'sales_order')->where('active', true)->whereHas('fiscalYear', fn ($q) => $q->whereDate('starts_on', '<=', $locked->order_date)->whereDate('ends_on', '>=', $locked->order_date))->first();
                if (! $seq) {
                    throw ValidationException::withMessages(['status' => 'Configure an active sales-order sequence for this order date.']);
                }$r = $this->issue->handle($seq, $userId);
                $changes += ['sales_order_number' => $r->document_number, 'document_number_reservation_id' => $r->id, 'confirmed_at' => now(), 'confirmed_by' => $userId];
            }if ($target === SalesOrderStatus::Cancelled) {
                $changes += ['cancelled_at' => now(), 'cancelled_by' => $userId, 'cancellation_reason' => $reason];
            }if ($target === SalesOrderStatus::Closed) {
                $changes += ['closed_at' => now(), 'closed_by' => $userId];
            }$locked->update($changes);

            return $locked->fresh('lines');
        }, 3);
    }
}
