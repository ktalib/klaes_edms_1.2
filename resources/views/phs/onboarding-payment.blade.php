@extends('phs.layouts.app')

@section('title', 'PHS Portal - Payment Verification')

@section('content')
<div class="min-h-screen bg-slate-50">
    <div class="relative overflow-hidden py-10">
        <div class="absolute -top-16 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-sky-300/30 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-80 w-96 rounded-full bg-emerald-300/20 blur-3xl"></div>

        <div class="relative w-full px-0">
            <nav class="sticky top-0 z-10 mb-8 rounded-3xl border border-gray-200 bg-white/80 px-5 py-4 shadow-sm backdrop-blur-md">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-3">
                        <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="h-11 w-auto object-contain">
                        <span class="text-sm font-semibold tracking-wide text-gray-700">PHS Portal</span>
                    </div>
                    <div class="hidden items-center space-x-4 md:flex">
                        <a href="{{ route('phs.login') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-transparent px-4 py-2 text-sm font-medium text-gray-700 transition-all hover:bg-gray-50">Sign In</a>
                        <a href="{{ route('phs.request.form') }}" class="inline-flex items-center justify-center rounded-md border-0 bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-blue-700">Request Access</a>
                    </div>
                    <button id="mobile-menu-btn" class="rounded-lg p-2 hover:bg-gray-100 md:hidden">
                        <i data-lucide="menu" class="h-6 w-6 text-gray-700"></i>
                    </button>
                </div>
                <div id="mobile-menu" class="invisible max-h-0 overflow-hidden border-t border-gray-200 opacity-0 transition-all duration-300 ease-in-out md:hidden">
                    <div class="space-y-3 border-t border-gray-200 py-4">
                        <a href="{{ route('phs.login') }}" class="block rounded-md border border-gray-300 bg-transparent px-4 py-2 text-center text-sm font-medium text-gray-700 transition-all hover:bg-gray-50">Sign In</a>
                        <a href="{{ route('phs.request.form') }}" class="block rounded-md border-0 bg-blue-600 px-4 py-2 text-center text-sm font-medium text-white transition-all hover:bg-blue-700">Request Access</a>
                    </div>
                </div>
            </nav>

            <div class="w-full rounded-[2rem] bg-white/95 p-8 shadow-2xl ring-1 ring-slate-200 backdrop-blur-md sm:p-12">
                <div class="grid grid-cols-12 gap-8">
                    <aside class="hidden lg:block col-span-12 lg:col-span-4 bg-gradient-to-b from-emerald-50 via-white/60 to-white p-8">
                        <div class="h-full flex flex-col justify-center">
                            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="h-10 w-auto mb-6">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Payment Verification</p>
                            <h2 class="mt-4 text-2xl font-bold text-slate-900">Complete Your Payment</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600">Transfer the amount shown below and provide your bank transfer reference to complete your application.</p>
                        </div>
                    </aside>
                    <main class="col-span-12 lg:col-span-8 p-8 sm:p-12">
                        <div class="flex items-start justify-end mb-6">
                            <div class="rounded-3xl bg-sky-100 px-4 py-3 text-sm font-semibold text-sky-700 whitespace-nowrap">Step 2 of 2</div>
                        </div>

                <div class="grid gap-6 mb-8 lg:grid-cols-2">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                        <h3 class="text-lg font-semibold text-slate-900 mb-4">Application Summary</h3>
                        <dl class="grid gap-4 text-sm">
                            <div>
                                <dt class="font-semibold text-slate-600">Organization</dt>
                                <dd class="text-slate-900">{{ $validated['organization_name'] }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-600">Contact Person</dt>
                                <dd class="text-slate-900">{{ $validated['contact_name'] }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-600">Email</dt>
                                <dd class="text-slate-900">{{ $validated['contact_email'] }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-600">Token Package</dt>
                                <dd class="text-slate-900 font-semibold">{{ $validated['initial_token_package'] }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6">
                        <h3 class="text-lg font-semibold text-emerald-900 mb-4">Payment Due</h3>
                        <div class="text-center py-4 border-t border-b border-emerald-200 my-4">
                            <p class="text-sm text-emerald-700 font-medium">Amount to Transfer</p>
                            <p class="text-4xl font-bold text-emerald-900 mt-2">₦{{ number_format($amount) }}</p>
                        </div>
                        <p class="text-xs text-emerald-700 mt-4">Once we receive and verify your bank transfer, we will approve your request and send you an activation link via email.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('phs.request.submit') }}" class="space-y-6">
                    @csrf

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                        <h3 class="text-lg font-semibold text-slate-900 mb-4">Bank Account Details</h3>
                        <p class="text-sm text-slate-600 mb-5">Transfer ₦{{ number_format($amount) }} to the account below:</p>
                        
                        <div class="rounded-3xl bg-white border border-slate-200 p-5 space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="font-semibold text-slate-700">Bank Name:</span>
                                <span class="text-slate-900">First Bank</span>
                            </div>
                            <div class="flex justify-between border-t border-slate-100 pt-3">
                                <span class="font-semibold text-slate-700">Account Name:</span>
                                <span class="text-slate-900">KLAES Enterprise</span>
                            </div>
                            <div class="flex justify-between border-t border-slate-100 pt-3">
                                <span class="font-semibold text-slate-700">Account Number:</span>
                                <span class="text-slate-900">1234567890</span>
                            </div>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="rounded-3xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                            <p class="font-semibold">Please fix the following:</p>
                            <ul class="mt-3 list-disc space-y-1 pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                        <h3 class="text-lg font-semibold text-slate-900 mb-4">Transfer Confirmation</h3>
                        
                        <div class="grid gap-6 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Payment Amount (NGN) *</span>
                                <input type="number" name="payment_amount" value="{{ $amount }}" readonly
                                    class="mt-2 block w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none cursor-not-allowed" />
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Bank Transfer Reference *</span>
                                <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" required
                                    placeholder="e.g., TRF-20260607-001"
                                    class="mt-2 block w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200" />
                            </label>
                        </div>

                        <p class="text-xs text-slate-600 mt-4">You will find the transfer reference in your bank's transaction receipt or statement.</p>
                    </div>

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pt-4">
                        <a href="{{ route('phs.request.form') }}" class="inline-flex justify-center rounded-full border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">← Back</a>
                        <button type="submit" class="inline-flex justify-center rounded-full bg-emerald-600 px-8 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-600/10 transition hover:bg-emerald-700">Complete Application</button>
                    </div>
                </form>
            </main>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
