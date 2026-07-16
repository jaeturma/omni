<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dashboard | {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div>
                    <p class="text-sm font-semibold text-blue-700">Omni Mini-ERP</p>
                    <h1 class="text-xl font-bold">Dashboard</h1>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                        Sign out
                    </button>
                </form>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-lg font-semibold">Welcome, {{ auth()->user()->name }}</h2>
                <p class="mt-2 text-sm text-slate-600">The application baseline is ready.</p>
            </section>
        </main>
    </body>
</html>
