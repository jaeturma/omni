<?php

use App\Enums\QuotationStatus;
use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\PaymentAllocation;
use App\Models\Quotation;
use App\Models\SalesAttachment;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\SalesTraceability;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

function attachmentPayload(?UploadedFile $file = null): array
{
    return ['file' => $file ?? UploadedFile::fake()->create('purchase-order.pdf', 24, 'application/pdf'),
        'document_type' => 'Purchase order', 'document_date' => '2026-07-17', 'reference_number' => 'PO-001', 'notes' => 'Signed copy'];
}

test('private upload stores metadata and hash and authorized user can download', function () {
    $user = User::factory()->administrator()->create();
    $quotation = Quotation::factory()->create();
    $contents = 'immutable attachment contents';
    $file = UploadedFile::fake()->createWithContent('purchase-order.pdf', $contents);

    $this->actingAs($user)->post(route('sales-attachments.store', ['quotation', $quotation->id]), attachmentPayload($file))->assertRedirect();
    $attachment = SalesAttachment::sole();

    expect($attachment->original_filename)->toBe('purchase-order.pdf')
        ->and($attachment->stored_filename)->not->toContain('purchase-order.pdf')
        ->and($attachment->file_hash)->toBe(hash('sha256', $contents))
        ->and($attachment->document_type)->toBe('Purchase order');
    Storage::disk('local')->assertExists($attachment->stored_filename);
    $this->actingAs($user)->get(route('sales-attachments.download', $attachment))->assertOk()->assertDownload('purchase-order.pdf');
    $this->actingAs(User::factory()->create())->get(route('sales-attachments.download', $attachment))->assertForbidden();
});

test('upload validates file type and permission', function () {
    $quotation = Quotation::factory()->create();
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $admin = User::factory()->administrator()->create();

    $this->actingAs($viewer)->post(route('sales-attachments.store', ['quotation', $quotation->id]), attachmentPayload())->assertForbidden();
    $this->actingAs($admin)->post(route('sales-attachments.store', ['quotation', $quotation->id]), attachmentPayload(UploadedFile::fake()->create('script.exe', 2, 'application/octet-stream')))->assertSessionHasErrors('file');
    expect(SalesAttachment::count())->toBe(0);
});

test('draft attachment deletion is reasoned soft deleted and removes the private file', function () {
    $user = User::factory()->administrator()->create();
    $quotation = Quotation::factory()->create(['status' => QuotationStatus::Draft]);
    $this->actingAs($user)->post(route('sales-attachments.store', ['quotation', $quotation->id]), attachmentPayload());
    $attachment = SalesAttachment::sole();

    $this->actingAs($user)->delete(route('sales-attachments.destroy', $attachment), ['deletion_reason' => 'Uploaded wrong document'])->assertRedirect();

    expect(SalesAttachment::withTrashed()->find($attachment->id)->deletion_reason)->toBe('Uploaded wrong document');
    Storage::disk('local')->assertMissing($attachment->stored_filename);
});

test('attachments linked to advanced records cannot be deleted', function () {
    $user = User::factory()->administrator()->create();
    $quotation = Quotation::factory()->create(['status' => QuotationStatus::Submitted]);
    $attachment = $quotation->salesAttachments()->create(['document_type' => 'Purchase order', 'original_filename' => 'po.pdf',
        'stored_filename' => 'sales-attachments/locked.pdf', 'mime_type' => 'application/pdf', 'file_size' => 10,
        'file_hash' => str_repeat('a', 64), 'document_date' => '2026-07-17', 'uploaded_by' => $user->id]);
    Storage::disk('local')->put($attachment->stored_filename, 'locked');

    $this->actingAs($user)->delete(route('sales-attachments.destroy', $attachment), ['deletion_reason' => 'Try removal'])->assertForbidden();
    expect($attachment->fresh())->not->toBeNull();
    Storage::disk('local')->assertExists($attachment->stored_filename);
});

test('traceability exposes the sales chain from quotation through payment', function () {
    $user = User::factory()->administrator()->create();
    $quotation = Quotation::factory()->create(['quotation_number' => 'Q-TRACE']);
    $order = SalesOrder::factory()->for($quotation)->create(['sales_order_number' => 'SO-TRACE']);
    Delivery::factory()->for($order, 'salesOrder')->create(['delivery_number' => 'DR-TRACE']);
    $invoice = SalesInvoice::factory()->for($order, 'salesOrder')->create(['invoice_number' => 'SI-TRACE']);
    $payment = CustomerPayment::factory()->create(['payment_number' => 'CR-TRACE']);
    PaymentAllocation::factory()->for($payment, 'customerPayment')->for($invoice, 'salesInvoice')->create();

    $links = app(SalesTraceability::class)->links($quotation)->pluck('number');

    expect($links)->toContain('Q-TRACE', 'SO-TRACE', 'DR-TRACE', 'SI-TRACE', 'CR-TRACE');
    $this->actingAs($user)->get(route('quotations.show', $quotation))->assertOk()
        ->assertSeeInOrder(['Q-TRACE', 'SO-TRACE', 'DR-TRACE', 'SI-TRACE', 'CR-TRACE']);
});
