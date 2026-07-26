<x-app-layout title="Chart of Accounts">
    <x-page-header title="Chart of Accounts" description="Manage the controlled accounting hierarchy and postable accounts." />
    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('accounts.index') }}" class="flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1 text-sm font-medium">Search<input name="search" value="{{ request('search') }}" placeholder="Code or name" class="rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="flex flex-col gap-1 text-sm font-medium">Class<select name="account_class" class="rounded-lg border border-slate-300 px-3 py-2"><option value="">All</option>@foreach ($accountClasses as $class)<option value="{{ $class->value }}" @selected(request('account_class') === $class->value)>{{ str($class->value)->headline() }}</option>@endforeach</select></label>
            <label class="flex flex-col gap-1 text-sm font-medium">Status<select name="status" class="rounded-lg border border-slate-300 px-3 py-2"><option value="">All</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select></label>
            <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Filter</button>
        </form>
        @can('create', \App\Models\Account::class)<a href="{{ route('accounts.create') }}" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Create account</a>@endcan
    </div>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-6 py-3">Code</th><th class="px-6 py-3">Account</th><th class="px-6 py-3">Class / Type</th><th class="px-6 py-3">Parent</th><th class="px-6 py-3">Rules</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($accounts as $account)
                    <tr>
                        <td class="px-6 py-4 font-mono font-medium">{{ $account->code }}</td>
                        <td class="px-6 py-4"><span class="font-medium">{{ $account->name }}</span>@if ($account->is_system)<span class="ml-2 rounded bg-slate-100 px-2 py-1 text-xs">System</span>@endif</td>
                        <td class="px-6 py-4">{{ str($account->account_class->value)->headline() }}<div class="text-xs text-slate-500">{{ str($account->account_type->value)->headline() }}</div></td>
                        <td class="px-6 py-4">{{ $account->parent ? $account->parent->code.' — '.$account->parent->name : 'Top level' }}</td>
                        <td class="px-6 py-4">{{ $account->is_header ? 'Header' : ($account->is_postable ? 'Postable' : 'Non-postable') }}@if ($account->is_control_account)<div class="text-xs font-medium text-blue-700">Control: {{ str($account->control_account_type)->headline() }}</div>@endif</td>
                        <td class="px-6 py-4">{{ $account->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-6 py-4"><div class="flex gap-3">@can('update', $account)<a href="{{ route('accounts.edit', $account) }}" class="font-semibold text-blue-700">Edit</a>@endcan @can($account->is_active ? 'deactivate' : 'activate', $account)<form method="POST" action="{{ route('accounts.status', $account) }}">@csrf @method('PATCH')<button class="font-semibold text-slate-700">{{ $account->is_active ? 'Deactivate' : 'Activate' }}</button></form>@endcan</div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">No accounts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $accounts->links() }}</div>
</x-app-layout>
