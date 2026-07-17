<?php

namespace App\Services;

use App\Enums\CustomerPaymentStatus;
use App\Enums\DeliveryStatus;
use App\Enums\GovernmentDeductionStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\GovernmentDeduction;
use App\Models\Quotation;
use App\Models\SalesAttachment;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesAttachmentManager
{
    /** @var array<string, class-string<Model>> */
    public const ATTACHABLE_TYPES = [
        'quotation' => Quotation::class, 'sales_order' => SalesOrder::class, 'delivery' => Delivery::class,
        'sales_invoice' => SalesInvoice::class, 'customer_payment' => CustomerPayment::class,
        'government_deduction' => GovernmentDeduction::class,
    ];

    public function resolve(string $type, int $id): Quotation|SalesOrder|Delivery|SalesInvoice|CustomerPayment|GovernmentDeduction
    {
        $model = self::ATTACHABLE_TYPES[$type] ?? null;
        abort_unless($model !== null, 404);

        return $model::query()->findOrFail($id);
    }

    /** @param array<string, mixed> $metadata */
    public function store(Quotation|SalesOrder|Delivery|SalesInvoice|CustomerPayment|GovernmentDeduction $record, UploadedFile $file, array $metadata, User $user): SalesAttachment
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedFilename = 'sales-attachments/'.now()->format('Y/m').'/'.Str::uuid().($extension ? '.'.$extension : '');
        $hash = hash_file('sha256', $file->getRealPath());

        if (! Storage::disk('local')->putFileAs(dirname($storedFilename), $file, basename($storedFilename))) {
            throw ValidationException::withMessages(['file' => 'The attachment could not be stored.']);
        }

        try {
            return DB::transaction(fn (): SalesAttachment => $record->salesAttachments()->create($metadata + [
                'original_filename' => $file->getClientOriginalName(), 'stored_filename' => $storedFilename,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'file_size' => $file->getSize(),
                'file_hash' => $hash, 'uploaded_by' => $user->id,
            ]));
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedFilename);
            throw $exception;
        }
    }

    public function delete(SalesAttachment $attachment, User $user, string $reason): void
    {
        DB::transaction(function () use ($attachment, $user, $reason): void {
            $attachment->forceFill(['deleted_by' => $user->id, 'deletion_reason' => $reason])->save();
            $attachment->delete();
        });
        Storage::disk('local')->delete($attachment->stored_filename);
    }

    public function isProtected(Model $record): bool
    {
        return match (true) {
            $record instanceof Quotation => $record->status !== QuotationStatus::Draft,
            $record instanceof SalesOrder => $record->status !== SalesOrderStatus::Draft,
            $record instanceof Delivery => $record->status !== DeliveryStatus::Draft,
            $record instanceof SalesInvoice => $record->status !== SalesInvoiceStatus::Draft,
            $record instanceof CustomerPayment => $record->status !== CustomerPaymentStatus::Draft,
            $record instanceof GovernmentDeduction => $record->status !== GovernmentDeductionStatus::Pending,
            default => true,
        };
    }
}
