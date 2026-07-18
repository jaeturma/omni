<?php

namespace App\Http\Controllers;

use App\Actions\SaveReceivingRecord;
use App\Actions\TransitionReceivingRecord;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceivingStatus;
use App\Http\Requests\StoreReceivingRecordRequest;
use App\Http\Requests\TransitionReceivingRecordRequest;
use App\Models\PurchaseOrder;
use App\Models\ReceivingRecord;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReceivingRecordController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ReceivingRecord::class);
        $receivingRecords = ReceivingRecord::query()->with(['purchaseOrder:id,purchase_order_number', 'supplier:id,name'])->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('receiving_number', 'like', '%'.$request->string('search').'%')->orWhere('delivery_receipt_number', 'like', '%'.$request->string('search').'%')->orWhere('supplier_name', 'like', '%'.$request->string('search').'%')))->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))->latest('receiving_date')->latest('id')->paginate(25)->withQueryString();

        $receivableOrders = PurchaseOrder::query()->whereIn('status', [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Issued, PurchaseOrderStatus::PartiallyReceived])->latest('order_date')->limit(10)->get(['id', 'purchase_order_number', 'supplier_name']);

        return view('receiving-records.index', compact('receivingRecords', 'receivableOrders'));
    }

    public function create(PurchaseOrder $purchaseOrder): View
    {
        Gate::authorize('create', ReceivingRecord::class);
        abort_unless(in_array($purchaseOrder->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Issued, PurchaseOrderStatus::PartiallyReceived], true), 404);

        return view('receiving-records.create', ['purchaseOrder' => $purchaseOrder->load('lines'), 'warehouses' => Warehouse::query()->where('status', 'active')->orderBy('name')->get(), 'users' => User::query()->where('active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(StoreReceivingRecordRequest $request, SaveReceivingRecord $save): RedirectResponse
    {
        $record = $save->handle($request->validated(), $request->user()->id);

        return redirect()->route('receiving-records.show', $record)->with('success', 'Receiving record draft created.');
    }

    public function show(ReceivingRecord $receivingRecord): View
    {
        Gate::authorize('view', $receivingRecord);

        return view('receiving-records.show', ['receivingRecord' => $receivingRecord->load(['purchaseOrder', 'supplier', 'warehouse', 'receiver', 'inspector', 'accepter', 'lines'])]);
    }

    public function destroy(ReceivingRecord $receivingRecord): RedirectResponse
    {
        Gate::authorize('delete', $receivingRecord);
        $receivingRecord->delete();

        return redirect()->route('receiving-records.index')->with('success', 'Receiving draft deleted.');
    }

    public function transition(TransitionReceivingRecordRequest $request, ReceivingRecord $receivingRecord, TransitionReceivingRecord $transition): RedirectResponse
    {
        $transition->handle($receivingRecord, ReceivingStatus::from($request->validated('status')), $request->user()->id, $request->validated('reason'));

        return back()->with('success', 'Receiving status updated.');
    }

    public function print(ReceivingRecord $receivingRecord): View
    {
        Gate::authorize('print', $receivingRecord);

        return view('receiving-records.print', ['receivingRecord' => $receivingRecord->load(['purchaseOrder', 'supplier', 'warehouse', 'lines'])]);
    }
}
