<x-app-layout title="Tax Filing {{ $filing->return_reference }}">
    <div class="flex flex-col gap-6">
        <div><a href="{{ route('tax-filings.index') }}" class="text-sm text-blue-700">Back to filing history</a><h1 class="mt-2 text-2xl font-semibold">{{ $filing->bir_form_number }} | {{ $filing->return_reference }}</h1><p class="text-sm text-slate-600">Immutable manual filing record | {{ $filing->taxObligation->taxPeriod->label }}</p></div>
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">This history records evidence supplied by users. It is not a BIR acknowledgement and does not represent filing or payment through Omni.</div>

        <section class="grid gap-4 rounded-2xl bg-white p-5 ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(['Channel' => $filing->filing_channel, 'Filing date' => $filing->filing_date->toDateString(), 'Worksheet amount' => 'PHP '.number_format((float) $filing->worksheet_amount_payable, 4), 'Declared' => 'PHP '.number_format((float) $filing->amount_declared, 4), 'Paid' => 'PHP '.number_format((float) $filing->amountPaid(), 4), 'Balance' => 'PHP '.number_format((float) $filing->paymentBalance(), 4), 'Payment status' => str($filing->paymentStatus())->headline(), 'Filed by' => $filing->filedBy->name] as $label => $value)<div><div class="text-xs uppercase text-slate-500">{{ $label }}</div><div class="font-semibold">{{ $value }}</div></div>@endforeach
        </section>

        @can('tax-payments.record')
            <form method="POST" action="{{ route('tax-filings.payments.store', $filing) }}" class="grid gap-4 rounded-2xl bg-white p-5 ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-3">@csrf
                <h2 class="font-semibold sm:col-span-2 lg:col-span-3">Record payment</h2>
                <label class="grid gap-1 text-sm">Channel<input name="payment_channel" list="payment-channels" required class="rounded-lg border-slate-300"><datalist id="payment-channels"><option value="Authorized agent bank"><option value="Online payment"><option value="eFPS"><option value="Other"></datalist></label>
                <label class="grid gap-1 text-sm">Date<input type="date" name="payment_date" required class="rounded-lg border-slate-300"></label>
                <label class="grid gap-1 text-sm">Reference<input name="payment_reference" required class="rounded-lg border-slate-300"></label>
                <label class="grid gap-1 text-sm">Amount<input name="amount_paid" required inputmode="decimal" class="rounded-lg border-slate-300"></label>
                <label class="grid gap-1 text-sm">Bank / provider<input name="bank_or_provider" class="rounded-lg border-slate-300"></label>
                <div><button class="mt-5 rounded-lg bg-emerald-700 px-4 py-2 text-white">Record payment</button></div>
            </form>
        @endcan

        <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200"><h2 class="font-semibold">Payment history</h2><div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><tbody>@forelse($filing->payments as $payment)<tr><td class="py-2">{{ $payment->payment_date->toDateString() }}</td><td>{{ $payment->payment_reference }}</td><td>{{ $payment->payment_channel }}</td><td class="text-right">PHP {{ number_format((float) $payment->amount_paid, 4) }}</td></tr>@empty<tr><td class="py-4 text-slate-500">No payments recorded.</td></tr>@endforelse</tbody></table></div></section>

        @can('tax-attachments.upload')
            <form method="POST" enctype="multipart/form-data" action="{{ route('tax-filings.attachments.store', $filing) }}" class="grid gap-4 rounded-2xl bg-white p-5 ring-1 ring-slate-200 sm:grid-cols-2">@csrf
                <h2 class="font-semibold sm:col-span-2">Upload private evidence</h2>
                <label class="grid gap-1 text-sm">Type<select name="attachment_type" class="rounded-lg border-slate-300"><option value="proof_of_filing">Proof of filing</option><option value="proof_of_payment">Proof of payment</option><option value="acknowledgement">Acknowledgement supplied by user</option></select></label>
                <label class="grid gap-1 text-sm">Payment<select name="tax_filing_payment_id" class="rounded-lg border-slate-300"><option value="">Filing-level evidence</option>@foreach($filing->payments as $payment)<option value="{{ $payment->id }}">{{ $payment->payment_reference }}</option>@endforeach</select></label>
                <input type="file" name="file" required class="sm:col-span-2"><div><button class="rounded-lg bg-blue-700 px-4 py-2 text-white">Upload privately</button></div>
            </form>
        @endcan

        <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200"><h2 class="font-semibold">Attachments</h2><ul class="mt-3 grid gap-2">@forelse($filing->attachments as $attachment)<li class="flex justify-between gap-3"><span>{{ str($attachment->attachment_type)->headline() }} | {{ $attachment->original_filename }}</span>@can('tax-attachments.view')<a class="text-blue-700" href="{{ route('tax-filing-attachments.download', $attachment) }}">Private download</a>@endcan</li>@empty<li class="text-sm text-slate-500">No attachments uploaded.</li>@endforelse</ul></section>
    </div>
</x-app-layout>
