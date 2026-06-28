@extends('online_legal_search.layout')

@section('title', 'Sign In')

@section('body')
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-cyan-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 px-4 py-12">
    <div class="w-full max-w-md">
        <!-- Logo & Header -->
        <div class="mb-8 text-center">
            <img src="{{ asset('assets/logo/klaes-logo.png') }}" alt="KLAES" class="mx-auto mb-4 h-16 w-auto">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Online Legal Search</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Sign in to conduct legal searches</p>
        </div>

        <!-- Login Form Card -->
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-lg sm:p-8">
            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/30 p-4">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @if(session('status'))
                <div class="mb-4 rounded-lg border border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900/30 p-4">
                    <p class="text-sm text-green-700 dark:text-green-400">{{ session('status') }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('ols.login.submit') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                    <input
                        id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                        autocomplete="email"
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 dark:focus:ring-cyan-700"
                        placeholder="you@example.com">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                    <input
                        id="password" type="password" name="password" required
                        autocomplete="current-password"
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 dark:focus:ring-cyan-700"
                        placeholder="••••••••">
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                    </label>
                    <a href="{{ route('ols.password.email') }}" class="text-sm text-cyan-600 hover:text-cyan-800 dark:text-cyan-400">
                        Forgot password?
                    </a>
                </div>

                <button type="submit" class="w-full rounded-lg bg-cyan-600 px-4 py-2.5 font-semibold text-white shadow hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2">
                    Sign In
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
                Don't have an account?
                <a href="{{ route('ols.register') }}" class="font-medium text-cyan-600 hover:text-cyan-800 dark:text-cyan-400">Create one</a>
            </p>
        </div>

        <p class="mt-6 text-center text-xs text-gray-500 dark:text-gray-400">
            <a href="{{ route('ols.landing') }}" class="hover:text-cyan-600">&larr; Back to home</a>
        </p>
    </div>
</div>
@endsection
