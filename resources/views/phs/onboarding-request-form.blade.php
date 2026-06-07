@extends('phs.layouts.app')

@section('title', 'PHS Portal - Access Request')

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
                        <a href="{{ route('phs.landing') }}" class="inline-flex items-center justify-center rounded-md border-0 bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-blue-700">Request Access</a>
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

            <div class="w-full rounded-[2rem] bg-white/95 p-0 shadow-2xl ring-1 ring-slate-200 backdrop-blur-md sm:p-0 overflow-hidden">
                <div class="grid grid-cols-12 gap-8">
                    <aside class="hidden lg:block col-span-12 lg:col-span-4 bg-gradient-to-b from-sky-50 via-white/60 to-white p-8">
                        <div class="h-full flex flex-col justify-center">
                            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="h-10 w-auto mb-6">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600">Kano State Ministry of Land and Physical Planning</p>
                            <h2 class="mt-4 text-2xl font-bold text-slate-900">Property History Search (PHS)</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600">Request institutional access to search official property records. Choose your token package and complete payment verification on the next step.</p>
                        </div>
                    </aside>
                    <main class="col-span-12 lg:col-span-8 p-8 sm:p-12">
                        <div class="flex items-start justify-end mb-6">
                            <div class="rounded-3xl bg-sky-100 px-4 py-3 text-sm font-semibold text-sky-700 whitespace-nowrap">Step 1 of 2</div>
                        </div>

                @if ($errors->any())
                    <div class="mb-6 rounded-3xl bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-200">
                        <p class="font-semibold">Please fix the following:</p>
                        <ul class="mt-3 list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('phs.request.confirm') }}" class="space-y-6">
                    @csrf

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                        <h3 class="text-lg font-semibold text-slate-900 mb-4">Organization Information</h3>
                        <div class="grid gap-6 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Organization Name *</span>
                                <input type="text" name="organization_name" value="{{ old('organization_name') }}" required
                                    class="mt-2 block w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200" />
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Organization Type *</span>
                                <select name="organization_type" required
                                    class="mt-2 block w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200">
                                    <option value="">Select type</option>
                                    <option value="bank" {{ old('organization_type') === 'bank' ? 'selected' : '' }}>Bank</option>
                                    <option value="law_firm" {{ old('organization_type') === 'law_firm' ? 'selected' : '' }}>Law Firm</option>
                                    <option value="corporate" {{ old('organization_type') === 'corporate' ? 'selected' : '' }}>Corporate</option>
                                </select>
                            </label>
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2 mt-6">
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Phone</span>
                                <input type="tel" name="phone" value="{{ old('phone') }}"
                                    class="mt-2 block w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200" />
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Address</span>
                                <input type="text" name="address" value="{{ old('address') }}"
                                    class="mt-2 block w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200" />
                            </label>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                        <h3 class="text-lg font-semibold text-slate-900 mb-4">Contact Information</h3>
                        <div class="grid gap-6 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Contact Name *</span>
                                <input type="text" name="contact_name" value="{{ old('contact_name') }}" required
                                    class="mt-2 block w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200" />
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Email Address *</span>
                                <input type="email" name="contact_email" value="{{ old('contact_email') }}" required
                                    class="mt-2 block w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200" />
                            </label>
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2 mt-6">
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Job Title</span>
                                <input type="text" name="job_title" value="{{ old('job_title') }}"
                                    class="mt-2 block w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200" />
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Department / Division</span>
                                <input type="text" name="department" value="{{ old('department') }}"
                                    class="mt-2 block w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200" />
                            </label>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                        <h3 class="text-lg font-semibold text-slate-900 mb-4">Token Package & Notes</h3>
                        <label class="block mb-6">
                            <span class="text-sm font-medium text-slate-700">Preferred Token Package *</span>
                            <select name="initial_token_package" id="tokenPackage" required
                                class="mt-2 block w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200">
                                <option value="">Select a package</option>
                                <option value="Starter" {{ (old('initial_token_package') ?? $package) === 'Starter' ? 'selected' : '' }}>Starter - 2,000 tokens - ₦50,000</option>
                                <option value="Professional" {{ (old('initial_token_package') ?? $package) === 'Professional' ? 'selected' : '' }}>Professional - 5,000 tokens - ₦110,000</option>
                                <option value="Enterprise" {{ (old('initial_token_package') ?? $package) === 'Enterprise' ? 'selected' : '' }}>Enterprise - 10,000 tokens - ₦200,000</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Additional Notes</span>
                            <textarea name="additional_notes" rows="4" placeholder="Any special requirements or questions?" class="mt-2 block w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-200">{{ old('additional_notes') }}</textarea>
                        </label>
                    </div>

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pt-4">
                        <a href="{{ route('phs.landing') }}" class="inline-flex justify-center rounded-full border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</a>
                        <button type="submit" class="inline-flex justify-center rounded-full bg-sky-600 px-8 py-3 text-sm font-semibold text-white shadow-lg shadow-sky-600/10 transition hover:bg-sky-700">Continue to Payment →</button>
                    </div>
                </form>
            </main>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

