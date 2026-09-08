@extends('layouts.app')

@php
    use App\Models\MasterJsiReport;
    use App\Support\MasterJsiPortionTemplates;
    use App\Support\MasterJsiSubjectResolver;

    /**
     * The Master JSI capture form — one form, five sheets.
     *
     * The officer picks a CATEGORY (SPU or APU) and then the PARCEL UPDATE TYPE; the
     * measurement table below redraws itself from MasterJsiPortionTemplates for that
     * type, so Extension asks for Existing Title / Extension Portion / Recommended
     * Site Portion and Merger asks for Portion A / Portion B / Recommended Merged
     * Portion, off the same page.
     *
     * When the form was opened from a parcel-update action menu the category, type
     * and record are already known and are locked — the inspection is about that
     * record, and letting them be changed here would file the observation against
     * the wrong parcel.
     */
    $isEdit = $report !== null;

    // Existing rows keyed by role, so an edit refills the template it was captured
    // against rather than starting the officer over.
    $existingPortions = $isEdit
        ? $report->portions->keyBy('role')
        : collect();

    $value = function (string $field, $default = null) use ($report, $prefill) {
        if ($report && $report->{$field} !== null && $report->{$field} !== '') {
            return $report->{$field};
        }
        return $prefill[$field] ?? $default;
    };

    $segments = $isEdit ? ($report->boundary_segments ?? []) : [];

    /**
     * The five sheets, flattened for the page script.
     *
     * Built here rather than inline in @json(): Blade parses a directive's argument
     * by matching parentheses, and a multi-line nested array truncates at the first
     * comma it cannot follow — silently, producing a script that half-exists.
     */
    $jsTemplates = collect(MasterJsiPortionTemplates::types())
        ->mapWithKeys(fn ($t) => [$t => [
            'heading'    => MasterJsiPortionTemplates::heading($t),
            'narrative'  => MasterJsiPortionTemplates::narrative($t),
            'rows'       => MasterJsiPortionTemplates::rows($t),
            'repeatable' => MasterJsiPortionTemplates::repeatable($t),
        ]]);

    $jsExisting = $existingPortions->map(fn ($p) => [
        'role'       => $p->role,
        'label'      => $p->label,
        'dimensions' => $p->dimensions,
        'area_sqm'   => $p->area_sqm,
        'land_use'   => $p->land_use,
        'unit_count' => $p->unit_count,
    ]);
@endphp

