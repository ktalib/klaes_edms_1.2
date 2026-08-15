@extends('laas.layouts.portal')

@section('title', 'Updates — LAAS Portal')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Updates</h1>
    <p class="mt-1 text-sm text-slate-600 dark:text-gray-400">
        Every update on your applications, newest first. These are the same messages sent to your phone.
    </p>
</div>

@if($events->isEmpty())
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-gray-600 dark:bg-gray-800">
        <i data-lucide="bell-off" class="mx-auto mb-4 h-10 w-10 text-slate-400 dark:text-gray-500"></i>
        <p class="text-base font-semibold text-slate-900 dark:text-white">No updates yet</p>
        <p class="mt-2 text-sm text-slate-600 dark:text-gray-400">Updates appear here as soon as your application moves.</p>
    </div>
@else
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <ul class="divide-y divide-slate-200 dark:divide-gray-700">
            @foreach($events as $event)
                <li class="flex gap-4 p-5">
                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-green-50 text-[#1a6b3c] dark:bg-green-900/30 dark:text-green-400">
                        <i data-lucide="bell" class="h-4 w-4"></i>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $event->title }}</p>
                            <p class="text-xs text-slate-400 dark:text-gray-500">{{ $event->created_at->format('j M Y, g:ia') }}</p>
                        </div>

                        @if($event->body)
                            <p class="mt-1 text-sm text-slate-600 dark:text-gray-400">{{ $event->body }}</p>
                        @endif

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <a href="{{ route('laas.application.show', $event->reference_no) }}"
                               class="font-mono text-xs font-semibold text-[#1a6b3c] hover:underline dark:text-green-400">{{ $event->reference_no }}</a>

                            @if($event->file_number)
                                <span class="text-xs text-slate-400 dark:text-gray-500">·</span>
                                <span class="text-xs text-slate-500 dark:text-gray-400">{{ $event->file_number }}</span>
                            @endif

                            @if($event->sms_status === 'sent')
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    <i data-lucide="message-square-check" class="h-3 w-3"></i> Sent to {{ $event->sms_to }}
                                </span>
                            @elseif($event->sms_status === 'failed')
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                    <i data-lucide="message-square-x" class="h-3 w-3"></i> SMS could not be delivered
                                </span>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
@endsection
