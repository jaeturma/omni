<x-app-layout title="Cash Disbursement">
    <x-page-header :title="$disbursement->disbursement_number ?? 'Draft #'.$disbursement->id" :description="str($disbursement->status->value)->headline().' disbursement to '.$disbursement->payee" />
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-2xl bg-white p-6 ring-1 ring-slate-200 lg:col-span-2">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt>Date</dt><dd>{{ $disbursement->disbursement_date->format('M d, Y') }}</dd>
                <dt>Period</dt><dd>{{ $disbursement->fiscalPeriod->name }}</dd>
                <dt>Account</dt><dd>{{ $disbursement->financialAccount->code }} — {{ $disbursement->financialAccount->name }}</dd>
                <dt>Source</dt><dd>{{ str($disbursement->source_type->value)->headline() }}</dd>
                <dt>Supplier payment</dt><dd>{{ $disbursement->supplierPayment?->payment_number ?? '—' }}</dd>
                <dt>Expense</dt><dd>{{ $disbursement->expense?->expense_number ?? '—' }}</dd>
                <dt>Method</dt><dd>{{ $disbursement->paymentMethod->name }}</dd>
                <dt>Reference</dt><dd>{{ $disbursement->reference_number ?? '—' }}</dd>
                <dt>Release date</dt><dd>{{ $disbursement->release_date?->format('M d, Y') ?? '—' }}</dd>
                <dt>Clearing date</dt><dd>{{ $disbursement->clearing_date?->format('M d, Y') ?? '—' }}</dd>
            </dl>
        </section>
        <section class="rounded-2xl bg-white p-6 ring-1 ring-slate-200">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt>Gross</dt><dd class="text-right">PHP {{ number_format((float) $disbursement->gross_settlement, 2) }}</dd>
                <dt>Deductions/charges</dt><dd class="text-right">PHP {{ number_format((float) $disbursement->deductions_or_bank_charges, 2) }}</dd>
                <dt class="font-bold">Net cash out</dt><dd class="text-right font-bold">PHP {{ number_format((float) $disbursement->net_cash_out, 2) }}</dd>
            </dl>
        </section>
    </div>
    <div class="mt-5 flex flex-wrap gap-3">
        @can('update', $disbursement)<a href="{{ route('cash-disbursements.edit', $disbursement) }}" class="rounded-lg border px-4 py-2">Edit</a>@endcan
        @can('print', $disbursement)<a href="{{ route('cash-disbursements.print', $disbursement) }}" class="rounded-lg border px-4 py-2">Print</a>@endcan
        @if($disbursement->status === \App\Enums\CashDisbursementStatus::Draft)
            @can('post', $disbursement)<form method="POST" action="{{ route('cash-disbursements.transition', $disbursement) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="posted"><button class="rounded-lg bg-blue-700 px-4 py-2 text-white">Post disbursement</button></form>@endcan
        @endif
        @if($disbursement->status === \App\Enums\CashDisbursementStatus::Posted)
            @can('release', $disbursement)<form method="POST" action="{{ route('cash-disbursements.transition', $disbursement) }}" class="flex gap-2">@csrf @method('PATCH')<input type="hidden" name="status" value="released"><input type="date" name="release_date" required class="rounded-lg border-slate-300"><button class="rounded-lg border px-4 py-2">Mark released</button></form>@endcan
        @endif
        @if(in_array($disbursement->status, [\App\Enums\CashDisbursementStatus::Posted, \App\Enums\CashDisbursementStatus::Released], true))
            @can('clear', $disbursement)<form method="POST" action="{{ route('cash-disbursements.transition', $disbursement) }}" class="flex gap-2">@csrf @method('PATCH')<input type="hidden" name="status" value="cleared"><input type="date" name="clearing_date" required class="rounded-lg border-slate-300"><button class="rounded-lg border px-4 py-2">Mark cleared</button></form>@endcan
        @endif
        @if(in_array($disbursement->status, [\App\Enums\CashDisbursementStatus::Posted, \App\Enums\CashDisbursementStatus::Released, \App\Enums\CashDisbursementStatus::Cleared], true))
            @can('stop', $disbursement)<form method="POST" action="{{ route('cash-disbursements.transition', $disbursement) }}" class="flex gap-2">@csrf @method('PATCH')<input type="hidden" name="status" value="stopped"><input name="reason" required placeholder="Stop/reversal reason" class="rounded-lg border-slate-300"><button class="rounded-lg border border-red-300 px-4 py-2 text-red-700">Stop</button></form>@endcan
            @can('void', $disbursement)<form method="POST" action="{{ route('cash-disbursements.transition', $disbursement) }}" class="flex gap-2">@csrf @method('PATCH')<input type="hidden" name="status" value="voided"><input name="reason" required placeholder="Void reason" class="rounded-lg border-slate-300"><button class="rounded-lg border border-red-300 px-4 py-2 text-red-700">Void</button></form>@endcan
        @endif
    </div>
</x-app-layout>
