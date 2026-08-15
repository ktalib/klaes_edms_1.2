@php
    use App\Models\Laas\LaasApplication;

    /**
     * $application — the application to draw.
     * $compact    — optional; a single slim progress bar instead of the full list.
     *
     * `draft` is dropped: an application the applicant is still filling has no
     * journey to show yet. `rejected` is off the main line entirely and gets its
     * own banner rather than a position on the tracker.
     */
    $steps = array_values(array_filter(
        LaasApplication::ORDER,
        fn ($s) => $s !== LaasApplication::STAGE_DRAFT
    ));

    $icons = [
        LaasApplication::STAGE_SUBMITTED               => 'send',
        LaasApplication::STAGE_DIRECTOR_APPROVED       => 'user-check',
        LaasApplication::STAGE_FILENO_ASSIGNED         => 'hash',
        LaasApplication::STAGE_LAND12_RAISED           => 'file-text',
        LaasApplication::STAGE_AT_CADASTRAL            => 'ruler',
        LaasApplication::STAGE_LAND12_COMPLETED        => 'map',
        LaasApplication::STAGE_RECOMMENDATION_PENDING  => 'hourglass',
        LaasApplication::STAGE_RECOMMENDATION_APPROVED => 'clipboard-check',
        LaasApplication::STAGE_ROFO_GENERATED          => 'file-check-2',
        LaasApplication::STAGE_ROFO_SIGNED             => 'stamp',
    ];

    $currentRank = LaasApplication::rank($application->stage);
    $isRejected  = $application->stage === LaasApplication::STAGE_REJECTED;
    $done        = $isRejected ? 0 : max($currentRank, 0);
    $percent     = (int) round($done / count($steps) * 100);
@endphp

@if($isRejected)
    <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/30">
        <i data-lucide="x-circle" class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400"></i>
        <div>
            <p class="text-sm font-semibold text-red-900 dark:text-red-200">This application was not approved</p>
            @if($application->rejection_reason)
                <p class="mt-1 text-sm text-red-800 dark:text-red-300">{{ $application->rejection_reason }}</p>
            @endif
        </div>
    </div>
@elseif(!empty($compact))
    <div>
        <div class="mb-1.5 flex items-center justify-between text-xs">
            <span class="font-medium text-slate-700 dark:text-gray-300">{{ LaasApplication::label($application->stage) }}</span>
            <span class="text-slate-500 dark:text-gray-400">{{ $percent }}%</span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-gray-700">
            <div class="h-full rounded-full bg-[#1a6b3c] transition-all" style="width: {{ $percent }}%"></div>
        </div>
    </div>
@else
    <ol class="space-y-0">
        @foreach($steps as $i => $step)
            @php
                $rank      = LaasApplication::rank($step);
                $isDone    = $rank < $currentRank;
                $isCurrent = $rank === $currentRank;
                $isLast    = $i === count($steps) - 1;
            @endphp
            <li class="flex gap-4">
                <!-- Rail -->
                <div class="flex flex-col items-center">
                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2 transition
                        @if($isDone) border-[#1a6b3c] bg-[#1a6b3c] text-white
                        @elseif($isCurrent) border-[#f0a500] bg-[#f0a500] text-white
                        @else border-slate-300 bg-white text-slate-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-500 @endif">
                        <i data-lucide="{{ $isDone ? 'check' : ($icons[$step] ?? 'circle') }}" class="h-4 w-4"></i>
                    </div>
                    @unless($isLast)
                        <div class="w-0.5 flex-1 {{ $isDone ? 'bg-[#1a6b3c]' : 'bg-slate-200 dark:bg-gray-700' }}"></div>
                    @endunless
                </div>

                <!-- Label -->
                <div class="{{ $isLast ? 'pb-0' : 'pb-6' }} pt-1">
                    <p class="text-sm font-semibold
                        @if($isCurrent) text-[#b37a00] dark:text-amber-400
                        @elseif($isDone) text-slate-900 dark:text-white
                        @else text-slate-400 dark:text-gray-500 @endif">
                        {{ LaasApplication::label($step) }}
                        @if($isCurrent)
                            <span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Current</span>
                        @endif
                    </p>
                </div>
            </li>
        @endforeach
    </ol>
@endif