@section('styles')
<style>
    .swal2-container { z-index: 20000 !important; }

    /* The recommended row is the ANSWER the sheet gives, not another observation,
       so it is set apart rather than sitting as one more line in the run. */
    .portion-row.is-derived { background:#f0fdf4; }
    .portion-row.is-derived td { border-color:#bbf7d0; }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle'       => $isEdit ? 'Master JSI ' . $report->jsi_ref : 'New Master JSI',
        'PageDescription' => 'Physical Planning site observation for a parcel update.',
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

        {{-- Header --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm px-7 py-6 flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-red-50 flex items-center justify-center shrink-0">
                    <i data-lucide="ruler" class="w-6 h-6 text-red-600"></i>
                </div>
                <div>
                    <h1 class="text-xl font-black text-slate-800 leading-tight">
                        {{ $isEdit ? 'Master JSI ' . $report->jsi_ref : 'New Master JSI' }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Physical Planning site observation for the generation of the plan view measurements.
                    </p>
                </div>
            </div>
            <a href="{{ $returnUrl }}" class="text-xs font-bold text-slate-400 hover:text-slate-600 px-3 py-2">Cancel</a>
        </div>

        <form id="masterJsiForm" class="space-y-5">
            @csrf
            <input type="hidden" name="subject_type" id="subject_type" value="{{ $subjectType }}">
            <input type="hidden" name="subject_id" id="subject_id" value="{{ $subjectId }}">
            <input type="hidden" name="duplex_stage_id" value="{{ $isEdit ? $report->duplex_stage_id : '' }}">

            {{-- ===== 1. What this inspection is about ===== --}}
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">1 · Category &amp; Parcel Update</h2>
                    <p class="text-xs text-slate-400 mt-0.5">These choose the sheet. The measurement table below follows them.</p>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Category <span class="text-red-500">*</span></label>
                        <select name="category" id="category" required
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            <option value="">Select category</option>
                            @foreach (MasterJsiReport::CATEGORIES as $key => $label)
                                <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Parcel Update Type <span class="text-red-500">*</span></label>
                        <select name="parcel_update_type" id="parcel_update_type" required
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            <option value="">Select type</option>
                            @foreach (MasterJsiPortionTemplates::types() as $type)
                                <option value="{{ $type }}" @selected($parcelUpdateType === $type)>
                                    {{ ucwords(str_replace('_', ' ', $type)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- The file number is what the officer is actually holding, so it is
                         what the form asks for. Picking one through the global selector
                         resolves the parcel-update application behind it, which is what
                         the clearance attaches to — see resolveSubject(). --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">File Number <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <input type="text" name="file_number" id="file_number" value="{{ $value('file_number') }}"
                                placeholder="Select a file number"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            <button type="button" onclick="openFileSelector()"
                                class="shrink-0 px-3 py-2 bg-slate-800 text-white text-xs font-bold rounded-lg hover:bg-slate-900 transition">Select</button>
                        </div>
                        {{-- Says in words whether this inspection will clear anything. An
                             unlinked one is allowed — the site may be visited before the
                             application is captured — but it must not look the same as a
                             linked one. --}}
                        <p id="subjectLinkNote" class="mt-1 text-[10px] text-slate-400">
                            {{ $subjectId
                                ? 'Linked to ' . MasterJsiSubjectResolver::label($subjectType, MasterJsiSubjectResolver::find($subjectType, $subjectId))
                                : 'Pick the file number — the parcel update application behind it is found automatically.' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- ===== 2. Application details ===== --}}
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">2 · Application Details</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- File Number is NOT repeated here: it is asked once, in section 1,
                         because it is what resolves the parcel update this inspection
                         clears. Two boxes for one number is two numbers to disagree. --}}
                    @foreach ([
                        ['file_title', 'File Title', 'text'],
                        ['applicant_name', 'Applicant Name', 'text'],
                        ['inspection_date', 'Inspection Date', 'date'],
                    ] as [$field, $label, $type])
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">{{ $label }}</label>
                        <input type="{{ $type }}" name="{{ $field }}"
                            value="{{ $field === 'inspection_date' && $value($field) ? \Carbon\Carbon::parse($value($field))->format('Y-m-d') : $value($field) }}"
                            @if($type === 'date') max="{{ now()->format('Y-m-d') }}" @endif
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                    </div>
                    @endforeach

                    {{-- The address, narrowest first: Plot Number, District, LGA, then
                         the Location line the three of them sit on. --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Plot Number</label>
                        <input type="text" name="plot_number" id="plot_number" value="{{ $value('plot_number') }}"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                    </div>

                    @php $districtValue = (string) $value('district'); @endphp
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">District</label>
                        <select name="district" id="district"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                            <option value="">Select district</option>
                            @foreach ($districts as $d)
                                <option value="{{ $d->name }}" @selected(strcasecmp($districtValue, (string) $d->name) === 0)>
                                    {{ strtoupper($d->name) }}
                                </option>
                            @endforeach
                            {{-- A district the registry holds but the list does not: the
                                 captured value must survive being re-opened, rather than
                                 silently resetting to blank on the next save. --}}
                            <option value="OTHER" @selected($districtValue !== '' && !$districts->contains(fn ($d) => strcasecmp($districtValue, (string) $d->name) === 0))>OTHER</option>
                        </select>
                        <input type="text" name="district_other" id="district_other"
                            value="{{ $districtValue !== '' && !$districts->contains(fn ($d) => strcasecmp($districtValue, (string) $d->name) === 0) ? $districtValue : '' }}"
                            placeholder="Specify district"
                            class="{{ $districtValue !== '' && !$districts->contains(fn ($d) => strcasecmp($districtValue, (string) $d->name) === 0) ? '' : 'hidden' }} mt-2 w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">LGA</label>
                        <input type="text" name="lga" id="lga" value="{{ $value('lga') }}"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                    </div>

                    {{-- The plot number is NOT repeated in here — it has its own box
                         above, and stating it twice lets the two disagree. --}}
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Location</label>
                        <input type="text" name="location" id="location" value="{{ $value('location') }}"
                            placeholder="Street, area — without the plot number"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Inspection Officer</label>
                        <input type="text" name="inspection_officer" value="{{ $value('inspection_officer') }}"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500">
                    </div>
                </div>
            </div>

            {{-- ===== 3. Site observation ===== --}}
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">3 · Site Observation</h2>
                    <p class="text-xs text-slate-400 mt-0.5" id="narrativeHint"></p>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Available on Ground</label>
                            <select name="available_on_ground" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                <option value="">Select</option>
                                @foreach (['Available', 'Not Available', 'Partially Available'] as $option)
                                    <option value="{{ $option }}" @selected($value('available_on_ground') === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Conforms with surrounding land use</label>
                            <select name="conformity" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                <option value="">Select</option>
                                <option value="1" @selected($value('conformity') === true || $value('conformity') === 1)>Yes — conforms</option>
                                <option value="0" @selected($value('conformity') === false || $value('conformity') === 0)>No — does not conform</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Prevailing Land Use</label>
                            <input type="text" name="prevailing_land_use" value="{{ $value('prevailing_land_use') }}"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>

                    {{-- Boundaries. Four boxes, one sentence: the compiled
                         boundary_description is what is stored, and the shared
                         HasBoundarySegments trait writes it exactly as the OSS JSI
                         sheet does. --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">The site was bounded by</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @foreach (['north' => 'North', 'east' => 'East', 'south' => 'South', 'west' => 'West'] as $dir => $dirLabel)
                            <div>
                                <label class="block text-[11px] font-medium text-slate-500 mb-1">On the {{ $dirLabel }}</label>
                                <input type="text" name="boundary_segments[{{ $dir }}]" value="{{ $segments[$dir] ?? '' }}"
                                    placeholder="What lies on the {{ strtolower($dirLabel) }}"
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Road Reservation</label>
                            <input type="text" name="road_reservation" value="{{ $value('road_reservation') }}"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Number of Units / Subdivisions</label>
                            <input type="number" min="0" name="number_of_units" value="{{ $value('number_of_units') }}"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>

                    {{-- Change of Purpose is the one sheet that states both land uses
                         in words as well as in the table. --}}
                    <div id="landUseFields" class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Existing Land Use / Purpose</label>
                            <input type="text" name="existing_land_use" value="{{ $value('existing_land_use') }}"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Recommended Land Use / Purpose</label>
                            <input type="text" name="recommended_land_use" value="{{ $value('recommended_land_use') }}"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Additional Observations</label>
                        <textarea name="additional_observations" rows="3"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">{{ $value('additional_observations') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- ===== 4. The measurement table ===== --}}
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">4 · Measurements</h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Sides in <strong>metres</strong>. The area is derived where the sides allow it, and the
                            hectare figure follows the square metres.
                        </p>
                    </div>
                    <button type="button" id="addPortionBtn" onclick="addPortionRow()"
                        class="hidden shrink-0 inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-lg hover:bg-slate-200 transition">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add portion
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left w-14">S/N</th>
                                <th class="px-4 py-3 text-left">Portion</th>
                                <th class="px-4 py-3 text-left">Dimension (m)</th>
                                <th class="px-4 py-3 text-left w-64">Measurement (m²/ha)</th>
                            </tr>
                        </thead>
                        <tbody id="portionsBody" class="divide-y divide-slate-100">
                            <tr id="portionsEmpty">
                                <td colspan="4" class="px-4 py-12 text-center text-sm text-slate-400">
                                    Choose a parcel update type to draw its measurement table.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pb-8">
                <a href="{{ $returnUrl }}" class="px-5 py-3 text-sm font-bold text-slate-500 hover:text-slate-700">Cancel</a>
                <button type="submit" id="saveBtn"
                    class="px-7 py-3 bg-red-600 text-white rounded-xl text-sm font-bold shadow-sm shadow-red-600/20 hover:bg-red-700 transition">
                    {{ $isEdit ? 'Save changes' : 'Save Master JSI' }}
                </button>
            </div>
        </form>
    </div>
    </div>

    {{-- The same file-number selector every other register uses. --}}
    @include('components.global-fileno-modal')

    @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
/* ============================================================================
   The five sheets, as the server describes them. The table below is drawn from
   this — nothing here branches per parcel update type.
   ========================================================================== */
const TEMPLATES = @json($jsTemplates);

/* Rows already captured, keyed by role, so an edit refills its own sheet. */
const EXISTING = @json($jsExisting);

const IS_EDIT   = @json($isEdit);
const REPORT_ID = @json($isEdit ? $report->id : null);
const RETURN_URL = @json($returnUrl);

/* ------------------------------------------------------------------ measures
   Lifted from deeds/parcel_update/subdivision.blade.php, which is the engine the
   parcel-update capture already uses: metres in, square metres derived, hectares
   shown alongside. A parcel is a polygon, not always a rectangle, so an area is
   only derived from EXACTLY two sides — anything else asks the officer for the
   figure rather than inventing one.
   ------------------------------------------------------------------------- */
const M2_PER_HECTARE = 10000;
const DERIVABLE_TERMS = 2;

function areaFromTerms(terms) {
    const nums = (terms || []).map(v => parseFloat(v)).filter(v => isFinite(v) && v > 0);
    return nums.length === DERIVABLE_TERMS ? nums[0] * nums[1] : null;
}

/** 2842.3474 -> "0.2842 Ha". Four places: a plot is a fraction of a hectare. */
function hectares(m2) {
    const ha = (Number(m2) || 0) / M2_PER_HECTARE;
    return ha.toLocaleString('en-NG', { minimumFractionDigits: 4, maximumFractionDigits: 4 }) + ' Ha';
}

/** 1410 -> "1,410 m²"; 1410.3 -> "1,410.3 m²". */
function areaText(n) {
    const v = Number(n) || 0;
    return (v === Math.round(v)
        ? v.toLocaleString('en-NG')
        : v.toLocaleString('en-NG', { minimumFractionDigits: 1, maximumFractionDigits: 2 })) + ' m²';
}

/** Always at least one box to type in, and two by default. */
function normaliseTerms(value) {
    const terms = Array.isArray(value) ? value.slice() : [];
    return terms.length ? terms : ['', ''];
}

const esc = v => (v === undefined || v === null) ? '' : String(v).replace(/"/g, '&quot;');

/* --------------------------------------------------------------- the table */
let rowSeq = 0;

function dimBoxes(slot, terms) {
    const box = (t, value) =>
        '<div class="relative">'
        + '<input type="number" step="any" min="0" placeholder="' + (t + 1) + '"'
        + ' aria-label="Side ' + (t + 1) + ' in metres" value="' + esc(value) + '"'
        + ' name="portions[' + slot + '][dimensions][]" data-slot="' + slot + '"'
        + ' oninput="syncPortionArea(' + slot + ')"'
        + ' class="portion-dim w-16 px-1.5 py-2 rounded-lg border border-slate-200 bg-white text-sm text-center">'
        + (terms.length > 1
            ? '<button type="button" onclick="removeDimTerm(' + slot + ',' + t + ')" tabindex="-1"'
              + ' class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-slate-200 text-slate-500 text-[10px] leading-none hover:bg-red-100 hover:text-red-600">&times;</button>'
            : '')
        + '</div>';

    return '<div class="portion-dims flex flex-wrap items-center gap-1.5" data-slot="' + slot + '">'
        + terms.map(box).join('')
        + '<button type="button" onclick="addDimTerm(' + slot + ')" title="One more side"'
        + ' class="w-8 h-8 rounded-lg border border-dashed border-slate-300 text-slate-400 hover:border-slate-400 hover:text-slate-600 transition">+</button>'
        + '</div>';
}

function termsOf(slot) {
    const wrap = document.querySelector('.portion-dims[data-slot="' + slot + '"]');
    return wrap ? [...wrap.querySelectorAll('.portion-dim')].map(el => el.value) : [];
}

window.addDimTerm = function (slot) { redrawDims(slot, termsOf(slot).concat([''])); };

window.removeDimTerm = function (slot, term) {
    const terms = termsOf(slot);
    if (terms.length <= 1) return;   // never down to nothing
    terms.splice(Number(term), 1);
    redrawDims(slot, terms);
};

/** Rebuild the row of boxes rather than splicing, so every x button stays addressed. */
function redrawDims(slot, terms) {
    const cell = document.querySelector('.portion-dim-cell[data-slot="' + slot + '"]');
    if (!cell) return;
    cell.innerHTML = dimBoxes(slot, normaliseTerms(terms));
    syncPortionArea(slot);
}

/**
 * The area for one row.
 *
 * Where the sides give an area it is written in and the box stays read-only; where
 * they cannot, the box is opened for the officer to fill in and whatever is already
 * there is left alone — re-deriving would wipe a figure they had typed.
 */
window.syncPortionArea = function (slot) {
    const input = document.querySelector('.portion-area[data-slot="' + slot + '"]');
    const out   = document.querySelector('.portion-area-out[data-slot="' + slot + '"]');
    if (!input) return;

    const terms = termsOf(slot);
    const derived = areaFromTerms(terms);

    if (derived !== null) {
        input.value = derived;
        input.readOnly = true;
        input.classList.add('bg-slate-50');
    } else {
        input.readOnly = false;
        input.classList.remove('bg-slate-50');
    }

    const m2 = parseFloat(input.value);
    if (out) {
        out.innerHTML = (isFinite(m2) && m2 > 0)
            ? areaText(m2) + ' &middot; <span class="text-emerald-700 font-bold">' + hectares(m2) + '</span>'
            : '';
    }
};

/** One row of the sheet. */
function portionRowHtml(row, slot) {
    const existing = EXISTING[row.role] || {};
    const terms = normaliseTerms(existing.dimensions ? String(existing.dimensions).split(/\s*x\s*/i) : []);

    // A land-use row states a use, not a measurement; a count row states a number.
    const dimCell = row.land_use
        ? '<input type="text" name="portions[' + slot + '][land_use]" value="' + esc(existing.land_use) + '"'
          + ' placeholder="Land use" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">'
        : (row.count
            ? '<input type="number" min="0" name="portions[' + slot + '][unit_count]" value="' + esc(existing.unit_count) + '"'
              + ' placeholder="How many" class="w-40 border border-slate-200 rounded-lg px-3 py-2 text-sm">'
            : dimBoxes(slot, terms));

    const areaCell = row.land_use
        ? '<span class="text-xs text-slate-400">—</span>'
        : '<input type="number" step="any" min="0" name="portions[' + slot + '][area_sqm]"'
          + ' value="' + esc(existing.area_sqm) + '" placeholder="m²" data-slot="' + slot + '"'
          + ' oninput="syncPortionArea(' + slot + ')"'
          + ' class="portion-area w-40 border border-slate-200 rounded-lg px-3 py-2 text-sm">'
          + '<div class="portion-area-out text-[11px] text-slate-500 mt-1" data-slot="' + slot + '"></div>';

    return '<tr class="portion-row ' + (row.derived ? 'is-derived' : '') + '" data-slot="' + slot + '">'
        + '<td class="px-4 py-3 align-top font-black text-slate-500">' + row.sn + '</td>'
        + '<td class="px-4 py-3 align-top">'
        +   '<input type="hidden" name="portions[' + slot + '][role]" value="' + esc(row.role) + '">'
        +   '<input type="hidden" name="portions[' + slot + '][label]" value="' + esc(row.label) + '">'
        +   '<span class="font-bold text-slate-700 text-sm">' + row.label + '</span>'
        +   (row.derived ? '<span class="block text-[10px] font-black text-emerald-600 uppercase tracking-wider mt-0.5">Recommended</span>' : '')
        + '</td>'
        + '<td class="px-4 py-3 align-top portion-dim-cell" data-slot="' + slot + '">' + dimCell + '</td>'
        + '<td class="px-4 py-3 align-top">' + areaCell + '</td>'
        + '</tr>';
}

/** Redraw the whole table for the chosen parcel update type. */
function renderPortions() {
    const type = document.getElementById('parcel_update_type').value;
    const body = document.getElementById('portionsBody');
    const template = TEMPLATES[type];
    const hint = document.getElementById('narrativeHint');
    const addBtn = document.getElementById('addPortionBtn');

    if (!template) {
        body.innerHTML = '<tr><td colspan="4" class="px-4 py-12 text-center text-sm text-slate-400">'
            + 'Choose a parcel update type to draw its measurement table.</td></tr>';
        hint.textContent = '';
        addBtn.classList.add('hidden');
        return;
    }

    hint.textContent = template.narrative;
    rowSeq = 0;
    body.innerHTML = template.rows.map(row => portionRowHtml(row, rowSeq++)).join('');

    // A merger of three or more parcels needs more observation rows than the
    // sample sheet prints.
    addBtn.classList.toggle('hidden', !template.repeatable);

    // Change of Purpose states its two land uses in words as well as in the table.
    document.getElementById('landUseFields').classList.toggle('hidden', type !== 'change_of_purpose');

    template.rows.forEach((row, i) => { if (!row.land_use) syncPortionArea(i); });
    if (window.lucide) lucide.createIcons();
}

/** An extra observation row, for a merger carrying more than two portions. */
window.addPortionRow = function () {
    const type = document.getElementById('parcel_update_type').value;
    const template = TEMPLATES[type];
    if (!template || !template.repeatable) return;

    const body = document.getElementById('portionsBody');
    const slot = rowSeq++;
    const letter = String.fromCharCode(65 + body.querySelectorAll('tr').length - 1);

    // Inserted BEFORE the recommended row: the answer stays at the bottom of the
    // sheet however many portions go into it.
    const derivedRow = body.querySelector('tr.is-derived');
    const html = portionRowHtml({
        sn: body.querySelectorAll('tr').length,
        role: 'portion_' + letter.toLowerCase(),
        label: 'Portion ' + letter,
        land_use: false, count: false, derived: false,
    }, slot);

    if (derivedRow) derivedRow.insertAdjacentHTML('beforebegin', html);
    else body.insertAdjacentHTML('beforeend', html);

    renumberRows();
    syncPortionArea(slot);
};

function renumberRows() {
    document.querySelectorAll('#portionsBody tr').forEach((tr, i) => {
        const cell = tr.querySelector('td');
        if (cell) cell.textContent = i + 1;
    });
}

/* ------------------------------------------------------- category -> type */
/**
 * APU is the duplex and nothing else; SPU is the five single workflows. The type
 * list is not filtered by category — a duplex stage is still a subdivision or a
 * merger — but the record picker is.
 */
// Either choice changes WHICH register the file number is looked up in, so the
// link is resolved again rather than left describing the previous answer.
document.getElementById('category').addEventListener('change', resolveSubject);

document.getElementById('parcel_update_type').addEventListener('change', () => {
    renderPortions();
    resolveSubject();
});

/* ------------------------------------------------------- file number picker */
/**
 * The file number comes from the SAME global selector every other register uses,
 * so an officer picks a file here the way they pick one anywhere else in the
 * system — rather than through a search box that only this page had.
 *
 * Choosing a file then resolves the parcel-update application behind it, which is
 * what the clearance attaches to. That lookup needs the parcel update type, so it
 * is asked for first.
 */
window.openFileSelector = function () {
    if (!window.GlobalFileNoModal) {
        return Swal.fire({ icon: 'error', title: 'File selector unavailable', confirmButtonColor: '#dc2626' });
    }

    GlobalFileNoModal.open({
        callback: function (data) {
            if (!data.fileNumber) return;

            document.getElementById('file_number').value = data.fileNumber;

            // What the registry knows about the file, without overwriting anything
            // already typed — the officer was on the site and the registry was not.
            if (data.record) {
                const name = data.record.file_name || data.record.applicant_name || '';
                setIfEmpty('file_title', name);
                setIfEmpty('applicant_name', name);
                setIfEmpty('plot_number', data.record.plot_no);
                setDistrict(data.record.district || data.record.property_district);
                setIfEmpty('lga', data.record.lga);

                // The plot number has its own box, so it comes off the front of the
                // location rather than being stated twice.
                setIfEmpty('location', locationWithoutPlot(
                    data.record.location || data.record.Location,
                    data.record.plot_no
                ));
            }

            resolveSubject();
        }
    });
};

function setIfEmpty(name, value) {
    const el = document.querySelector(`[name="${name}"]`);
    if (el && !el.value && value) el.value = value;
}

/**
 * The district select, filled from a registry value.
 *
 * A district the registry holds but the list does not falls to OTHER with the name
 * typed in beside it — rather than silently staying blank and being saved as blank.
 */
function setDistrict(value) {
    const select = document.getElementById('district');
    const other  = document.getElementById('district_other');
    if (!select || !value || select.value) return;

    const match = [...select.options].find(
        o => o.value && o.value.toUpperCase() === String(value).trim().toUpperCase()
    );

    if (match) {
        select.value = match.value;
        other.classList.add('hidden');
        other.value = '';
    } else {
        select.value = 'OTHER';
        other.classList.remove('hidden');
        other.value = value;
    }
}

/**
 * "327, KATSINA ROAD, DAKATA" with plot 327 -> "KATSINA ROAD, DAKATA".
 *
 * Mirrors MasterJsiSubjectResolver::locationWithoutPlot(), which does the same for
 * the values the server prefills — the two paths must agree or the same file would
 * read differently depending on how the form was opened.
 *
 * Only a LEADING plot number is removed, and only when it matches: "3271" is not
 * stripped for plot "327", and a road named further along the line is left alone.
 */
function locationWithoutPlot(location, plotNo) {
    const loc  = String(location || '').trim();
    const plot = String(plotNo || '').trim();
    if (!loc || !plot) return loc;

    const escaped = plot.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const re = new RegExp('^\\s*(?:plot\\s*(?:no\\.?|number)?\\s*)?' + escaped + '\\s*[,\\-\\/]?\\s+', 'i');
    const stripped = loc.replace(re, '').trim();

    // A location that was ONLY the plot number is kept as it was, not blanked.
    return stripped || loc;
}

// OTHER reveals the free-text box; any real district hides it again.
document.getElementById('district').addEventListener('change', function () {
    const other = document.getElementById('district_other');
    other.classList.toggle('hidden', this.value !== 'OTHER');
    if (this.value !== 'OTHER') other.value = '';
});

/**
 * Find the parcel-update application this file number belongs to.
 *
 * Not finding one is a normal answer: the site may be inspected before the
 * application is captured. The note says which it is, in words, because an
 * inspection that clears nothing must not look like one that does.
 */
async function resolveSubject() {
    const note = document.getElementById('subjectLinkNote');
    const fileNumber = document.getElementById('file_number').value.trim();
    const category = document.getElementById('category').value;
    const type = document.getElementById('parcel_update_type').value;

    document.getElementById('subject_type').value = '';
    document.getElementById('subject_id').value = '';

    if (!fileNumber) {
        note.textContent = 'Pick the file number — the parcel update application behind it is found automatically.';
        note.className = 'mt-1 text-[10px] text-slate-400';
        return;
    }

    // An APU always resolves against the duplex register; an SPU against the
    // register matching its type.
    const workflow = category === 'APU' ? 'duplex' : type;

    if (!workflow) {
        note.textContent = 'Choose the parcel update type so the application can be found.';
        note.className = 'mt-1 text-[10px] text-amber-600 font-bold';
        return;
    }

    note.textContent = 'Looking up the application…';
    note.className = 'mt-1 text-[10px] text-slate-400';

    try {
        const res = await fetch(
            `/master-jsi/resolve-subject?subject_type=${encodeURIComponent(workflow)}&file_number=${encodeURIComponent(fileNumber)}`,
            { headers: { 'Accept': 'application/json' } }
        );
        const data = await res.json().catch(() => ({}));

        if (data.found) {
            document.getElementById('subject_type').value = data.subject_type;
            document.getElementById('subject_id').value = data.subject_id;
            note.textContent = data.message;
            note.className = 'mt-1 text-[10px] text-emerald-600 font-bold';

            Object.entries(data.prefill || {}).forEach(([field, val]) => {
                // The district is a select with an OTHER escape, so it cannot be
                // filled by assigning the value blindly.
                if (field === 'district') setDistrict(val);
                else setIfEmpty(field, val);
            });
        } else {
            note.textContent = data.message || 'No matching application found. The inspection will be captured unlinked.';
            note.className = 'mt-1 text-[10px] text-amber-600 font-bold';
        }
    } catch (e) {
        note.textContent = 'The application could not be looked up. The inspection will be captured unlinked.';
        note.className = 'mt-1 text-[10px] text-amber-600 font-bold';
    }
}

// A file number typed by hand, or a type chosen after the file, must re-resolve —
// otherwise the note would still describe the previous answer.
document.getElementById('file_number').addEventListener('change', resolveSubject);

/* ------------------------------------------------------------------- submit */
document.getElementById('masterJsiForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = document.getElementById('saveBtn');
    btn.disabled = true;

    const form = e.target;
    const fd = new FormData(form);

    // FormData -> nested object, so portions[0][dimensions][] arrives as an array
    // of rows rather than a flat list of keys.
    const payload = {};
    for (const [key, val] of fd.entries()) {
        if (key === '_token') continue;
        const path = key.replace(/\]/g, '').split('[');
        let node = payload;
        path.forEach((seg, i) => {
            const last = i === path.length - 1;
            if (seg === '') { // a [] array tail
                if (!Array.isArray(node)) return;
                node.push(val);
                return;
            }
            if (last) { node[seg] = val; return; }
            const nextIsArray = path[i + 1] === '';
            if (node[seg] === undefined) node[seg] = nextIsArray ? [] : {};
            node = node[seg];
        });
    }

    // portions arrived keyed by slot number; the server wants a list.
    if (payload.portions && !Array.isArray(payload.portions)) {
        payload.portions = Object.values(payload.portions);
    }

    // OTHER is a prompt, not a district — the typed name is what gets stored, so
    // the record never carries the literal string "OTHER".
    if (payload.district === 'OTHER') {
        payload.district = (payload.district_other || '').trim();
    }
    delete payload.district_other;

    const url = IS_EDIT ? `/master-jsi/${REPORT_ID}` : '/master-jsi';
    const res = await fetch(url, {
        method: IS_EDIT ? 'PUT' : 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
    });

    const data = await res.json().catch(() => ({}));
    btn.disabled = false;

    if (!res.ok) {
        const msg = data.message || (data.errors ? Object.values(data.errors).flat().join('\n') : 'The save was refused.');
        return Swal.fire({ icon: 'error', title: 'Not saved', text: msg, confirmButtonColor: '#dc2626' });
    }

    Swal.fire({ icon: 'success', title: 'Saved', text: data.message, confirmButtonColor: '#dc2626' })
        .then(() => { window.location.href = data.redirect || RETURN_URL; });
});

/* Draw the sheet the page opened on. */
renderPortions();
if (window.lucide) lucide.createIcons();

document.addEventListener('DOMContentLoaded', function () {
    if (window.GlobalFileNoModal) GlobalFileNoModal.init();
});
</script>
@endsection
