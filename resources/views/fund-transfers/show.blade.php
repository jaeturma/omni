<x-app-layout title="Fund Transfer">
    <x-page-header :title="$transfer->transfer_number ?? 'Draft #'.$transfer->id" :description="str($transfer->status->value)->headline().' transfer from '.$transfer->sourceFinancialAccount->name.' to '.$transfer->destinationFinancialAccount->name" />
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-2xl bg-white p-6 ring-1 ring-slate-200 lg:col-span-2">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt>Source date</dt><dd>{{ $transfer->transfer_date->format('M d, Y') }}</dd>
                <dt>Destination date</dt><dd>{{ $transfer->destination_date->format('M d, Y') }}</dd>
                <dt>Fiscal period</dt><dd>{{ $transfer->fiscalPeriod->name }}</dd>
                <dt>Source account</dt><dd>{{ $transfer->sourceFinancialAccount->code }} — {{ $transfer->sourceFinancialAccount->name }}</dd>
                <dt>Destination account</dt><dd>{{ $transfer->destinationFinancialAccount->code }} — {{ $transfer->destinationFinancialAccount->name }}</dd>
                <dt>Reference</dt><dd>{{ $transfer->reference_number ?? '—' }}</dd>
                <dt>Source transaction</dt><dd>{{ $transfer->sourceTransaction?->type->value ?? 'Not created' }}</dd>
                <dt>Destination transaction</dt><dd>{{ $transfer->destinationTransaction?->status->value ?? 'Not created' }}</dd>
            </dl>
        </section>
        <section class="rounded-2xl bg-white p-6 ring-1 ring-slate-200">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt>Amount</dt><dd class="text-right">PHP {{ number_format((float) $transfer->amount, 2) }}</dd>
                <dt>Transfer fee</dt><dd class="text-right">PHP {{ number_format((float) $transfer->transfer_fee, 2) }}</dd>
                <dt class="font-bold">Source cash out</dt><dd class="text-right font-bold">PHP {{ number_format((float) $transfer->sourceCashOut(), 2) }}</dd>
            </dl>
        </section>
    </div>
    <div class="mt-5 flex flex-wrap gap-3">
        @if($transfer->status === \App\Enums\FundTransferStatus::Draft)
            @can('post', $transfer)<form method="POST" action="{{ route('fund-transfers.transition', $transfer) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="posted"><button class="rounded-lg bg-blue-700 px-4 py-2 text-white">Post transfer</button></form>@endcan
        @endif
        @if($transfer->status === \App\Enums\FundTransferStatus::InTransit)
            @can('complete', $transfer)<form method="POST" action="{{ route('fund-transfers.transition', $transfer) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="completed"><button class="rounded-lg border px-4 py-2">Complete transfer</button></form>@endcan
            @can('fail', $transfer)<form method="POST" action="{{ route('fund-transfers.transition', $transfer) }}" class="flex gap-2">@csrf @method('PATCH')<input type="hidden" name="status" value="failed"><input name="reason" required placeholder="Failure reason" class="rounded-lg border-slate-300"><button class="rounded-lg border border-red-300 px-4 py-2 text-red-700">Mark failed</button></form>@endcan
        @endif
        @if(in_array($transfer->status, [\App\Enums\FundTransferStatus::InTransit, \App\Enums\FundTransferStatus::Completed], true))
            @can('void', $transfer)<form method="POST" action="{{ route('fund-transfers.transition', $transfer) }}" class="flex gap-2">@csrf @method('PATCH')<input type="hidden" name="status" value="voided"><input name="reason" required placeholder="Void reason" class="rounded-lg border-slate-300"><button class="rounded-lg border border-red-300 px-4 py-2 text-red-700">Void transfer</button></form>@endcan
        @endif
    </div>
</x-app-layout>
