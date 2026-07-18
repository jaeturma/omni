<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\ReceivingStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPaymentStatus;
use App\Models\CanvassQuotation;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchasingAttachment;
use App\Models\ReceivingRecord;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchasingAttachmentManager
{
    /** @var array<string, class-string<Model>> */
    public const ATTACHABLE_TYPES = ['purchase_request' => PurchaseRequest::class, 'canvass_quotation' => CanvassQuotation::class,
        'purchase_order' => PurchaseOrder::class, 'receiving_record' => ReceivingRecord::class, 'supplier_invoice' => SupplierInvoice::class,
        'supplier_payment' => SupplierPayment::class, 'expense' => Expense::class];

    public function resolve(string $type, int $id): PurchaseRequest|CanvassQuotation|PurchaseOrder|ReceivingRecord|SupplierInvoice|SupplierPayment|Expense
    {
        $model = self::ATTACHABLE_TYPES[$type] ?? null;
        abort_unless($model !== null, 404);

        return $model::query()->findOrFail($id);
    }

    public function store(PurchaseRequest|CanvassQuotation|PurchaseOrder|ReceivingRecord|SupplierInvoice|SupplierPayment|Expense $record, UploadedFile $file, array $metadata, User $user): PurchasingAttachment
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        $storedFilename = 'purchasing-attachments/'.now()->format('Y/m').'/'.Str::uuid().($extension ? '.'.$extension : '');
        $hash = hash_file('sha256', $file->getRealPath());
        if (! Storage::disk('local')->putFileAs(dirname($storedFilename), $file, basename($storedFilename))) {
            throw ValidationException::withMessages(['file' => 'The attachment could not be stored.']);
        }
        try {
            return DB::transaction(fn (): PurchasingAttachment => $record->purchasingAttachments()->create($metadata + [
                'original_filename' => $file->getClientOriginalName(), 'stored_filename' => $storedFilename,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'file_size' => $file->getSize(),
                'file_hash' => $hash, 'uploaded_by' => $user->id]));
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedFilename);
            throw $exception;
        }
    }

    public function delete(PurchasingAttachment $attachment, User $user, string $reason): void
    {
        DB::transaction(function () use ($attachment, $user, $reason): void {
            $attachment->forceFill(['deleted_by' => $user->id, 'deletion_reason' => $reason])->save();
            $attachment->delete();
        });
        Storage::disk('local')->delete($attachment->stored_filename);
    }

    public function isProtected(PurchaseRequest|CanvassQuotation|PurchaseOrder|ReceivingRecord|SupplierInvoice|SupplierPayment|Expense $record): bool
    {
        return match (true) {
            $record instanceof PurchaseRequest => $record->status !== PurchaseRequestStatus::Draft,
            $record instanceof CanvassQuotation => PurchaseRequest::query()->findOrFail($record->purchase_request_id)->status !== PurchaseRequestStatus::Draft,
            $record instanceof PurchaseOrder => $record->status !== PurchaseOrderStatus::Draft,
            $record instanceof ReceivingRecord => $record->status !== ReceivingStatus::Draft,
            $record instanceof SupplierInvoice => $record->status !== SupplierInvoiceStatus::Draft,
            $record instanceof SupplierPayment => $record->status !== SupplierPaymentStatus::Draft,
            $record instanceof Expense => $record->status !== ExpenseStatus::Draft,
        };
    }
}
