<x-app-layout title="Bank Statements">
    <x-page-header title="Bank Statements" description="Imported bank and e-wallet statement lines staged for later reconciliation." />
    <div class="mb-5 flex justify-end">
        @can('create', \App\Models\BankStatementImport::class)
            <a href="{{ route('bank-statements.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Import CSV</a>
        @endcan
    </div>
    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead><tr><th class="px-5 py-3 text-left">File</th><th>Account</th><th>Period</th><th>Lines</th><th>Imported by</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($imports as $import)
                    <tr class="border-t">
                        <td class="px-5 py-3"><a href="{{ route('bank-statements.show', $import) }}" class="font-semibold text-blue-700">{{ $import->source_filename }}</a></td>
                        <td class="text-center">{{ $import->financialAccount->code }}</td>
                        <td class="text-center">{{ $import->statement_start_date->format('M d, Y') }} – {{ $import->statement_end_date->format('M d, Y') }}</td>
                        <td class="text-center">{{ $import->lines_count }}</td>
                        <td class="text-center">{{ $import->importer->name }}</td>
                        <td class="text-center">{{ $import->rolled_back_at ? 'Rolled back' : ($import->finalized_at ? 'Finalized' : 'Staged') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center">No bank statements imported.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $imports->links() }}</div>
</x-app-layout>
