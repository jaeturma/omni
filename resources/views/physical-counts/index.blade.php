<x-app-layout title="Physical Counts">
    <x-page-header title="Physical Counts" description="Freeze stock snapshots, record blind counts, and reconcile approved variances." />
    <div class="mb-5 flex justify-end">
        @can('create', \App\Models\PhysicalCount::class)
            <a href="{{ route('physical-counts.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">New count session</a>
        @endcan
    </div>
    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead><tr><th class="px-5 py-3 text-left">Session</th><th>Date</th><th>Warehouse</th><th>Cutoff</th><th>Mode</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($counts as $count)
                    <tr class="border-t">
                        <td class="px-5 py-3"><a href="{{ route('physical-counts.show', $count) }}" class="font-semibold text-blue-700">{{ $count->count_number ?? 'Draft #'.$count->id }}</a></td>
                        <td class="text-center">{{ $count->count_date->format('M d, Y') }}</td>
                        <td class="text-center">{{ $count->warehouse->code }}</td>
                        <td class="text-center">{{ $count->cutoff_at->format('M d, Y H:i') }}</td>
                        <td class="text-center">{{ $count->blind_count ? 'Blind' : 'Visible' }}</td>
                        <td class="text-center">{{ str($count->status->value)->headline() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-slate-500">No physical counts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $counts->links() }}</div>
</x-app-layout>
