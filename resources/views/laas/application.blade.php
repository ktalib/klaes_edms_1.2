@extends('laas.layouts.portal')

@section('title', $application->reference_no . ' — LAAS Portal')

@section('content')
@php
    use App\Models\Laas\LaasApplication;
    use App\Models\Laas\LaasDocument;

    $officeDocs    = $documents->where('source', LaasDocument::SOURCE_OFFICE);
    $applicantDocs = $documents->where('source', LaasDocument::SOURCE_APPLICANT);
@endphp

<div class="mb-6">
    <a href="{{ route('laas.dashboard') }}" class="mb-3 inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-gray-400 dark:hover:text-gray-100">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> All applications
    </a>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="font-mono text-2xl font-bold text-slate-900 dark:text-white">{{ $application->reference_no }}</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-gray-400">
                {{ $application->land_use }}
                @if($application->location) — {{ $application->location }} @endif
            </p>
        </div>

        @if($application->file_number)
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 dark:border-green-800 dark:bg-green-900/30">
                <p class="text-[10px] font-bold uppercase tracking-widest text-green-700 dark:text-green-400">Your File Number</p>
                <p class="mt-0.5 font-mono text-lg font-bold text-[#1a6b3c] dark:text-green-300">{{ $application->file_number }}</p>
            </div>
        @endif
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">

    <!-- Progress -->
    <div class="lg:col-span-1">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-5 text-sm font-extrabold uppercase tracking-widest text-slate-500 dark:text-gray-400">Progress</h2>
            @include('laas.partials.stage-tracker', ['application' => $application])
        </div>
    </div>

    <div class="space-y-6 lg:col-span-2">

        <!-- Timeline -->
        <div class="rounded-2xl border border-slate-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-gray-700">
                <h2 class="text-sm font-extrabold uppercase tracking-widest text-slate-500 dark:text-gray-400">What has happened</h2>
            </div>

            @if($events->isEmpty())
                <p class="p-6 text-sm text-slate-500 dark:text-gray-400">Nothing recorded yet.</p>
            @else
                <ul class="divide-y divide-slate-200 dark:divide-gray-700">
                    @foreach($events as $event)
                        <li class="flex gap-4 px-6 py-4">
                            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-green-50 text-[#1a6b3c] dark:bg-green-900/30 dark:text-green-400">
                                <i data-lucide="check" class="h-4 w-4"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $event->title }}</p>
                                    <p class="text-xs text-slate-400 dark:text-gray-500">{{ $event->created_at->format('j M Y, g:ia') }}</p>
                                </div>
                                @if($event->body)
                                    <p class="mt-1 text-sm text-slate-600 dark:text-gray-400">{{ $event->body }}</p>
                                @endif
                                @if($event->sms_status === 'sent')
                                    <p class="mt-1.5 inline-flex items-center gap-1 text-xs text-green-700 dark:text-green-400">
                                        <i data-lucide="message-square-check" class="h-3 w-3"></i> Texted to {{ $event->sms_to }}
                                    </p>
                                @elseif($event->sms_status === 'failed')
                                    <p class="mt-1.5 inline-flex items-center gap-1 text-xs text-amber-700 dark:text-amber-400">
                                        <i data-lucide="message-square-x" class="h-3 w-3"></i> SMS could not be delivered to {{ $event->sms_to }}
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <!-- Documents ready for the applicant -->
        @if($officeDocs->isNotEmpty())
            <div class="rounded-2xl border border-green-200 bg-green-50/50 p-6 dark:border-green-800 dark:bg-green-900/20">
                <h2 class="mb-4 flex items-center gap-2 text-sm font-extrabold uppercase tracking-widest text-[#1a6b3c] dark:text-green-400">
                    <i data-lucide="file-check-2" class="h-4 w-4"></i> Ready for you
                </h2>
                <ul class="space-y-2">
                    @foreach($officeDocs as $doc)
                        <li>
                            <a href="{{ route('laas.application.documents.download', [$application->reference_no, $doc->id]) }}"
                               class="flex items-center justify-between gap-3 rounded-lg border border-green-200 bg-white px-4 py-3 transition hover:border-[#1a6b3c] dark:border-green-800 dark:bg-gray-800">
                                <span class="flex min-w-0 items-center gap-3">
                                    <i data-lucide="file-text" class="h-4 w-4 flex-shrink-0 text-[#1a6b3c] dark:text-green-400"></i>
                                    <span class="truncate text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $docTypes[$doc->doc_type]['label'] ?? ucfirst(str_replace('_', ' ', $doc->doc_type)) }}
                                    </span>
                                </span>
                                <i data-lucide="download" class="h-4 w-4 flex-shrink-0 text-slate-400"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Applicant uploads -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-4 text-sm font-extrabold uppercase tracking-widest text-slate-500 dark:text-gray-400">Your documents</h2>

            @if($applicantDocs->isNotEmpty())
                <ul class="mb-5 space-y-2">
                    @foreach($applicantDocs as $doc)
                        <li class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-4 py-2.5 dark:border-gray-700">
                            <span class="flex min-w-0 items-center gap-3">
                                <i data-lucide="paperclip" class="h-4 w-4 flex-shrink-0 text-slate-400"></i>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $docTypes[$doc->doc_type]['label'] ?? $doc->doc_type }}
                                    </span>
                                    <span class="block truncate text-xs text-slate-500 dark:text-gray-400">{{ $doc->original_name }}</span>
                                </span>
                            </span>
                            <a href="{{ route('laas.application.documents.download', [$application->reference_no, $doc->id]) }}"
                               class="flex-shrink-0 rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-gray-700">
                                <i data-lucide="download" class="h-4 w-4"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('laas.application.documents.upload', $application->reference_no) }}"
                  enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="min-w-[180px] flex-1">
                    <label for="doc_type" class="block text-xs font-medium text-slate-700 dark:text-gray-300">Document type</label>
                    <select id="doc_type" name="doc_type" required
                            class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        @foreach($docTypes as $key => $meta)
                            <option value="{{ $key }}">{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[180px] flex-1">
                    <label for="file" class="block text-xs font-medium text-slate-700 dark:text-gray-300">File (PDF or image, max 5&nbsp;MB)</label>
                    <input id="file" type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png"
                           class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:file:bg-gray-600 dark:file:text-gray-200">
                </div>
                <button type="submit" class="laas-btn rounded-lg px-4 py-2 text-sm font-semibold text-white transition">
                    <i data-lucide="upload" class="mr-1.5 inline h-4 w-4"></i> Upload
                </button>
            </form>
        </div>

        <!-- Submitted details -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-4 text-sm font-extrabold uppercase tracking-widest text-slate-500 dark:text-gray-400">What you submitted</h2>
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                @foreach([
                    'Applicant'      => $application->applicant_name,
                    'Applicant type' => $application->applicant_type,
                    'Phone'          => $application->applicant_phone,
                    'Email'          => $application->applicant_email,
                    'Land use'       => $application->land_use,
                    'LGA'            => $lga->name ?? null,
                    'District'       => $district->name ?? null,
                    'Location'       => $application->location,
                    'Plot number'    => $application->plot_no,
                    'Approx. size'   => $application->approx_size,
                    'Submitted'      => $application->submitted_at?->format('j M Y, g:ia'),
                ] as $term => $value)
                    @if($value)
                        <div>
                            <dt class="text-xs font-medium text-slate-500 dark:text-gray-400">{{ $term }}</dt>
                            <dd class="mt-0.5 text-sm text-slate-900 dark:text-white">{{ $value }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>

            @if($application->applicant_remarks)
                <div class="mt-5 border-t border-slate-200 pt-4 dark:border-gray-700">
                    <p class="text-xs font-medium text-slate-500 dark:text-gray-400">Your remarks</p>
                    <p class="mt-1 text-sm text-slate-900 dark:text-white">{{ $application->applicant_remarks }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
