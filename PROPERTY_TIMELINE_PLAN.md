# Property Timeline Cross-Module Integration Plan

> **Goal:** Add a cross-table property timeline to the 4 production module pages — Instrument Registration, Property Records (PRA), CofO Records, and File History — so users can see the full transaction history of a property without leaving each module.

---

## 1. What We Already Have

### Existing Timeline (Property Search module)
- **Route:** `GET /property-search/timeline?prop_id={id}&file_number={no}` → `PropertySearchController@timeline`
- **Controller:** `app/Http/Controllers/PropertySearchController.php`
- **View:** `resources/views/property_search/timeline.blade.php`
- **Capability:** Queries all 4 staging/live tables by `prop_id` OR `file_number`, merges results chronologically, renders a full transaction timeline.

### Tables Already Queried
| Table | Source Label | Key Columns |
|---|---|---|
| `file_history_staging` | File History | mlsFNo, fileno, kangisFileNo, NewKANGISFileno, transaction_type, transaction_date, party_1/2/3, prop_id |
| `CofO_staging` | CofO | mlsFNo, fileno, kangisFileNo, transaction_type, transaction_date, prop_id |
| `pra` | PRA | mlsFNo, fileno, kangisFileNo, transaction_type, transaction_date, party_1/2/3, prop_id |
| `deed_registrations` | Deed Registration | fileno, instrument_type (=transaction_type), deeds_date (=transaction_date), grantor, grantee, prop_id |

### Existing Partial in PropertyCard
`PropertyCardController::resolveTimelineCounts()` already counts `file_history_staging` rows by `prop_id` and shows a badge on the PRA list. This is a stepping stone — the full plan expands it to all 4 tables and all 4 modules.

---

## 2. The 4 Target Modules

| # | Module | URL | Controller Method | Source Table | prop_id Present? |
|---|---|---|---|---|---|
| 1 | Property Records (PRA) | `/propertycard` | `PropertyCardController@index` | `pra` | ✅ Yes |
| 2 | CofO Records | `/propertycard/cofo` | `PropertyCardController@cofoIndex` | `CofO_staging` | ✅ Yes |
| 3 | Instrument Registration | `/instrument_registration` | `InstrumentRegistrationController@InstrumentRegistration` | `deed_registrations` | ✅ Yes (set on save via `InstrumentRegistrationService`) |
| 4 | File History | Legal Search or `file_history_staging` API | TBD (see §6) | `file_history_staging` | ✅ Yes |

**Key views involved:**

| Module | List View | Detail/Record View |
|---|---|---|
| PRA | `propertycard/index.blade.php` | `propertycard/partials/view_property_record.blade.php` |
| CofO | `propertycard/cofo.blade.php` | `propertycard/partials/view_cofo_record.blade.php` |
| Instrument Reg. | `instrument_registration/index.blade.php` | `instrument_registration/view.blade.php` |
| File History | (see §6) | (see §6) |

---

## 3. Anatomy of the `prop_id` Link

`prop_id` is a 12-digit integer generated at application intake by `PropertyIdAllocationService`. It is the single cross-table key that ties all 4 tables together.

```
mother_applications.prop_id (original source)
        │
        ├─→  pra.prop_id               (PRA records)
        ├─→  CofO_staging.prop_id      (CofO records)
        ├─→  deed_registrations.prop_id (Instrument registrations)
        └─→  file_history_staging.prop_id (File history)
```

A timeline query fetches all rows across the 4 tables where `prop_id = :id`, merges them chronologically, and displays them as a vertical timeline.

---

## 4. Implementation Approach

### Option A — Link to Existing Timeline Page (simplest, ~1 day)
Add a **"View Full Timeline"** button to each module's detail panel. The button opens `/property-search/timeline?prop_id={prop_id}` in a new tab or full-page navigation.

**Pros:** Zero backend work; reuses existing controller and view.  
**Cons:** Takes user away from the current module; no context continuity.

### Option B — AJAX Timeline Drawer/Modal (recommended, ~2–3 days)
Add a **"Timeline"** button to each module's record detail view. Clicking it opens a side-drawer or modal and loads `/property-search/timeline` as an AJAX partial, filtered by `prop_id`.

**Pros:** User stays in context; clean integration; reuses existing endpoint.  
**Cons:** Requires the existing `PropertySearchController@timeline` to support a JSON/partial response mode (one minor addition).

