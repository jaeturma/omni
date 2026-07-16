<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Sign in | {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <main class="flex min-h-screen items-center justify-center px-4 py-12">
            <section class="w-full max-w-md rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-semibold text-blue-700">Omni Mini-ERP</p>
                    <h1 class="text-2xl font-bold tracking-tight">Sign in</h1>
                    <p class="text-sm text-slate-600">Use your account credentials to continue.</p>
                </div>

                <form method="POST" action="{{ route('login.store') }}" class="mt-8 flex flex-col gap-5">
                    @csrf

                    <div class="flex flex-col gap-2">
                        <label for="email" class="text-sm font-medium">Email address</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                        >
                        @error('email')
                            <p class="text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password" class="text-sm font-medium">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                        >
                        @error('password')
                            <p class="text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input name="remember" type="checkbox" value="1" class="size-4 rounded border-slate-300 text-blue-700">
                        Remember me
                    </label>

                    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2">
                        Sign in
                    </button>
                    <a href="{{ route('password.request') }}" class="text-center text-sm font-semibold text-blue-700 hover:text-blue-800">Forgot your password?</a>
                </form>
            </section>
        </main>
    </body>
</html>
