@extends('online_legal_search.layout')

@section('title', 'Online Legal Search')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
  .loading-spinner {
    width: 1rem; height: 1rem;
    border: 2px solid #e5e7eb; border-top: 2px solid #0891b2;
    border-radius: 50%; animation: spin 1s linear infinite;
  }
  @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

  /* Make Select2 match the surrounding rounded inputs */
  .ols-select2 .select2-container { width: 100% !important; }
  .ols-select2 .select2-container--default .select2-selection--single {
    height: 3rem;                 /* h-12 */
    border: 1px solid #d1d5db;    /* gray-300 */
    border-radius: 0.375rem;      /* rounded-md */
    padding-left: 2.5rem;         /* room for the search icon */
    display: flex; align-items: center;
  }
  .ols-select2 .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 1.5; color: #111827; padding: 0;
  }
  .ols-select2 .select2-container--default .select2-selection--single .select2-selection__placeholder { color: #9ca3af; }
  .ols-select2 .select2-container--default .select2-selection--single .select2-selection__arrow { height: 3rem; right: 0.5rem; }
  .ols-select2 .select2-container--default.select2-container--focus .select2-selection--single,
  .ols-select2 .select2-container--default.select2-container--open .select2-selection--single {
    border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,.1);
  }
  /* Advanced compact variant (h-10) */
  .ols-select2-sm .select2-container--default .select2-selection--single { height: 2.5rem; padding-left: 0.75rem; }
  .ols-select2-sm .select2-container--default .select2-selection--single .select2-selection__arrow { height: 2.5rem; }

  /* Dropdown + search field readability (page may be in dark mode) */
  .select2-dropdown { border-color:#d1d5db; border-radius:.5rem; box-shadow:0 10px 25px rgba(0,0,0,.08); background:#fff; }
  .select2-container--default .select2-search--dropdown .select2-search__field {
    color:#111827; border:1px solid #d1d5db; border-radius:.375rem; padding:.4rem .6rem;
  }
  .select2-container--default .select2-results__option { color:#111827; }
  .select2-container--default .select2-results__option--highlighted[aria-selected] { background:#2563eb; color:#fff; }
  .select2-results__message { color:#6b7280; }
</style>
@endpush

@section('body')
<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-green-50">

  @include('online_legal_search.partials.header')

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Hero -->
    <section id="hero-section" class="relative py-12 md:py-16 bg-gradient-to-br from-blue-100 via-sky-100 to-green-100 rounded-xl shadow-lg overflow-hidden mb-12">
      <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-12 items-center">
        <div class="text-center lg:text-left">
          <div class="inline-flex items-center rounded-full text-xs font-medium px-3 py-1 bg-white/90 border border-blue-300 text-blue-700 shadow-md mb-4">
            <i data-lucide="globe" class="w-4 h-4 mr-2 text-blue-600"></i> Available 24/7 Online
          </div>
          <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold text-gray-900 mb-5 leading-tight">
            Official Legal Search,
            <span class="block text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-green-600">Made Simple &amp; Instant.</span>
          </h1>
          <p class="text-lg md:text-xl text-gray-700 mb-2 max-w-2xl mx-auto lg:mx-0">
            Securely access official land records and verify property information with the  LAnd ADmin Enterprise System.
          </p>
        </div>
        <div class="relative">
          <div class="overflow-hidden rounded-2xl shadow-2xl ring-1 ring-white/60">
            <img src="{{ asset('assets/images/pages/d.png') }}" alt="Staff conducting an online legal search"
                 class="w-full h-auto object-cover" loading="lazy">
          </div>
        </div>
      </div>
    </section>

    <!-- Search -->
    <section id="search-section" class="pb-8">
      <div class="bg-white shadow-xl rounded-xl border-none">
        <div class="p-6 md:p-8">
          <div class="mb-6">
            <div class="inline-flex bg-gray-100 rounded-lg p-1">
              <button id="quick-tab" class="px-4 py-2 rounded-md bg-white text-gray-700 border-0 cursor-pointer transition-all shadow-sm">Quick Search</button>
              <button id="advanced-tab" class="px-4 py-2 rounded-md bg-transparent text-gray-500 border-0 cursor-pointer transition-all hover:bg-gray-50">Advanced Search</button>
            </div>
          </div>

          <!-- Quick -->
          <div id="quick-search">
            <div class="flex flex-col sm:flex-row items-center gap-3 max-w-2xl">
              <div class="flex-1 relative w-full ols-select2">
                <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5 z-10 pointer-events-none"></i>
                <select id="search-query" class="w-full"><option></option></select>
              </div>
              <button id="search-btn" class="inline-flex items-center justify-center rounded-md font-medium border-0 bg-blue-600 text-white hover:bg-blue-700 px-7 h-12 text-base whitespace-nowrap">
                <i data-lucide="search" class="w-5 h-5 mr-2"></i> Search
              </button>
            </div>
            <p class="text-xs text-gray-500 mt-2.5">
              Start typing a file number to pick from official records — or type an owner / plot number and press Search.
              <span class="text-gray-400">e.g. "COM-RES-2021-78", "KN12345"</span>
            </p>
          </div>

          <!-- Advanced -->
          <div id="advanced-search" class="hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-4">
              <div class="ols-select2 ols-select2-sm">
                <label class="block text-sm font-medium text-gray-700 mb-1">File Number</label>
                <select id="adv-file-number" class="w-full"><option></option></select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Party 1 (Guarantor)</label>
                <input id="adv-guarantor" placeholder="Enter name" class="w-full border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 h-10 px-3" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Party 2 (Guarantee)</label>
                <input id="adv-guarantee" placeholder="Enter name" class="w-full border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 h-10 px-3" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Plan Number</label>
                <input id="adv-plan" placeholder="e.g., KN/2021/001" class="w-full border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 h-10 px-3" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Plot Number</label>
                <input id="adv-plot" placeholder="e.g., A123" class="w-full border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 h-10 px-3" />
              </div>
              <div class="ols-select2 ols-select2-sm">
                <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                <select id="adv-district" class="w-full">
                  <option></option>
                  @foreach (($districtOptions ?? []) as $district)
                    <option value="{{ $district }}">{{ $district }}</option>
                  @endforeach
                </select>
              </div>
              <div class="ols-select2 ols-select2-sm">
                <label class="block text-sm font-medium text-gray-700 mb-1">LGA</label>
                <select id="adv-lga" class="w-full">
                  <option></option>
                  @foreach (($lgaOptions ?? []) as $lga)
                    <option value="{{ $lga }}">{{ $lga }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                <input id="adv-location" placeholder="Enter location" class="w-full border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 h-10 px-3" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Size</label>
                <input id="adv-size" placeholder="e.g., 1000sqm" class="w-full border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 h-10 px-3" />
              </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
              <button id="advanced-reset-btn" class="inline-flex items-center justify-center rounded-md font-medium text-sm bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 h-10 px-5">Reset</button>
              <button id="advanced-search-btn" class="inline-flex items-center justify-center rounded-md font-medium text-sm border-0 bg-blue-600 text-white hover:bg-blue-700 h-10 px-5">
                <i data-lucide="search" class="w-4 h-4 mr-2"></i> Search
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Loading -->
    <section id="loading-section" class="hidden pb-12">
      <div class="flex justify-center items-center py-20"><div class="loading-spinner" style="width:2.5rem;height:2.5rem;border-width:3px;"></div></div>
    </section>

    <!-- Results -->
    <section id="results-section" class="hidden pb-12">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-800">Search Results <span id="results-count" class="text-gray-500 font-normal"></span></h2>
      </div>

      <!-- No Results -->
      <div id="no-results" class="bg-white shadow-lg rounded-xl border-none hidden">
        <div class="p-10 text-center">
          <i data-lucide="file-search" class="w-16 h-16 text-gray-300 mx-auto mb-6"></i>
          <h3 class="text-2xl font-semibold text-gray-700 mb-3">No Results Found</h3>
          <p class="text-gray-500 mb-6 max-w-md mx-auto">We couldn't find any records matching your search criteria. Please try refining your search or use different keywords.</p>
          <button id="try-new-search" class="inline-flex items-center justify-center rounded-md font-medium bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 text-base px-6 py-2.5">
            <i data-lucide="rotate-ccw" class="w-4 h-4 mr-2"></i> Try a New Search
          </button>
        </div>
      </div>

      <!-- Summary preview card -->
      <div id="summary-card" class="hidden"></div>

      <!-- File Information (file indexed but no transactions) -->
      {{-- A file with no transactions is still a searchable, payable record: the report
           renders its synthetic File Commissioning row (LegalSearchService::buildPrintReport
           only 404s when the file itself does not exist). So this card carries the same
           Next / payment step as the summary card, with a note setting the expectation. --}}
      <div id="file-info-card" class="hidden">
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 max-w-2xl overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800">File Information</h3>
          </div>
          <div id="file-info-body" class="px-6 py-5"></div>
          <div class="px-6 pb-5">
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
              No registered transaction is recorded against this file. The full Legal Search report will
              show its File Commissioning entry only, and still requires sign-in and a &#8358;10,000 payment.
            </div>
          </div>
          <div class="bg-gray-50 px-6 py-4 flex justify-end border-t border-gray-200">
            <button id="file-info-next-btn" class="inline-flex items-center justify-center rounded-md font-medium border-0 bg-blue-600 text-white hover:bg-blue-700 px-6 py-2.5">
              <i data-lucide="lock" class="w-4 h-4 mr-2"></i> Next
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section id="features-section" class="py-16 bg-white rounded-xl shadow-lg mb-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Why choose Ministry of Land &amp; Physical Planning Legal Search?</h2>
          <p class="text-lg text-gray-600 max-w-3xl mx-auto">Instant access to official land records on a secure, government-approved platform.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div class="text-center p-6">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-5 ring-4 ring-blue-200/50"><i data-lucide="shield" class="w-8 h-8 text-blue-600"></i></div>
            <h3 class="text-xl font-semibold text-gray-800 mb-3">Official &amp; Secure</h3>
            <p class="text-gray-600 text-sm">Government-certified platform with secure data handling.</p>
          </div>
          <div class="text-center p-6">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-5 ring-4 ring-green-200/50"><i data-lucide="clock" class="w-8 h-8 text-green-600"></i></div>
            <h3 class="text-xl font-semibold text-gray-800 mb-3">Instant Results</h3>
            <p class="text-gray-600 text-sm">Get comprehensive legal search reports in minutes.</p>
          </div>
          <div class="text-center p-6">
            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-5 ring-4 ring-purple-200/50"><i data-lucide="file-text" class="w-8 h-8 text-purple-600"></i></div>
            <h3 class="text-xl font-semibold text-gray-800 mb-3">Comprehensive Reports</h3>
            <p class="text-gray-600 text-sm">Ownership history, encumbrances, and legal status.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-16 bg-gradient-to-r from-blue-600 via-indigo-700 to-purple-700 rounded-xl shadow-2xl mb-8 relative overflow-hidden">
      <div class="absolute inset-0 bg-black/20"></div>
      <div class="relative max-w-4xl mx-auto text-center px-4">
        <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Ready to Get Started?</h2>
        <p class="text-lg text-blue-100 mb-8 max-w-2xl mx-auto">Each official Online Legal Search result is available for <span class="font-semibold text-white">₦10,000</span> after a quick, secure payment.</p>
        <button id="start-search-btn" class="inline-flex items-center justify-center rounded-md bg-slate-100 text-slate-900 hover:bg-slate-200 px-10 py-3.5 text-lg font-semibold shadow-lg">
          <i data-lucide="search" class="w-5 h-5 mr-2.5"></i> Start Your Search
        </button>
      </div>
    </section>
  </div>

  @include('online_legal_search.partials.footer')
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
  const SEARCH_URL = @json(route('ols.search'));
  const RESULT_URL = @json(route('ols.result'));
  const FILES_URL  = @json(route('ols.file-numbers'));
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;

  // ---- Select2 file-number autocomplete (sourced from file_indexings) ----
  function initFileSelect($sel, placeholder, opts) {
    opts = opts || {};
    $sel.select2({
      placeholder: placeholder,
      allowClear: true,
      width: '100%',
      minimumInputLength: 2,
      tags: !!opts.allowTags,            // let users type free text (owner/plot) too
      dropdownParent: $sel.parent(),
      ajax: {
        url: FILES_URL,
        dataType: 'json',
        delay: 250,
        data: params => ({ term: params.term || '' }),
        processResults: data => ({ results: data.results || [] }),
        cache: true,
      },
      language: {
        inputTooShort: () => 'Type at least 2 characters…',
        searching: () => 'Searching file numbers…',
        noResults: () => opts.allowTags ? 'No file match — press Enter to search this term' : 'No matching file found',
      },
    });
    return $sel;
  }

  const el = (id) => document.getElementById(id);
  const sections = {
    hero: el('hero-section'), features: el('features-section'),
    loading: el('loading-section'), results: el('results-section'),
  };

  function show(state) {
    sections.loading.classList.toggle('hidden', state !== 'loading');
    sections.results.classList.toggle('hidden', state !== 'results');
  }

  // Tab switching
  const quickTab = el('quick-tab'), advTab = el('advanced-tab');
  const quickPane = el('quick-search'), advPane = el('advanced-search');
  function activateTab(which) {
    const on = 'bg-white text-gray-700 shadow-sm', off = 'bg-transparent text-gray-500 hover:bg-gray-50';
    quickTab.className = 'px-4 py-2 rounded-md border-0 cursor-pointer transition-all ' + (which === 'quick' ? on : off);
    advTab.className   = 'px-4 py-2 rounded-md border-0 cursor-pointer transition-all ' + (which === 'adv' ? on : off);
    quickPane.classList.toggle('hidden', which !== 'quick');
    advPane.classList.toggle('hidden', which !== 'adv');
  }
  quickTab.addEventListener('click', () => activateTab('quick'));
  advTab.addEventListener('click', () => activateTab('adv'));

  // The File Information card is static markup (only its body is re-rendered), so
  // its Next button is wired once here — wiring it per render would stack listeners.
  el('file-info-next-btn').addEventListener('click', goToResult);

  function runSearch(params) {
    if (!Object.values(params).some(v => (v || '').trim() !== '')) return;
    window.lastSearchParams = params;
    show('loading');

    fetch(SEARCH_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: JSON.stringify(params),
    })
    .then(r => r.json())
    .then(data => renderResults(data))
    .catch(() => renderResults({ has_results: false, error: true }))
    .finally(() => { if (window.lucide) window.lucide.createIcons(); });
  }

  function renderResults(data) {
    show('results');
    const noResults = el('no-results');
    const card = el('summary-card');
    const count = el('results-count');

    if (!data || !data.has_results) {
      card.classList.add('hidden');
      count.textContent = '';
      // If the file itself is indexed (even with no transactions), show its
      // File Information instead of a bare "No Results Found" message.
      if (data && data.file_info && renderFileInfo(data.file_info)) {
        noResults.classList.add('hidden');
        el('file-info-card').classList.remove('hidden');
      } else {
        el('file-info-card').classList.add('hidden');
        noResults.classList.remove('hidden');
      }
      if (window.lucide) window.lucide.createIcons();
      return;
    }

    el('file-info-card').classList.add('hidden');
    noResults.classList.add('hidden');
    count.textContent = `(${data.total_count} record${data.total_count === 1 ? '' : 's'} found)`;
    const s = data.summary || {};
    const districtLga = [s.district, s.lga].filter(Boolean).join(' / ') || '—';
    const caveatBadge = s.is_caveated
      ? '<span class="inline-flex items-center rounded-full text-xs font-medium px-2.5 py-1 bg-red-500 text-white">Caveat: Yes</span>'
      : '';

    card.classList.remove('hidden');
    card.innerHTML = `
      <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden max-w-2xl">
        <div class="p-6">
          <div class="flex justify-between items-start mb-4">
            <div>
              <div class="font-semibold text-xl text-gray-800 flex items-center">
                <i data-lucide="file-text" class="h-5 w-5 mr-2.5 text-blue-500"></i>${esc(s.file_number) || '—'}
              </div>
              <div class="text-sm text-gray-500 mt-1">${esc(s.file_title) || 'Matched record'}</div>
            </div>
            ${caveatBadge}
          </div>
          <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div><span class="text-gray-500">District/LGA:</span> <span class="text-gray-700 font-medium">${esc(districtLga)}</span></div>
            <div><span class="text-gray-500">Land Use:</span> <span class="text-gray-700 font-medium">${esc(s.land_use) || '—'}</span></div>
            <div><span class="text-gray-500">Plot No:</span> <span class="text-gray-700 font-medium">${esc(s.plot_number) || '—'}</span></div>
            <div><span class="text-gray-500">Size:</span> <span class="text-gray-700 font-medium">${esc(s.size) || '—'}</span></div>
            <div><span class="text-gray-500">Transaction(s):</span> <span class="text-gray-700 font-medium">${s.transaction_count ?? '—'}</span></div>
          </div>
          <div class="mt-5 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
            This is a preview only. The full Legal Search report (complete file history &amp; particulars) requires sign-in and a ₦10,000 payment.
          </div>
        </div>
        <div class="bg-gray-50 px-6 py-4 flex justify-end border-t border-gray-200">
          <button id="view-result-btn" class="inline-flex items-center justify-center rounded-md font-medium border-0 bg-blue-600 text-white hover:bg-blue-700 px-6 py-2.5">
            <i data-lucide="lock" class="w-4 h-4 mr-2"></i> Next
          </button>
        </div>
      </div>`;

    el('view-result-btn').addEventListener('click', goToResult);
    if (window.lucide) window.lucide.createIcons();
  }

  // Navigate to the gated result page. If the user is not authenticated, the
  // result route's auth:online_ls middleware redirects to login and returns
  // them here afterwards (intended redirect); otherwise it shows the payment step.
  function goToResult() {
    const p = window.lastSearchParams || {};
    const qs = new URLSearchParams();
    Object.entries(p).forEach(([k, v]) => { if (v) qs.append(k, v); });
    window.location.href = RESULT_URL + '?' + qs.toString();
  }

  function esc(v) {
    if (v === null || v === undefined) return '';
    return String(v).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }

  // ---- File Information fallback (mirrors the main Legal Search card) --------
  const LAND_USE_PREFIX_MAP = {
    'RES': 'Residential', 'COM': 'Commercial', 'IND': 'Industrial', 'AG': 'Agriculture',
    'CON-RES': 'Residential', 'CON-COM': 'Commercial', 'CON-IND': 'Industrial', 'CON-AG': 'Agriculture',
    'RES-RC': 'Residential', 'COM-RC': 'Commercial', 'IND-RC': 'Industrial', 'AG-RC': 'Agriculture',
    'CON-RES-RC': 'Residential', 'CON-COM-RC': 'Commercial', 'CON-IND-RC': 'Industrial', 'CON-AG-RC': 'Agriculture',
  };

  function deriveLandUseFromFileNumber(fileNumber) {
    if (!fileNumber) return '';
    const normalized = String(fileNumber).toUpperCase().replace(/[\/=_]+/g, '-').trim();
    const prefixTokens = [];
    for (const token of normalized.split('-').filter(Boolean)) {
      if (/^\d+$/.test(token)) break;
      prefixTokens.push(token);
    }
    if (prefixTokens[0] === 'ST') prefixTokens.shift();
    const prefix = prefixTokens.join('-');
    if (LAND_USE_PREFIX_MAP[prefix]) return LAND_USE_PREFIX_MAP[prefix];
    for (const token of prefixTokens) {
      if (LAND_USE_PREFIX_MAP[token]) return LAND_USE_PREFIX_MAP[token];
    }
    return '';
  }

  function deriveFileType(fileNumber) {
    if (!fileNumber) return '';
    const n = String(fileNumber).toUpperCase().replace(/\//g, '-').trim();
    if (/^ST-(RES|COM|IND|AG)-\d{4}-\d+(-\d+)?$/.test(n)) return 'ST';
    if (/^(COM|RES|IND|AG|CON-COM|CON-RES|CON-AG|CON-IND)-\d{4}-\d+$/.test(n) ||
        /^(COM|RES|IND|AG|CON-COM|CON-RES|CON-AG|CON-IND)-\d+$/.test(n)) return 'MLS';
    if (/^[A-Z]{4}\s?\d{3,6}$/.test(n)) return 'KANGIS';
    if (/^KN\d{2,6}$/.test(n)) return 'New KANGIS';
    return '';
  }

  function renderFileInfo(info) {
    if (!info) return false;
    const pick = (...vals) => {
      for (const v of vals) {
        if (v !== null && v !== undefined && String(v).trim() !== '' && String(v).trim() !== '-') return String(v).trim();
      }
      return '';
    };

    const fileNumber = pick(info.file_number);
    const rows = [
      ['File Number',     fileNumber],
      ['File Type',       deriveFileType(fileNumber)],
      ['File Title',      pick(info.file_title)],
      ['Land Use',        pick(info.land_use) || deriveLandUseFromFileNumber(fileNumber)],
      ['Location',        pick(info.location)],
      ['Plot No',         pick(info.plot_number)],
      ['Size',            pick(info.size)],
      ['TP No',           pick(info.tp_no)],
      ['District',        pick(info.district)],
      ['LGA',             pick(info.lga)],
      ['Related File No', pick(info.related_fileno)],
    ].filter(([, value]) => value !== '');

    if (!rows.length) return false;

    const cells = rows.map(([label, value]) => `
      <div class="flex items-start gap-2.5">
        <i data-lucide="chevron-right" class="h-4 w-4 text-gray-400 flex-shrink-0 mt-0.5"></i>
        <div class="min-w-0">
          <div class="text-[11px] uppercase tracking-wide text-gray-400 leading-tight">${esc(label)}</div>
          <div class="text-sm font-medium text-gray-800 break-words leading-snug">${esc(value)}</div>
        </div>
      </div>`).join('');

    el('file-info-body').innerHTML = `<div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">${cells}</div>`;
    return true;
  }

  // ---- Wire everything up once jQuery/Select2 are ready ----
  jQuery(function ($) {
    const $quick = initFileSelect($('#search-query'), 'Search file number, KANGIS No., owner, plot…', { allowTags: true });
    const $advFile = initFileSelect($('#adv-file-number'), 'e.g., COM-RES-2021-078', { allowTags: true });

    // District / LGA lookup dropdowns (searchable select2 over preloaded options).
    function initLookupSelect($sel, placeholder) {
      $sel.select2({
        placeholder: placeholder,
        allowClear: true,
        width: '100%',
        dropdownParent: $sel.parent(),
      });
      return $sel;
    }
    const $advDistrict = initLookupSelect($('#adv-district'), 'Select district');
    const $advLga      = initLookupSelect($('#adv-lga'), 'Select LGA');

    const quickValue = () => ($quick.val() || '').toString().trim();

    // Quick search
    $('#search-btn').on('click', () => runSearch({ query: quickValue() }));
    // Auto-run as soon as a real file number is picked from the list.
    $quick.on('select2:select', e => {
      const d = e.params.data || {};
      if (d.title || d.district) runSearch({ query: quickValue() });
    });

    // Advanced search
    $('#advanced-search-btn').on('click', () => runSearch({
      query:         ($advFile.val() || '').toString().trim(),
      guarantorName: el('adv-guarantor').value,
      guaranteeName: el('adv-guarantee').value,
      plotNumber:    el('adv-plot').value,
      planNumber:    el('adv-plan').value,
      district:      el('adv-district').value,
      location:      el('adv-location').value,
      lga:           el('adv-lga').value,
      size:          el('adv-size').value,
    }));
    $('#advanced-reset-btn').on('click', () => {
      $advFile.val(null).trigger('change');
      $advDistrict.val(null).trigger('change');
      $advLga.val(null).trigger('change');
      ['adv-guarantor','adv-guarantee','adv-plot','adv-plan','adv-location','adv-size'].forEach(id => el(id).value = '');
    });

    const searchSection = el('search-section');
    const focusQuick = () => $quick.select2('open');
    const revealSearch = () => {
      show(null);
      sections.results.classList.add('hidden');
      if (searchSection) searchSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      focusQuick();
    };
    $('#try-new-search').on('click', revealSearch);
    $('#start-search-btn').on('click', revealSearch);

    // Header "New Search": on the landing page, reveal the search section in
    // place instead of triggering a full page reload.
    $('#new-search-nav').on('click', (e) => { e.preventDefault(); revealSearch(); });

    // Carry forward a query passed via URL (e.g. deep link)
    const initialQuery = new URLSearchParams(window.location.search).get('query');
    if (initialQuery) {
      const opt = new Option(initialQuery, initialQuery, true, true);
      $quick.append(opt).trigger('change');
      runSearch({ query: initialQuery });
    }
  });
})();
</script>
@endpush
