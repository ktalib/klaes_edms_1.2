@extends('laas.layouts.app')

@section('title', 'Create an account — LAAS Portal')

@section('body')
<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-lg">

        <div class="mb-8 text-center">
            <a href="{{ route('laas.landing') }}">
                <img src="{{ asset('assets/logo/laas-light-logo.jpeg') }}" alt="LAAS Portal" class="mx-auto mb-4 h-20 w-auto object-contain dark:hidden">
                <img src="{{ asset('assets/logo/laas-dark-logo.jpeg') }}" alt="LAAS Portal" class="mx-auto mb-4 hidden h-20 w-auto object-contain dark:block">
            </a>
            <p class="text-sm text-slate-600 dark:text-gray-400">Create your account to apply for land allocation</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-lg sm:p-8 dark:border-gray-700 dark:bg-gray-800">
            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-700 dark:bg-red-900/30">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @php
                $input = 'mt-2 block w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-slate-900 placeholder-slate-400 focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-500 dark:focus:ring-green-800';
                $label = 'block text-sm font-medium text-slate-700 dark:text-gray-300';
            @endphp

            <form method="POST" action="{{ route('laas.register.submit') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="name" class="{{ $label }}">Full name <span class="text-red-500">*</span></label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus class="{{ $input }}">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="phone" class="{{ $label }}">Phone number <span class="text-red-500">*</span></label>
                        <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required
                               placeholder="08031234567" class="{{ $input }}">
                        <p class="mt-1 text-xs text-slate-500 dark:text-gray-400">Every update is sent here by SMS.</p>
                    </div>
                    <div>
                        <label for="email" class="{{ $label }}">Email address <span class="text-red-500">*</span></label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required class="{{ $input }}">
                    </div>
                </div>

                <div>
                    <label for="nin" class="{{ $label }}">National Identification Number (NIN)</label>
                    <input id="nin" type="text" name="nin" value="{{ old('nin') }}" class="{{ $input }}">
                </div>

                <div>
                    <label for="address" class="{{ $label }}">Contact address</label>
                    <textarea id="address" name="address" rows="2" class="{{ $input }}">{{ old('address') }}</textarea>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="{{ $label }}">Password <span class="text-red-500">*</span></label>
                        <input id="password" type="password" name="password" required autocomplete="new-password" class="{{ $input }}">
                        <p class="mt-1 text-xs text-slate-500 dark:text-gray-400">At least 8 characters.</p>
                    </div>
                    <div>
                        <label for="password_confirmation" class="{{ $label }}">Confirm password <span class="text-red-500">*</span></label>
                        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $input }}">
                    </div>
                </div>

                <button type="submit" class="laas-btn mt-6 w-full rounded-lg py-2.5 font-semibold text-white transition">
                    <i data-lucide="user-plus" class="mr-2 inline h-4 w-4"></i> Create account
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-600 dark:text-gray-400">
                Already have an account?
                <a href="{{ route('laas.login') }}" class="font-semibold text-[#1a6b3c] hover:underline dark:text-green-400">Sign in</a>
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
