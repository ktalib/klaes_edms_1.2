@extends('laas.layouts.app')

@section('title', 'Sign in — LAAS Portal')

@section('body')
<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">

        <div class="mb-8 text-center">
            <a href="{{ route('laas.landing') }}">
                <img src="{{ asset('assets/logo/laas-light-logo.jpeg') }}" alt="LAAS Portal" class="mx-auto mb-4 h-20 w-auto object-contain dark:hidden">
                <img src="{{ asset('assets/logo/laas-dark-logo.jpeg') }}" alt="LAAS Portal" class="mx-auto mb-4 hidden h-20 w-auto object-contain dark:block">
            </a>
            <p class="text-sm text-slate-600 dark:text-gray-400">Sign in to track your land allocation application</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-lg sm:p-8 dark:border-gray-700 dark:bg-gray-800">
            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-700 dark:bg-red-900/30">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('laas.login.submit') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="phone" class="block text-sm font-medium text-slate-700 dark:text-gray-300">Phone number or email</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone') }}" required autofocus
                           autocapitalize="none" autocomplete="username" spellcheck="false"
                           placeholder="08031234567"
                           class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-slate-900 placeholder-slate-400 focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-500 dark:focus:ring-green-800">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 dark:text-gray-300">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                           placeholder="••••••••"
                           class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-slate-900 placeholder-slate-400 focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-500 dark:focus:ring-green-800">
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="remember" value="1"
                           class="h-4 w-4 rounded border-slate-300 text-[#1a6b3c] focus:ring-green-500 dark:border-gray-600">
                    <span class="text-sm text-slate-600 dark:text-gray-400">Keep me signed in</span>
                </label>

                <button type="submit" class="laas-btn mt-6 w-full rounded-lg py-2.5 font-semibold text-white transition">
                    <i data-lucide="log-in" class="mr-2 inline h-4 w-4"></i> Sign in
                </button>
            </form>

            <div class="my-6 flex items-center gap-4">
                <div class="flex-1 border-t border-slate-200 dark:border-gray-600"></div>
                <span class="text-sm text-slate-500 dark:text-gray-400">or</span>
                <div class="flex-1 border-t border-slate-200 dark:border-gray-600"></div>
            </div>

            <p class="text-center text-sm text-slate-600 dark:text-gray-400">
                First time here?
                <a href="{{ route('laas.register') }}" class="font-semibold text-[#1a6b3c] hover:underline dark:text-green-400">Create an account</a>
            </p>
        </div>

        <div class="mt-6 flex items-center justify-center gap-4">
            <a href="{{ route('laas.landing') }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-gray-400 dark:hover:text-gray-100">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to LAAS Portal
            </a>
            <button onclick="laasToggleTheme()" title="Toggle dark mode"
                    class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:text-gray-400 dark:hover:bg-gray-700">
                <i data-lucide="sun" class="h-4 w-4 dark:hidden"></i>
                <i data-lucide="moon" class="hidden h-4 w-4 dark:block"></i>
            </button>
        </div>
    </div>
</div>
@endsection
