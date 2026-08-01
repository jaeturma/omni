<x-app-layout title="Dashboard">
    <x-page-header title="Dashboard" description="Your Omni Mini-ERP workspace is ready." />
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="text-lg font-semibold">Welcome, {{ auth()->user()->name }}</h2>
        <p class="mt-2 text-sm text-slate-600">Use the navigation to access available areas.</p>
        @can('financial-dashboard.view')
            <a class="mt-4 inline-flex rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white" href="{{ route('financial-dashboard') }}">Open financial dashboard</a>
        @endcan
    </section>
</x-app-layout>
