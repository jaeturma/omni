@props(['title'])

@php
    $navigationGroups = [
        'Workspace' => [
            ['Dashboard', 'dashboard', null],
        ],
        'Setup' => [
            ['Fiscal Years', 'fiscal-years.index', 'fiscal-years.view'],
            ['Sequences', 'document-sequences.index', 'document-sequences.view'],
            ['Users', 'users.index', 'users.view'],
            ['Settings', 'system-settings.edit', 'system-settings.view'],
        ],
        'Master Data' => [
            ['Customers', 'customers.index', 'customers.view'],
            ['Suppliers', 'suppliers.index', 'suppliers.view'],
            ['Catalog', 'products-services.index', 'products-services.view'],
            ['Categories', 'categories.index', 'categories.view'],
            ['Brands', 'brands.index', 'brands.view'],
            ['Units', 'units-of-measure.index', 'units-of-measure.view'],
            ['Warehouses', 'warehouses.index', 'warehouses.view'],
            ['Payment Methods', 'payment-methods.index', 'payment-methods.view'],
            ['Banks', 'banks.index', 'banks.view'],
        ],
        'Sales' => [
            ['Quotations', 'quotations.index', 'quotations.view'],
            ['Sales Orders', 'sales-orders.index', 'sales-orders.view'],
            ['Deliveries', 'deliveries.index', 'deliveries.view'],
            ['Sales Invoices', 'sales-invoices.index', 'sales-invoices.view'],
            ['Customer Payments', 'customer-payments.index', 'customer-payments.view'],
            ['Receivables', 'receivables.index', 'receivables.view'],
            ['Government Deductions', 'government-deductions.index', 'government-deductions.view'],
        ],
        'Purchasing' => [
            ['Purchase Requests', 'purchase-requests.index', 'purchase-requests.view'],
            ['Purchase Orders', 'purchase-orders.index', 'purchase-orders.view'],
            ['Receiving', 'receiving-records.index', 'receiving-records.view'],
            ['Supplier Invoices', 'supplier-invoices.index', 'supplier-invoices.view'],
            ['Supplier Payments', 'supplier-payments.index', 'supplier-payments.view'],
            ['Expenses', 'expenses.index', 'expenses.view'],
            ['Payables', 'payables.index', 'payables.view'],
        ],
        'Cash & Banking' => [
            ['Financial Accounts', 'financial-accounts.index', 'financial-accounts.view'],
            ['Cash Receipts', 'cash-receipts.index', 'cash-receipts.view'],
            ['Cash Disbursements', 'cash-disbursements.index', 'cash-disbursements.view'],
            ['Fund Transfers', 'fund-transfers.index', 'fund-transfers.view'],
            ['Petty Cash', 'petty-cash.index', 'petty-cash.view'],
            ['Bank Statements', 'bank-statements.index', 'bank-statements.view'],
            ['Reconciliation', 'bank-reconciliations.index', 'bank-reconciliation.view'],
            ['Cash Reports', 'cash-reports.index', 'cash-reports.view'],
        ],
        'Inventory' => [
            ['Opening Balances', 'inventory-opening-balances.index', 'inventory-opening-balances.view'],
            ['Adjustments', 'inventory-adjustments.index', 'inventory-adjustments.view'],
            ['Transfers', 'inventory-transfers.index', 'inventory-transfers.view'],
            ['Physical Counts', 'physical-counts.index', 'physical-counts.view'],
            ['Inventory Reports', 'inventory-reports.index', 'inventory-reports.view'],
        ],
        'Accounting' => [
            ['Chart of Accounts', 'accounts.index', 'chart-of-accounts.view'],
            ['Journal Entries', 'journal-entries.index', 'journals.view'],
            ['Posting Rules', 'posting-rules.index', 'posting-rules.view'],
            ['Source Postings', 'source-postings.index', 'source-posting.view'],
        ],
    ];

    $visibleNavigationGroups = collect($navigationGroups)
        ->map(fn (array $links) => collect($links)
            ->filter(fn (array $link) => $link[2] === null || auth()->user()->can($link[2]))
            ->values()
            ->all())
        ->filter()
        ->all();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} | {{ $applicationDisplayName }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white lg:hidden">
            <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="{{ route('dashboard') }}" aria-label="Omni Mini-ERP dashboard">
                    <span class="block text-sm font-semibold text-blue-700">{{ $businessDisplayName ?: config('app.name') }}</span>
                    <span class="block text-xs text-slate-500">Business workspace</span>
                </a>
                <details class="relative">
                    <summary class="cursor-pointer list-none rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-blue-300">Menu</summary>
                    <div class="absolute right-0 mt-2 max-h-[calc(100vh-5rem)] w-72 overflow-y-auto rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                        <x-navigation-menu :groups="$visibleNavigationGroups" />
                        <div class="mt-4 border-t border-slate-200 pt-4">
                            <p class="px-3 text-sm font-medium">{{ auth()->user()->name }}</p>
                            <p class="px-3 text-xs text-slate-500">{{ auth()->user()->email }}</p>
                            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                                @csrf
                                <button type="submit" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-left text-sm font-semibold hover:bg-slate-50">Sign out</button>
                            </form>
                        </div>
                    </div>
                </details>
            </div>
        </header>

        <div class="lg:grid lg:min-h-screen lg:grid-cols-[17rem_minmax(0,1fr)]">
            <aside class="hidden border-r border-slate-200 bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col">
                <div class="border-b border-slate-200 px-5 py-5">
                    <a href="{{ route('dashboard') }}" aria-label="Omni Mini-ERP dashboard">
                        <span class="block font-semibold text-blue-700">{{ $businessDisplayName ?: config('app.name') }}</span>
                        <span class="block text-xs text-slate-500">Business workspace</span>
                    </a>
                </div>
                <nav class="min-h-0 flex-1 overflow-y-auto p-3" aria-label="Main navigation">
                    <x-navigation-menu :groups="$visibleNavigationGroups" />
                </nav>
                <div class="border-t border-slate-200 p-4">
                    <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-left text-sm font-semibold hover:bg-slate-50">Sign out</button>
                    </form>
                </div>
            </aside>

            <main class="min-w-0 px-4 py-8 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <x-flash-messages />
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
