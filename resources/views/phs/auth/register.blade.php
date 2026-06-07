@extends('phs.layouts.app')

@section('title', 'Register Your Institution - PHS Portal')

@section('content')
<div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-blue-50 via-white to-green-50 px-4 py-12 sm:px-6 lg:px-8">
    <div class="w-full max-w-2xl">
        <!-- Logo & Header -->
        <div class="mb-8 text-center">
            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="mx-auto mb-4 h-14 w-auto object-contain">

            <h1 class="text-2xl font-bold text-gray-900">Register Your Institution</h1>
            <p class="mt-2 text-sm text-gray-600">Create a PHS Portal account. New institutions receive 100 signup tokens!</p>
        </div>

        <!-- Registration Form -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-lg sm:p-8">
            @if($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('phs.register.submit') }}" class="space-y-5">
                @csrf
                
                <!-- Institution Name -->
                <div class="sm:col-span-2">
                    <label for="institution_name" class="block text-sm font-medium text-gray-700">Institution Name</label>
                    <input 
                        id="institution_name"
                        type="text" 
                        name="institution_name" 
                        value="{{ old('institution_name') }}" 
                        required 
                        class="mt-2 block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        placeholder="e.g., Musa Trust Bank"
                    >
                </div>

                <!-- Institution Type -->
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="institution_type" class="block text-sm font-medium text-gray-700">Institution Type</label>
                        <select 
                            id="institution_type"
                            name="institution_type" 
                            required
                            class="mt-2 block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        >
                            <option value="">-- Select Type --</option>
                            <option value="bank">Bank</option>
                            <option value="law_firm">Law Firm</option>
                            <option value="corporate">Corporate</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Phone -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input 
                            id="phone"
                            type="tel" 
                            name="phone" 
                            value="{{ old('phone') }}" 
                            class="mt-2 block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            placeholder="+234 ..."
                        >
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Contact Email</label>
                    <input 
                        id="email"
                        type="email" 
                        name="email" 
                        value="{{ old('email') }}" 
                        required 
                        class="mt-2 block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        placeholder="contact@institution.com"
                    >
                </div>

                <!-- Password Fields -->
                <div class="grid gap-5 sm:grid-cols-2">
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
                        <p class="mt-1 text-xs text-gray-500">At least 8 characters</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                        <input 
                            id="password_confirmation"
                            type="password" 
                            name="password_confirmation" 
                            required 
                            class="mt-2 block w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                            placeholder="••••••••"
                        >
                    </div>
                </div>

                <!-- Terms Acceptance -->
                <label class="flex items-center gap-3">
                    <input 
                        type="checkbox" 
                        name="terms" 
                        required
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    >
                    <span class="text-sm text-gray-600">
                        I agree to the <a href="#" class="font-semibold text-blue-600 hover:text-blue-700">Terms of Service</a> and <a href="#" class="font-semibold text-blue-600 hover:text-blue-700">Privacy Policy</a>
                    </span>
                </label>

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="mt-6 w-full rounded-lg bg-blue-600 py-2 font-semibold text-white transition hover:bg-blue-700 sm:py-3"
                >
                    <i data-lucide="building-2" class="mr-2 inline h-4 w-4"></i>
                    Create Institution Account
                </button>
            </form>

            <!-- Divider -->
            <div class="my-6 flex items-center gap-4">
                <div class="flex-1 border-t border-gray-200"></div>
                <span class="text-sm text-gray-500">or</span>
                <div class="flex-1 border-t border-gray-200"></div>
            </div>

            <!-- Sign In Link -->
            <p class="text-center text-sm text-gray-600">
                Already have an account?
                <a href="{{ route('phs.login') }}" class="font-semibold text-blue-600 hover:text-blue-700">
                    Sign in here
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