### Option C — Embedded Timeline Tab (most integrated, ~3–5 days)
Add a dedicated **"Timeline"** tab to each module's record detail view. The tab panel loads inline and is powered by a dedicated AJAX route.

**Pros:** Best UX; most native-feeling integration.  
**Cons:** More frontend work required per module.

> **Recommendation: Option B** (modal/drawer), with `prop_id` passed as query param. The existing `PropertySearchController@timeline` returns a full Blade page; we add a `?mode=partial` flag to return only the inner panel HTML, then each module's JS fetches and injects it into a modal.

---

## 5. Step-by-Step Implementation

### Step 1 — Add Partial Mode to `PropertySearchController@timeline`

**File:** `app/Http/Controllers/PropertySearchController.php`

Modify the `timeline()` method to detect `?mode=partial` and return only the inner timeline panel:

```php
$mode = $request->query('mode', 'full');

if ($mode === 'partial') {
    return view('property_search.timeline_partial', $data);
}
return view('property_search.timeline', $data);
```

**New partial view:** `resources/views/property_search/timeline_partial.blade.php`  
Extract the inner `<div class="timeline-panel">` section from `timeline.blade.php` into this new file, then include it from the full view. No data changes required.

---

### Step 2 — Create a Shared Timeline Modal Partial

**New file:** `resources/views/partials/property_timeline_modal.blade.php`

```html
<!-- Reusable cross-module property timeline modal -->
<div id="propertyTimelineModal" class="modal fade" tabindex="-1" role="dialog" data-size="xl">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-indigo-700 text-white">
                <h5 class="modal-title">
                    <i class="fas fa-history mr-2"></i>
                    Property Transaction Timeline
                    <span id="timelinePropIdBadge" class="badge badge-light ml-2 text-indigo-700" style="font-size:0.75rem;"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">×</button>
            </div>
            <div class="modal-body" id="propertyTimelineBody">
                <div class="py-10 text-center text-gray-400">
                    <i class="fas fa-circle-notch fa-spin fa-2x mb-3"></i>
                    <p>Loading timeline…</p>
                </div>
            </div>
        </div>
    </div>
</div>
```

**Include this partial** in each module's main layout (once near the bottom, before `@endsection`).

---

### Step 3 — Create a Shared Timeline JS Helper

**New file:** `public/js/property-timeline-modal.js`

```javascript
/**
 * property-timeline-modal.js
 * Shared helper for opening the cross-table property timeline modal.
 * Usage: openPropertyTimeline(propId, fileNumber)
 */
function openPropertyTimeline(propId, fileNumber) {
    const modal = $('#propertyTimelineModal');
    const body  = $('#propertyTimelineBody');
    const badge = $('#timelinePropIdBadge');

    if (!propId && !fileNumber) {
        alert('No property identifier available for this record.');
        return;
    }

    badge.text(propId ? 'Prop ID: ' + propId : fileNumber);
    body.html('<div class="py-10 text-center text-gray-400"><i class="fas fa-circle-notch fa-spin fa-2x mb-3"></i><p>Loading timeline…</p></div>');
    modal.modal('show');

    const params = new URLSearchParams();
    if (propId)      params.set('prop_id', propId);
    if (fileNumber)  params.set('file_number', fileNumber);
    params.set('mode', 'partial');

    fetch('/property-search/timeline?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.ok ? r.text() : Promise.reject(r.status))
    .then(html => body.html(html))
    .catch(err => body.html('<div class="alert alert-danger m-3">Failed to load timeline. (' + err + ')</div>'));
}
```

---

### Step 4 — Module 1: Property Records (PRA)

**List view:** `resources/views/propertycard/index.blade.php`

The PRA list already has a timeline count badge from `resolveTimelineCounts()`. Update the badge to count across ALL 4 tables:

- **Backend change (PropertyCardController):**  
  Replace `resolveTimelineCounts()` (which only queries `file_history_staging`) with a version that UNIONs all 4 tables and sums counts per `prop_id`.  
  Return `['file_history_staging' => N, 'CofO_staging' => M, 'pra' => R, 'deed_registrations' => D, 'total' => N+M+R+D]` per prop_id.  
  The badge on the list row should display the `total`.

- **Detail panel:** `propertycard/partials/view_property_record.blade.php`  
  Add a **"View Timeline"** button that calls `openPropertyTimeline(propId, fileNumber)`.  
  The `prop_id` is already available in the record detail; `fileNumber` falls back to `mlsFNo || fileno`.

