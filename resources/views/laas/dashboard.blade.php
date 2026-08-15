@extends('laas.layouts.portal')

@section('title', 'Dashboard — LAAS Portal')

@section('content')
@php use App\Models\Laas\LaasApplication; @endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">My applications</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-gray-400">
            Every land allocation application you have started, and where each one has reached.
        </p>
    </div>
    <a href="{{ route('laas.apply.form') }}"
       class="laas-btn inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition">
        <i data-lucide="file-plus-2" class="h-4 w-4"></i> New application
    </a>
</div>

@if($applications->isEmpty())
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-gray-600 dark:bg-gray-800">
        <i data-lucide="folder-open" class="mx-auto mb-4 h-10 w-10 text-slate-400 dark:text-gray-500"></i>
        <p class="text-base font-semibold text-slate-900 dark:text-white">You have not applied yet</p>
        <p class="mx-auto mt-2 max-w-md text-sm text-slate-600 dark:text-gray-400">
            Start your first land allocation application. You will be told by SMS the moment it is received,
            and at every stage after that.
        </p>
        <a href="{{ route('laas.apply.form') }}"
           class="laas-btn mt-6 inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold text-white transition">
            <i data-lucide="file-plus-2" class="h-4 w-4"></i> Start an application
        </a>
    </div>
@else
    <div class="grid gap-4 md:grid-cols-2">
        @foreach($applications as $application)
            @php $isDraft = $application->stage === LaasApplication::STAGE_DRAFT; @endphp

            <a href="{{ $isDraft ? route('laas.apply.form') : route('laas.application.show', $application->reference_no) }}"
               class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#1a6b3c] hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-green-500">

                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-mono text-sm font-bold text-slate-900 dark:text-white">{{ $application->reference_no }}</p>
                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-gray-400">
                            {{ $application->land_use ?: 'Land use not set' }}
                            @if($application->location) — {{ $application->location }} @endif
                        </p>
                    </div>

                    @if($isDraft)
                        <span class="flex-shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-600 dark:bg-gray-700 dark:text-gray-300">Draft</span>
                    @elseif($application->stage === LaasApplication::STAGE_REJECTED)
                        <span class="flex-shrink-0 rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-red-700 dark:bg-red-900/40 dark:text-red-300">Not approved</span>
                    @elseif($application->stage === LaasApplication::STAGE_ROFO_SIGNED)
                        <span class="flex-shrink-0 rounded-full bg-green-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green-800 dark:bg-green-900/40 dark:text-green-300">Complete</span>
                    @else
                        <span class="flex-shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">In progress</span>
                    @endif
                </div>

                @if($application->file_number)
                    <p class="mb-3 inline-flex items-center gap-1.5 rounded-md bg-green-50 px-2 py-1 text-xs font-semibold text-[#1a6b3c] dark:bg-green-900/30 dark:text-green-300">
                        <i data-lucide="hash" class="h-3 w-3"></i> {{ $application->file_number }}
                    </p>
                @endif

                @if($isDraft)
                    <p class="text-sm text-slate-600 dark:text-gray-400">Not submitted yet — continue where you left off.</p>
                @else
                    @include('laas.partials.stage-tracker', ['application' => $application, 'compact' => true])
                @endif

                <p class="mt-3 text-xs text-slate-400 dark:text-gray-500">
                    {{ $application->submitted_at ? 'Submitted ' . $application->submitted_at->format('j M Y') : 'Started ' . $application->created_at->format('j M Y') }}
                </p>
            </a>
        @endforeach
    </div>
@endif
@endsection
