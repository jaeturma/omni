@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} | {{ $applicationDisplayName }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('dashboard') }}" class="shrink-0" aria-label="Omni Mini-ERP dashboard">
                    <span class="block text-sm font-semibold text-blue-700">{{ $businessDisplayName ?: config('app.name') }}</span>
                    <span class="block text-xs text-slate-500">Business workspace</span>
                </a>

                <nav class="hidden items-center gap-1 lg:flex" aria-label="Main navigation">
                    <a href="{{ route('dashboard') }}" class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800" aria-current="page">Dashboard</a>
                    @can('fiscal-years.view')<a href="{{ route('fiscal-years.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Fiscal Years</a>@endcan
                    @can('document-sequences.view')<a href="{{ route('document-sequences.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Sequences</a>@endcan
                    @can('users.view')<a href="{{ route('users.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Users</a>@endcan
                    @can('system-settings.view')<a href="{{ route('system-settings.edit') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Settings</a>@endcan
                    @can('customers.view')<a href="{{ route('customers.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Customers</a>@endcan
                    @can('suppliers.view')<a href="{{ route('suppliers.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Suppliers</a>@endcan
                    @can('units-of-measure.view')<a href="{{ route('units-of-measure.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Units</a>@endcan
                    @can('categories.view')<a href="{{ route('categories.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Categories</a>@endcan
                    @can('products-services.view')<a href="{{ route('products-services.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Catalog</a>@endcan
                    @foreach (['Sales', 'Purchases', 'Expenses', 'Inventory', 'Accounting', 'Tax Reports'] as $navigationLabel)
                        <span class="cursor-not-allowed rounded-lg px-3 py-2 text-sm text-slate-400" aria-disabled="true">{{ $navigationLabel }}</span>
                    @endforeach
                </nav>

                <div class="hidden items-center gap-4 sm:flex">
                    <div class="text-right">
                        <p class="text-sm font-medium">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                            Sign out
                        </button>
                    </form>
                </div>

                <details class="relative sm:hidden">
                    <summary class="cursor-pointer list-none rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-blue-300">
                        Menu
                    </summary>
                    <div class="absolute right-0 z-10 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
                        <nav class="flex flex-col gap-1" aria-label="Mobile navigation">
                            <a href="{{ route('dashboard') }}" class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800" aria-current="page">Dashboard</a>
                            @can('fiscal-years.view')<a href="{{ route('fiscal-years.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Fiscal Years</a>@endcan
                            @can('document-sequences.view')<a href="{{ route('document-sequences.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Sequences</a>@endcan
                            @can('users.view')<a href="{{ route('users.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Users</a>@endcan
                            @can('system-settings.view')<a href="{{ route('system-settings.edit') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Settings</a>@endcan
                            @can('customers.view')<a href="{{ route('customers.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Customers</a>@endcan
                            @can('suppliers.view')<a href="{{ route('suppliers.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Suppliers</a>@endcan
                            @can('units-of-measure.view')<a href="{{ route('units-of-measure.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Units</a>@endcan
                            @can('categories.view')<a href="{{ route('categories.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Categories</a>@endcan
                            @can('products-services.view')<a href="{{ route('products-services.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Catalog</a>@endcan
                            @foreach (['Sales', 'Purchases', 'Expenses', 'Inventory', 'Accounting', 'Tax Reports'] as $navigationLabel)
                                <span class="cursor-not-allowed rounded-lg px-3 py-2 text-sm text-slate-400" aria-disabled="true">{{ $navigationLabel }}</span>
                            @endforeach
                        </nav>
                        <div class="mt-3 border-t border-slate-200 pt-3">
                            <p class="px-3 text-sm font-medium">{{ auth()->user()->name }}</p>
                            <p class="px-3 text-xs text-slate-500">{{ auth()->user()->email }}</p>
                            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                                @csrf
                                <button type="submit" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-left text-sm font-semibold hover:bg-slate-50">
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </details>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <x-flash-messages />
            {{ $slot }}
        </main>
    </body>
</html>
