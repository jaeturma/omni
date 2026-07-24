<!DOCTYPE html>
<html>
    <head><meta charset="utf-8"><title>{{ $disbursement->disbursement_number }}</title>@vite('resources/css/app.css')</head>
    <body class="bg-white p-8 text-slate-900">
        <main class="mx-auto max-w-3xl">
            <h1 class="text-2xl font-bold">Cash Disbursement {{ $disbursement->disbursement_number }}</h1>
            <p class="mt-2">{{ $disbursement->disbursement_date->format('F d, Y') }} · {{ $disbursement->financialAccount->name }}</p>
            <dl class="mt-8 grid grid-cols-2 gap-3">
                <dt>Payee</dt><dd>{{ $disbursement->payee }}</dd>
                <dt>Source</dt><dd>{{ str($disbursement->source_type->value)->headline() }}</dd>
                <dt>Payment method</dt><dd>{{ $disbursement->paymentMethod->name }}</dd>
                <dt>Reference</dt><dd>{{ $disbursement->reference_number ?? '—' }}</dd>
                <dt>Gross settlement</dt><dd>PHP {{ number_format((float) $disbursement->gross_settlement, 2) }}</dd>
                <dt>Deductions or charges</dt><dd>PHP {{ number_format((float) $disbursement->deductions_or_bank_charges, 2) }}</dd>
                <dt class="font-bold">Net cash out</dt><dd class="font-bold">PHP {{ number_format((float) $disbursement->net_cash_out, 2) }}</dd>
            </dl>
        </main>
    </body>
</html>
