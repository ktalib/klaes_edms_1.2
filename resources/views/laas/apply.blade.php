@extends('laas.layouts.portal')

@section('title', 'New application — LAAS Portal')

@section('content')
@php
    use App\Support\Laas\SroFormSchema;

    /** Current value for a field: what was posted, else the draft, else blank. */
    $v = fn ($key, $fallback = '') => old($key, $answers[$key] ?? $fallback);

    $label = 'block text-sm font-bold';
@endphp

<div class="mx-auto max-w-3xl">

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--ink);">
                Application for Statutory Right of Occupancy
            </h1>
            <p class="mt-1 text-sm" style="color: var(--ink-soft);">
                Answer every required field, then submit. Your answers are saved as you type.
            </p>
        </div>
        <p id="laas-autosave" class="hidden items-center gap-1.5 text-xs" style="color: var(--ink-soft);">
            <i data-lucide="cloud-check" class="h-3.5 w-3.5" aria-hidden="true"></i><span></span>
        </p>
    </div>

    {{-- ---------- Land type ---------- --}}
    <div class="laas-card mb-6 p-5">
        <p class="laas-eyebrow mb-3" style="color: var(--ink-soft);">Select land type</p>

        <div class="flex flex-wrap gap-2" role="tablist">
            @foreach(SroFormSchema::TYPES as $key => $meta)
                @php $active = $key === $type; @endphp
                <a href="{{ route('laas.apply.form', ['type' => $key]) }}"
                   role="tab" aria-selected="{{ $active ? 'true' : 'false' }}"
                   class="inline-flex items-center gap-2 rounded-lg border-2 px-4 py-2.5 text-sm font-bold transition"
                   style="{{ $active
                        ? 'background: var(--brand); border-color: var(--brand); color: var(--on-brand);'
                        : 'background: transparent; border-color: var(--border); color: var(--ink-soft);' }}">
                    <i data-lucide="{{ $meta['icon'] }}" class="h-4 w-4" aria-hidden="true"></i>
                    {{ $meta['label'] }}
                </a>
            @endforeach
        </div>

        <p class="mt-3 text-xs" style="color: var(--ink-faint);">
            Switching type keeps everything you have already typed.
        </p>
    </div>

    {{-- Statutory banner, mirroring the official paper form --}}
    <div class="mb-6 rounded-xl border-2 px-5 py-3" style="border-color: var(--gold); background: rgba(245,179,1,.08);">
        <p class="laas-eyebrow" style="color: var(--warn-ink);">
            Application for Statutory Right of Occupancy — {{ SroFormSchema::typeLabel($type) }}
        </p>
    </div>

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl border p-4"
             style="border-color: var(--danger); background: rgba(159,18,57,.07);">
            <p class="mb-2 text-sm font-bold" style="color: var(--danger);">Please correct the following:</p>
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li class="text-sm" style="color: var(--danger);">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="laas-apply-form" method="POST" action="{{ route('laas.apply.store') }}"
          enctype="multipart/form-data" class="space-y-6">
        @csrf
        <input type="hidden" name="land_type" value="{{ $type }}">

        @foreach($sections as $section)
            <section class="laas-card p-6">
                <h2 class="mb-1 flex items-center gap-2 text-sm font-extrabold uppercase tracking-widest"
                    style="color: var(--brand);">
                    <i data-lucide="{{ $section['icon'] }}" class="h-4 w-4" aria-hidden="true"></i>
                    {{ $section['title'] }}
                </h2>

                @if(!empty($section['note']))
                    <p class="mb-5 text-xs" style="color: var(--ink-soft);">{{ $section['note'] }}</p>
                @else
                    <div class="mb-5"></div>
                @endif

                <div class="space-y-5">
                    @foreach($section['fields'] as $field)
                        @php $key = $field['key']; @endphp

                        {{-- ---- Address block ---- --}}
                        @if($field['type'] === 'address')
                            <fieldset class="rounded-xl border p-4" style="border-color: var(--border);">
                                <legend class="px-2 text-xs font-bold" style="color: var(--ink-soft);">
                                    {!! $field['label'] !!}
                                </legend>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    @foreach(SroFormSchema::addressParts() as $part => $partLabel)
                                        @php $name = $field['prefix'] . $part; @endphp
                                        <div>
                                            <label for="{{ $name }}" class="block text-xs font-semibold"
                                                   style="color: var(--ink-soft);">{{ $partLabel }}</label>
                                            <input id="{{ $name }}" type="text" name="{{ $name }}"
                                                   value="{{ $v($name) }}" class="laas-input mt-1.5">
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>

                        {{-- ---- Prior allocations (residential) ---- --}}
                        @elseif($field['type'] === 'prev_allocations')
                            @php $rows = json_decode((string) ($answers['prev_allocation_details'] ?? ''), true) ?: []; @endphp
                            <fieldset class="rounded-xl border p-4" style="border-color: var(--border);">
                                <legend class="px-2 text-xs font-bold" style="color: var(--ink-soft);">
                                    {!! $field['label'] !!}
                                </legend>
                                <div class="space-y-4">
                                    @for($i = 1; $i <= SroFormSchema::PREV_ALLOCATION_ROWS; $i++)
                                        @php $row = $rows[$i - 1] ?? []; @endphp
                                        <div class="grid gap-3 sm:grid-cols-3">
                                            <div>
                                                <label for="prev_plot_{{ $i }}" class="block text-[11px] font-semibold"
                                                       style="color: var(--ink-faint);">({{ $i }}) a. Plot No.</label>
                                                <input id="prev_plot_{{ $i }}" type="text" name="prev_plot_{{ $i }}"
                                                       value="{{ old('prev_plot_' . $i, $row['plot_no'] ?? '') }}"
                                                       class="laas-input mt-1.5">
                                            </div>
                                            <div>
                                                <label for="prev_location_{{ $i }}" class="block text-[11px] font-semibold"
                                                       style="color: var(--ink-faint);">b. Location</label>
                                                <input id="prev_location_{{ $i }}" type="text" name="prev_location_{{ $i }}"
                                                       value="{{ old('prev_location_' . $i, $row['location'] ?? '') }}"
                                                       class="laas-input mt-1.5">
                                            </div>
                                            <div>
                                                <label for="prev_cert_{{ $i }}" class="block text-[11px] font-semibold"
                                                       style="color: var(--ink-faint);">c. Cert. of Occupancy No.</label>
                                                <input id="prev_cert_{{ $i }}" type="text" name="prev_cert_{{ $i }}"
                                                       value="{{ old('prev_cert_' . $i, $row['cert_no'] ?? '') }}"
                                                       class="laas-input mt-1.5">
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            </fieldset>

                        {{-- ---- Select ---- --}}
                        @elseif($field['type'] === 'select')
                            <div>
                                <label for="{{ $key }}" class="{{ $label }}" style="color: var(--ink);">
                                    {!! $field['label'] !!}
                                    @if($field['required'] ?? false)<span style="color: var(--danger);">*</span>@endif
                                </label>
                                <select id="{{ $key }}" name="{{ $key }}" class="laas-input mt-2"
                                        @if($field['required'] ?? false) required @endif>
                                    <option value="">-- Select --</option>
                                    @foreach($field['options'] as $option)
                                        <option value="{{ $option }}" @selected($v($key) === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @if(!empty($field['help']))
                                    <p class="mt-1.5 text-xs" style="color: var(--ink-soft);">{{ $field['help'] }}</p>
                                @endif
                            </div>

                        {{-- ---- Textarea ---- --}}
                        @elseif($field['type'] === 'textarea')
                            <div>
                                <label for="{{ $key }}" class="{{ $label }}" style="color: var(--ink);">
                                    {!! $field['label'] !!}
                                    @if($field['required'] ?? false)<span style="color: var(--danger);">*</span>@endif
                                </label>
                                <textarea id="{{ $key }}" name="{{ $key }}" rows="3" class="laas-input mt-2"
                                          @if($field['required'] ?? false) required @endif>{{ $v($key) }}</textarea>
                                @if(!empty($field['help']))
                                    <p class="mt-1.5 text-xs" style="color: var(--ink-soft);">{{ $field['help'] }}</p>
                                @endif
                            </div>

                        {{-- ---- Text / number ---- --}}
                        @else
                            <div>
                                <label for="{{ $key }}" class="{{ $label }}" style="color: var(--ink);">
                                    {!! $field['label'] !!}
                                    @if($field['required'] ?? false)<span style="color: var(--danger);">*</span>@endif
                                </label>
                                <input id="{{ $key }}" name="{{ $key }}" value="{{ $v($key) }}"
                                       type="{{ $field['type'] === 'number' ? 'number' : 'text' }}"
                                       @if($field['type'] === 'number') min="0" @endif
                                       @if($field['required'] ?? false) required @endif
                                       @if(!empty($field['help'])) aria-describedby="{{ $key }}-help" @endif
                                       class="laas-input mt-2">
                                @if(!empty($field['help']))
                                    <p id="{{ $key }}-help" class="mt-1.5 text-xs" style="color: var(--ink-soft);">{{ $field['help'] }}</p>
                                @endif
                            </div>
                        @endif
                    @endforeach

                    {{-- The passport photograph sits beside the applicant's name on
                         the paper form, so it belongs in the first section. --}}
                    @if($loop->first)
                        <div>
                            <label for="passport_photo" class="{{ $label }}" style="color: var(--ink);">
                                Passport photograph
                            </label>
                            <input id="passport_photo" type="file" name="passport_photo"
                                   accept=".jpg,.jpeg,.png" aria-describedby="passport-help"
                                   class="laas-input mt-2 file:mr-3 file:rounded file:border-0 file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                            <p id="passport-help" class="mt-1.5 text-xs" style="color: var(--ink-soft);">
                                JPG or PNG, up to 5&nbsp;MB. You can also add it later from the application page.
                            </p>
                        </div>
                    @endif
                </div>
            </section>
        @endforeach

        <div class="laas-card flex flex-wrap items-center justify-between gap-3 p-5">
            <p class="text-xs" style="color: var(--ink-soft);">
                Other documents can be uploaded from the application page once you have submitted.
            </p>
            <button type="submit" class="laas-btn px-6 py-3 text-sm">
                <i data-lucide="send" class="h-4 w-4" aria-hidden="true"></i> Submit application
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form  = document.getElementById('laas-apply-form');
    var token = document.querySelector('meta[name="csrf-token"]').content;

    var badge     = document.getElementById('laas-autosave');
    var badgeText = badge.querySelector('span');
    var timer = null;
    var dirty = false;

    function showSaved(at) {
        badge.classList.remove('hidden');
        badge.classList.add('inline-flex');
        badgeText.textContent = 'Saved ' + at;
    }

    function save() {
        if (!dirty) return;
        dirty = false;

        var body = new FormData(form);
        body.delete('_token');
        // Files are not part of an autosave; the passport is stored on submit.
        body.delete('passport_photo');

        fetch('{{ route('laas.apply.draft') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (json) { if (json.success) showSaved(json.saved_at); })
        .catch(function () {
            // A failed autosave is not worth interrupting the applicant over —
            // the next keystroke schedules another attempt, and Submit is what
            // actually persists the application.
            dirty = true;
        });
    }

    form.addEventListener('input', function () {
        dirty = true;
        clearTimeout(timer);
        timer = setTimeout(save, 2000);
    });

    // Don't autosave a form the applicant is in the middle of submitting.
    form.addEventListener('submit', function () { clearTimeout(timer); dirty = false; });

    // Switching land type is a plain link, so flush any pending answers first —
    // otherwise the last two seconds of typing would be lost on the hop.
    document.querySelectorAll('[role="tab"]').forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            if (!dirty) return;
            e.preventDefault();
            var href = tab.getAttribute('href');

            var body = new FormData(form);
            body.delete('_token');
            body.delete('passport_photo');

            fetch('{{ route('laas.apply.draft') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            }).finally(function () { window.location.href = href; });
        });
    });
})();
</script>
@endpush
