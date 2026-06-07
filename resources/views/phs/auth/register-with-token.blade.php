@extends('phs.layouts.app')

@section('title', 'PHS Portal - Complete Registration')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-green-50">
    <!-- Navigation -->
    <nav class="sticky top-0 z-10 bg-white/80 backdrop-blur-md shadow-sm">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center">
                    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="h-11 w-auto object-contain">
                </div>
                <div class="hidden items-center space-x-4 md:flex">
                    <a href="{{ route('phs.login') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-transparent px-4 py-2 text-sm font-medium text-gray-700 transition-all hover:bg-gray-50">
                        Sign In
                    </a>
                    <a href="{{ route('phs.landing') }}" class="inline-flex items-center justify-center rounded-md border-0 bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-blue-700">
                        Back
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Form Content -->
<div class="container mx-auto py-8 px-4">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-8">
            <h1 class="text-3xl font-bold mb-2">Complete Your Registration</h1>
            <p class="text-gray-600 mb-6">Your onboarding request has been approved. Complete registration to activate your account.</p>

            <!-- Pre-filled Information -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                <h3 class="font-semibold text-blue-900 mb-4">Organization Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-blue-700">Organization Name</p>
                        <p class="font-medium">{{ $onboardingRequest->organization_name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-blue-700">Type</p>
                        <p class="font-medium capitalize">{{ str_replace('_', ' ', $onboardingRequest->organization_type) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-blue-700">Contact Name</p>
                        <p class="font-medium">{{ $onboardingRequest->contact_name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-blue-700">Email</p>
                        <p class="font-medium">{{ $onboardingRequest->contact_email }}</p>
                    </div>
                </div>
            </div>

            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <strong>Please correct the following errors:</strong>
                    <ul class="list-disc list-inside mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($onboardingRequest->activation_token_expires_at)
                <div class="mb-6 p-4 bg-yellow-100 border border-yellow-400 text-yellow-800 rounded">
                    This registration link expires on <strong>{{ $onboardingRequest->activation_token_expires_at->format('F j, Y \a\t g:i A') }}</strong>
                </div>
            @endif

            <form method="POST" action="{{ route('phs.register.token.submit', ['token' => $token]) }}" class="space-y-6">
                @csrf

                <fieldset>
                    <legend class="text-lg font-semibold mb-4">Create Your Password</legend>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password *</label>
                        <input type="password" id="password" name="password" required
                            class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Minimum 6 characters">
                        <p class="text-sm text-gray-500 mt-1">Use a strong password with a mix of letters, numbers, and symbols.</p>
                    </div>

                    <div class="mt-4">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password *</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required
                            class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Re-enter your password">
                    </div>
                </fieldset>

                <div class="flex justify-between items-center pt-6 border-t">
                    <a href="{{ route('phs.landing') }}" class="text-blue-600 hover:text-blue-800">Back to Home</a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium">
                        Complete Registration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="bg-gray-900 py-12 text-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4 sm:gap-10">
            <div>
                <div class="flex items-center">
                    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="h-11 w-auto object-contain bg-white rounded-md p-1">
                </div>
                <p class="mt-4 text-sm leading-relaxed text-gray-400">
                    Official government platform for legal search services and land record verification in Kano State.
                </p>
            </div>
            <div>
                <h3 class="mb-4 text-base font-semibold uppercase tracking-wider text-gray-300">Services</h3>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="#" class="hover:text-white transition">Legal Search</a></li>
                    <li><a href="#" class="hover:text-white transition">Property Records</a></li>
                    <li><a href="#" class="hover:text-white transition">Certification</a></li>
                </ul>
            </div>
            <div>
                <h3 class="mb-4 text-base font-semibold uppercase tracking-wider text-gray-300">Company</h3>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="#" class="hover:text-white transition">About</a></li>
                    <li><a href="#" class="hover:text-white transition">Contact</a></li>
                    <li><a href="#" class="hover:text-white transition">Support</a></li>
                </ul>
            </div>
            <div>
                <h3 class="mb-4 text-base font-semibold uppercase tracking-wider text-gray-300">Legal</h3>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="#" class="hover:text-white transition">Privacy</a></li>
                    <li><a href="#" class="hover:text-white transition">Terms</a></li>
                    <li><a href="#" class="hover:text-white transition">Cookies</a></li>
                </ul>
            </div>
        </div>
        <div class="mt-8 border-t border-gray-800 pt-8 text-center text-sm text-gray-400">
            <p>&copy; {{ date('Y') }} Kano State Ministry of Land and Physical Planning. All rights reserved.</p>
        </div>
    </div>
</footer>
@endsection