```html
<button type="button" class="btn btn-outline-indigo btn-sm"
    onclick="openPropertyTimeline('{{ $record->prop_id }}', '{{ $record->mlsFNo ?? $record->fileno }}')">
    <i class="fas fa-history mr-1"></i> View Timeline
</button>
```

Also include the modal partial and JS at the bottom of the PRA index view:
```blade
@include('partials.property_timeline_modal')
<script src="{{ asset('js/property-timeline-modal.js') }}"></script>
```

---

### Step 5 — Module 2: CofO Records

**File:** `resources/views/propertycard/cofo.blade.php`

- Add the same **"View Timeline"** button to each CofO record row or the detail modal.  
  The `prop_id` column exists in `CofO_staging`.
- Add timeline count badge to the list (optional, using the same multi-table count helper from Step 4).
- Include modal partial and `property-timeline-modal.js` at the bottom.

**Controller:** `PropertyCardController@cofoIndex`  
- Pass `prop_id` with each CofO record in the `$cofoRecords` collection (confirm it is already selected in `getCofOData()`; if not, add `prop_id` to the select).

---

### Step 6 — Module 3: Instrument Registration

**File:** `resources/views/instrument_registration/view.blade.php`

- The detail view shows a single registered instrument. Add a **"View Timeline"** button in the header action bar or the property details section.
- `prop_id` is set on `deed_registrations` rows by `InstrumentRegistrationService` (sourced from `mother_applications.prop_id`). Confirm it is passed to the Blade view from `InstrumentRegistrationController@view`.

**Controller change:** `InstrumentRegistrationController@view`  
Ensure `$application->prop_id` (or the equivalent from `deed_registrations`) is available in the view. If not, add the column to the select.

**List view:** `resources/views/instrument_registration/index.blade.php`  
Add a **"Timeline"** action button per row. Since the list currently comes from `mother_applications` / `subapplications` (not `deed_registrations`), fall back on `file_number` when `prop_id` is null.

Include modal partial and `property-timeline-modal.js` at the bottom.

---

### Step 7 — Module 4: File History View

The **File History** module is `file_history_staging`-specific. Based on the route structure there are two candidates:

1. **Legal Search module** (`/legal_search`) — already has a search interface across all 4 tables via `LegalSearchService`. Individual results can link to the timeline.
2. **API/standalone view** (`/api/file-history` → `FileHistoryApiController`) — may have a separate record view.

**Plan for Legal Search:**
- Each search result row already has `prop_id` and `source_table`.
- Add a **"Timeline"** icon/button per result row that calls `openPropertyTimeline(row.prop_id, row.file_number)`.
- No backend changes needed.
- Include modal partial and `property-timeline-modal.js` in `resources/views/legal_search/index.blade.php`.

> If a standalone File History view exists, apply the same pattern: surface `prop_id` from the `file_history_staging` row and attach the modal trigger button.

---

### Step 8 — Backend: Multi-Table Timeline Count Helper

Replace `PropertyCardController::resolveTimelineCounts()` with a shared service or static helper:

**New method in a shared service (or add to `LegalSearchService` / new `PropertyTimelineService`):**

```php
/**
 * Count timeline events per prop_id across all 4 tables.
 * Returns a Collection keyed by prop_id with total event count.
 */
public static function countByPropIds(array $propIds): \Illuminate\Support\Collection
{
    if (empty($propIds)) return collect();

    $conn = DB::connection('sqlsrv');

    $tables = [
        ['file_history_staging', ['mlsFNo','fileno']],
        ['CofO_staging',         ['mlsFNo','fileno']],
        ['pra',                  ['mlsFNo','fileno']],
        ['deed_registrations',   ['fileno']],
    ];

    $counts = collect();
    foreach ($tables as [$table, $cols]) {
        $rows = $conn->table($table)
            ->select('prop_id', DB::raw('COUNT(*) as cnt'))
            ->whereIn('prop_id', $propIds)
            ->groupBy('prop_id')
            ->pluck('cnt', 'prop_id');

        foreach ($rows as $pid => $cnt) {
            $counts[$pid] = ($counts[$pid] ?? 0) + $cnt;
        }
    }

    return $counts;
}
```

Call this from `PropertyCardController@getData` (PRA) and `PropertyCardController@getCofOData` (CofO) to provide count badges on both list views.

---

## 6. File History Module Clarification Needed

Before implementing Step 7, confirm with the user which view is "File History":

