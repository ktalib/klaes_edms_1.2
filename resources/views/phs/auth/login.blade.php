@extends('phs.layouts.app')

@section('title', 'Sign In - PHS Portal')

@section('content')
<div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-blue-50 via-white to-green-50 px-4 py-12 sm:px-6 lg:px-8">
    <div class="w-full max-w-md">
        <!-- Logo & Header -->
        <div class="mb-8 text-center">
            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="mx-auto mb-4 h-14 w-auto object-contain">

            <h1 class="text-2xl font-bold text-gray-900">PHS Portal</h1>
            <p class="mt-2 text-sm text-gray-600">Sign in to your institution account</p>
        </div>

        <!-- Login Form -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg sm:p-8">
            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('phs.login.submit') }}" class="space-y-4">
                @csrf
                
                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input 
                        id="email"
                        type="email" 
                        name="email" 
                        value="{{ old('email') }}" 
                        required 
                        class="mt-2 block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        placeholder="your@email.com"
                    >
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input 
                        id="password"
                        type="password" 
                        name="password" 
                        required 
                        class="mt-2 block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        placeholder="••••••••"
                    >
                </div>

                <!-- Remember Me -->
                <label class="flex items-center gap-2">
                    <input 
                        type="checkbox" 
                        name="remember" 
                        value="1" 
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    >
                    <span class="text-sm text-gray-600">Remember me</span>
                </label>

                <!-- Sign In Button -->
                <button 
                    type="submit" 
                    class="mt-6 w-full rounded-lg bg-blue-600 py-2 font-semibold text-white transition hover:bg-blue-700 sm:py-3"
                >
                    <i data-lucide="log-in" class="mr-2 inline h-4 w-4"></i>
                    Sign In
                </button>
            </form>

            <!-- Divider -->
            <div class="my-6 flex items-center gap-4">
                <div class="flex-1 border-t border-gray-200"></div>
                <span class="text-sm text-gray-500">or</span>
                <div class="flex-1 border-t border-gray-200"></div>
            </div>

            <!-- Register Link -->
            <p class="text-center text-sm text-gray-600">
                Don't have an account?
                <a href="{{ route('phs.register') }}" class="font-semibold text-blue-600 hover:text-blue-700">
                    Register your institution
                </a>
            </p>
        </div>

        <!-- Back Link -->
        <div class="mt-6 text-center">
            <a href="{{ route('phs.landing') }}" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                Back to PHS Portal
            </a>
        </div>
    </div>
</div>
@endsection
