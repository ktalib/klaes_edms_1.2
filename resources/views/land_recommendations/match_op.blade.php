@extends('layouts.app')

{{--
  Match OP — the OP-holder Match card on a page of its own.

  Same card, same rule, same writer as the one inside the Recommendation capture
  form; this page just strips everything else away. It exists so the two jobs can be
  done by two people at once: one clears the unmatched Occupancy Permits while
  another gets on with capturing recommendations, instead of the second waiting on
  the first.

  Reached at /land-recommendations/create?match-op. No role check — anyone who can
  reach the menu entry can open it. What the page can DO is still bounded by the rule
  itself: Match is offered only on a file whose chain carries no transfer explaining
  the change, and the officer confirms both names before a row is written.

  The card itself is drawn by public/js/land-recommendation-op-match.js, which keys
  off #land-recommendation-form, #file_number and #op-match-card. Those ids are kept
  here deliberately: the script is shared with the capture form, and this page
  differs only by data-opmatch-standalone, which turns on the "match another file?"
  prompt after a successful write.
--}}

@section('content')
{{-- Same shell as the capture form. Two things come from it, and the page is broken
     without either: admin.header loads Tailwind v3 (layouts/app only has v2, which
     has no arbitrary values like text-[10px] and none of the rose/slate/violet
     palette the card uses), and the "flex-1 overflow-auto relative" wrapper is what
     app-layout.css sizes the content pane with — without it the page renders in a
     half-width column beside the sidebar. --}}
<div class="flex-1 overflow-auto bg-slate-50/60 relative">
  @include('admin.header')

  <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">

      <div class="bg-slate-50 px-8 py-6 flex justify-between items-center border-b border-slate-200">
        <div>
          <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Match OP</h1>
          <p class="text-slate-500 text-sm mt-1">
            Record the missing Transfer of Title on files whose Occupancy Permit names a different holder
          </p>
        </div>
        <div class="flex items-center gap-3">
          <button type="button" id="match-op-refresh"
                  class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 rounded-lg transition border border-slate-200 shadow-sm">
            <i data-lucide="refresh-cw" class="h-4 w-4"></i> Refresh
          </button>
        </div>
      </div>

      {{-- The script needs a form element with the endpoints on it. Nothing is ever
           posted from here — Match writes through its own endpoint — so the form has
           no action and its submit is blocked below. --}}
      <form id="land-recommendation-form" onsubmit="return false;" class="p-8 space-y-6"
            data-opmatch-check-url="{{ route('land-recommendations.op-match.check') }}"
            data-opmatch-url="{{ route('land-recommendations.op-match.store') }}"
            data-opmatch-standalone="1">
        <div hidden>
          @csrf
          <input type="hidden" name="is_existing_recommendation" id="is_existing_recommendation" value="0">
          <input type="hidden" name="op_match_tot_pra_id" id="op_match_tot_pra_id" value="">
        </div>

        <div id="file-number-card" class="bg-blue-50/50 rounded-xl p-6 border border-blue-100/50">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex-1">
              <label class="block text-xs font-bold text-blue-700 uppercase tracking-wider mb-2">Selected File Number</label>
              <input type="text" name="file_number" id="file_number" readonly
                     placeholder="NO FILE SELECTED"
                     class="w-full bg-white border border-blue-200 rounded-lg px-4 py-3 text-slate-900 font-bold font-mono placeholder:text-slate-400 text-lg shadow-sm outline-none focus:ring-2 focus:ring-blue-500 transition">
            </div>
            <div class="flex flex-shrink-0 items-end">
              <button type="button" id="select-fileno-btn"
                      class="px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition flex items-center gap-2 shadow-lg shadow-blue-200">
                <i data-lucide="search" class="h-5 w-5"></i>
                Select File Number
              </button>
            </div>
          </div>
        </div>

        {{-- Drawn by the shared script. Empty until a file is picked. --}}
        <div id="op-match-card" class="hidden"></div>

        {{-- Shown when a picked file needs nothing — the card stays hidden in that
             case, and silence would read as a page that failed to load. --}}
        <div id="match-op-idle" class="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/60 px-6 py-12 text-center">
          <i data-lucide="git-merge" class="h-8 w-8 text-slate-300 mx-auto"></i>
          <p class="mt-3 text-sm font-bold text-slate-700">Select a file number to check it</p>
          <p class="mt-1 text-xs text-slate-500">
            Files whose Occupancy Permit names a different holder than File Indexing, with no dealing explaining the change, can be matched here.
          </p>
        </div>
      </form>
    </div>
  </div>
</div>

@include('components.global-fileno-modal')
@endsection

@push('scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/land-recommendation-op-match.js') }}?v={{ filemtime(public_path('js/land-recommendation-op-match.js')) }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var fileNoInput = document.getElementById('file_number');
    var idle = document.getElementById('match-op-idle');
    var card = document.getElementById('op-match-card');

    // Open the shared file-number selector. It writes any [name="file_number"] input
    // and fires a jQuery change, which is what the match script listens for.
    var pick = document.getElementById('select-fileno-btn');
    if (pick) {
        pick.addEventListener('click', function () {
            if (!window.GlobalFileNoModal || typeof window.GlobalFileNoModal.open !== 'function') return;

            // Only the file number is wanted here — the capture form's callback also
            // fills applicant, location, plot and so on, none of which exist on this
            // page. targetFields writes #file_number and fires the change the match
            // script listens for.
            window.GlobalFileNoModal.open({ targetFields: ['#file_number'] });
        });
    }

    document.getElementById('match-op-refresh').addEventListener('click', function () {
        window.location.reload();
    });

    // The idle notice and the card are mutually exclusive: whichever the check
    // produced is the one worth showing.
    function syncIdle() {
        if (!idle || !card) return;
        idle.classList.toggle('hidden', !card.classList.contains('hidden'));
    }

    if (window.MutationObserver && card) {
        new MutationObserver(syncIdle).observe(card, { attributes: true, attributeFilter: ['class'] });
    }
    if (fileNoInput) {
        fileNoInput.addEventListener('change', function () { setTimeout(syncIdle, 50); });
        if (window.jQuery) window.jQuery(fileNoInput).on('change', function () { setTimeout(syncIdle, 50); });
    }

    syncIdle();
});
</script>
@endpush
