<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentMethodRequest;
use App\Http\Requests\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PaymentMethod::class);
        $paymentMethods = PaymentMethod::query()->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', '%'.$request->string('search').'%')->orWhere('name', 'like', '%'.$request->string('search').'%')))->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))->orderBy('name')->paginate(25)->withQueryString();

        return view('payment-methods.index', ['paymentMethods' => $paymentMethods]);
    }

    public function create(): View
    {
        Gate::authorize('create', PaymentMethod::class);

        return view('payment-methods.create');
    }

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        PaymentMethod::query()->create($request->validated() + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return redirect()->route('payment-methods.index')->with('success', 'Payment method created.');
    }

    public function edit(PaymentMethod $paymentMethod): View
    {
        Gate::authorize('update', $paymentMethod);

        return view('payment-methods.edit', ['paymentMethod' => $paymentMethod]);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->update($request->validated() + ['updated_by' => $request->user()->id]);

        return redirect()->route('payment-methods.index')->with('success', 'Payment method updated.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        Gate::authorize('delete', $paymentMethod);
        $paymentMethod->delete();

        return redirect()->route('payment-methods.index')->with('success', 'Payment method deleted.');
    }
}