| Option | URL | Controller | Notes |
|---|---|---|---|
| A | `/legal_search` | `LegalSearchController` | Searches all 4 tables; likely the one shown in screenshot 4 |
| B | `/api/file-history` | `FileHistoryApiController` | API-only (no Blade view) |
| C | Some other route | TBD | Needs investigation |

> If option A is correct, the Legal Search index results table gets the "View Timeline" button per row — no new routes or controllers needed.

---

## 7. New Files to Create

| File | Purpose |
|---|---|
| `resources/views/property_search/timeline_partial.blade.php` | Inner timeline panel extracted from `timeline.blade.php` for AJAX loading |
| `resources/views/partials/property_timeline_modal.blade.php` | Shared modal shell (include once per page that uses the timeline) |
| `public/js/property-timeline-modal.js` | JS helper: `openPropertyTimeline(propId, fileNumber)` |

---

## 8. Files to Modify

| File | Change |
|---|---|
| `app/Http/Controllers/PropertySearchController.php` | Add `?mode=partial` support to `timeline()` method |
| `app/Http/Controllers/PropertyCardController.php` | Replace `resolveTimelineCounts()` with multi-table count; ensure `prop_id` is in CofO select |
| `resources/views/propertycard/index.blade.php` | Add "View Timeline" button + modal include |
| `resources/views/propertycard/partials/view_property_record.blade.php` | Add "View Timeline" button |
| `resources/views/propertycard/cofo.blade.php` | Add "View Timeline" button + modal include |
| `resources/views/propertycard/partials/view_cofo_record.blade.php` | Add "View Timeline" button |
| `resources/views/instrument_registration/index.blade.php` | Add "Timeline" action button per row + modal include |
| `resources/views/instrument_registration/view.blade.php` | Add "View Timeline" button in action bar |
| `app/Http/Controllers/InstrumentRegistrationController.php` | Ensure `prop_id` is passed to `view.blade.php` |
| `resources/views/legal_search/index.blade.php` (or File History view) | Add "Timeline" button per result row + modal include |

No new routes needed — the existing `/property-search/timeline` endpoint handles all timeline requests.

---

## 9. New Route (Optional Convenience Alias)

To decouple the timeline from the Property Search module conceptually, optionally add this alias in `routes/app3.php`:

```php
// Convenience alias — delegates to existing PropertySearchController
Route::get('/property-timeline', [\App\Http\Controllers\PropertySearchController::class, 'timeline'])
    ->name('property.timeline');
```

This is optional; all modules can use the existing `property-search.timeline` route.

---

## 10. Implementation Order & Priority

| Priority | Step | Effort | Value |
|---|---|---|---|
| 1 | Extract `timeline_partial.blade.php` | 30 min | Unblocks all other steps |
| 2 | Create `property_timeline_modal.blade.php` + `property-timeline-modal.js` | 1 hr | Shared asset, used everywhere |
| 3 | PRA module (Step 4) | 2 hr | Most used module; already has partial timeline infra |
| 4 | CofO Records (Step 5) | 1 hr | Simple, similar to PRA |
| 5 | Instrument Registration (Step 6) | 2 hr | Slightly more complex; prop_id needs confirmation in view |
| 6 | File History / Legal Search (Step 7) | 1 hr | Clarify module first |
| 7 | Multi-table count helper (Step 8) | 1 hr | Polish — improves count accuracy |

**Total estimated effort: ~1–1.5 working days**

---

## 11. Manual Test Script

After each module is wired up, verify:

1. Open the module's list page.
2. Find a record with a known `prop_id` (e.g., one that exists in multiple tables).
3. Click the **"View Timeline"** button.
4. Confirm the modal opens and shows results from ≥ 2 different source tables.
5. Confirm results are sorted oldest → newest.
6. Confirm clicking a record row in the timeline shows the correct dates and parties.
7. Test with a `prop_id` that only exists in 1 table — should show 1 source with a note.
8. Test with a record where `prop_id` is NULL — button should either be hidden or show a graceful message.

---

## 12. Security Notes

- The `timeline()` endpoint is already behind `auth` + `XSS` middleware.
- The new `?mode=partial` flag only changes the view returned — no new permissions needed.
- Ensure `prop_id` values passed to `openPropertyTimeline()` are properly escaped in Blade (`{{ }}` handles this).
- The JS fetch uses `X-Requested-With` header; ensure the endpoint does not rely on CSRF for GET requests (it doesn't — GET endpoints don't need CSRF tokens in Laravel).
