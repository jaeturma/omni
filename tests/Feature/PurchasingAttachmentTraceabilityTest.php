<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\CanvassQuotation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchasingAttachment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchasingTraceability;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

function attachmentRequest(User $user, array $overrides = []): PurchaseRequest
{
    return PurchaseRequest::query()->create(array_replace(['request_date' => '2026-07-18', 'requested_by' => $user->id,
        'purpose' => 'Procure test equipment', 'estimated_total' => '1000.0000', 'status' => PurchaseRequestStatus::Draft,
        'created_by' => $user->id, 'updated_by' => $user->id], $overrides));
}

function purchasingAttachmentPayload(?UploadedFile $file = null): array
{
    return ['file' => $file ?? UploadedFile::fake()->create('request.pdf', 24, 'application/pdf'),
        'document_type' => 'purchase_request', 'document_date' => '2026-07-18', 'reference_number' => 'PR-REF', 'notes' => 'Signed copy'];
}

test('private upload stores complete metadata and immutable hash and authorized download works', function () {
    $user = User::factory()->administrator()->create();
    $request = attachmentRequest($user);
    $contents = 'immutable purchasing attachment';
    $this->actingAs($user)->post(route('purchasing-attachments.store', ['purchase_request', $request->id]),
        purchasingAttachmentPayload(UploadedFile::fake()->createWithContent('request.pdf', $contents)))->assertRedirect();
    $attachment = PurchasingAttachment::sole();

    expect($attachment->original_filename)->toBe('request.pdf')->and($attachment->stored_filename)->not->toContain('request.pdf')
        ->and($attachment->file_hash)->toBe(hash('sha256', $contents))->and($attachment->reference_number)->toBe('PR-REF')
        ->and($attachment->uploader->is($user))->toBeTrue();
    Storage::disk('local')->assertExists($attachment->stored_filename);
    $this->get(route('purchasing-attachments.download', $attachment))->assertOk()->assertDownload('request.pdf');
    $this->actingAs(User::factory()->create())->get(route('purchasing-attachments.download', $attachment))->assertForbidden();
});

test('upload validates document and file types and authorization', function () {
    $admin = User::factory()->administrator()->create();
    $request = attachmentRequest($admin);
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $this->actingAs($viewer)->post(route('purchasing-attachments.store', ['purchase_request', $request->id]), purchasingAttachmentPayload())->assertForbidden();
    $this->actingAs($admin)->post(route('purchasing-attachments.store', ['purchase_request', $request->id]),
        purchasingAttachmentPayload(UploadedFile::fake()->create('script.exe', 2, 'application/octet-stream')))->assertSessionHasErrors('file');
    $this->post(route('purchasing-attachments.store', ['purchase_request', $request->id]),
        array_replace(purchasingAttachmentPayload(), ['document_type' => 'unsupported']))->assertSessionHasErrors('document_type');
    expect(PurchasingAttachment::count())->toBe(0);
});

test('draft deletion is reasoned and safe while advanced records are protected', function () {
    $user = User::factory()->administrator()->create();
    $request = attachmentRequest($user);
    $this->actingAs($user)->post(route('purchasing-attachments.store', ['purchase_request', $request->id]), purchasingAttachmentPayload());
    $attachment = PurchasingAttachment::sole();
    $this->delete(route('purchasing-attachments.destroy', $attachment), ['deletion_reason' => 'Wrong document'])->assertRedirect();
    expect(PurchasingAttachment::withTrashed()->find($attachment->id)->deletion_reason)->toBe('Wrong document');
    Storage::disk('local')->assertMissing($attachment->stored_filename);

    $posted = attachmentRequest($user, ['status' => PurchaseRequestStatus::Submitted]);
    $locked = $posted->purchasingAttachments()->create(['document_type' => 'purchase_request', 'original_filename' => 'locked.pdf',
        'stored_filename' => 'purchasing-attachments/locked.pdf', 'mime_type' => 'application/pdf', 'file_size' => 6,
        'file_hash' => str_repeat('a', 64), 'document_date' => '2026-07-18', 'uploaded_by' => $user->id]);
    Storage::disk('local')->put($locked->stored_filename, 'locked');
    $this->delete(route('purchasing-attachments.destroy', $locked), ['deletion_reason' => 'Attempt'])->assertForbidden();
    Storage::disk('local')->assertExists($locked->stored_filename);
});

test('source traceability exposes request canvass and purchase order links on screen', function () {
    $user = User::factory()->administrator()->create();
    $supplier = Supplier::factory()->for($user, 'creator')->for($user, 'updater')->create(['name' => 'Trace Supplier']);
    $request = attachmentRequest($user, ['request_number' => 'PR-TRACE']);
    $quote = CanvassQuotation::query()->create(['purchase_request_id' => $request->id, 'supplier_id' => $supplier->id,
        'supplier_name' => $supplier->name, 'quoted_amount' => '1000.0000', 'quotation_date' => '2026-07-18', 'selected' => true,
        'created_by' => $user->id, 'updated_by' => $user->id]);
    PurchaseOrder::query()->create(['purchase_request_id' => $request->id, 'canvass_quotation_id' => $quote->id, 'supplier_id' => $supplier->id,
        'purchase_order_number' => 'PO-TRACE', 'order_date' => '2026-07-18', 'supplier_name' => $supplier->name,
        'delivery_location' => 'Office', 'status' => PurchaseOrderStatus::Draft, 'created_by' => $user->id, 'updated_by' => $user->id]);

    expect(app(PurchasingTraceability::class)->links($request)->pluck('number'))->toContain('PR-TRACE', 'Trace Supplier');
    $this->actingAs($user)->get(route('purchase-requests.show', $request))->assertOk()
        ->assertSeeInOrder(['PR-TRACE', 'Trace Supplier', 'PO-TRACE'])->assertSee('Source traceability');
});
