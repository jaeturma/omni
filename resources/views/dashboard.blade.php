<x-app-layout title="Dashboard">
    <x-page-header
        title="Dashboard"
        description="Your Omni Mini-ERP workspace is ready. Business modules will appear here as they are implemented."
    />

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="text-lg font-semibold">Welcome, {{ auth()->user()->name }}</h2>
        <p class="mt-2 text-sm text-slate-600">Use the navigation to access available areas.</p>
    </section>
</x-app-layout>
