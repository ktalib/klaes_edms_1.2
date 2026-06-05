# Legal Search — Feature Study Report

_Generated: 2026-06-05_
_Scope: the "Legal Search" subsystem of the KLAES (Kano Land Administration) application._

---

## 1. What Legal Search Is

Legal Search produces an **official property search report** for a given land file. Given a
file number (or party name, location, plot/plan number, etc.), it gathers every recorded
transaction for that property from across the system's historical data, arranges them into a
single chronological **timeline**, detects encumbrances (caveats, active mortgages), and
renders a printable report used "for filing purpose" / "for office use only".

It exists in three delivery modes, all backed by the **same service and search logic**:

| Mode | Controller | Purpose | Watermark |
|------|-----------|---------|-----------|
| **Official** | `LegalSearchController` | Internal filing-purpose report | `FOR OFFICE USE ONLY` |
| **On-Premise (Pay-per-Search)** | `OnPremiseController` (extends the above) | Counter / paid search | `PAY-PER-SEARCH` |
| **Online (SaaS)** | `LegalSearchController::online*` | Public/online self-service | served from `docs/templates/online.html` |

The modes differ only in `pageTitle`, watermark, print-template route, and the document type
recorded by the print manager — see [`OnPremiseController`](app/Http/Controllers/OnPremiseController.php),
which overrides just those properties.

---

## 2. Component Map

### Controllers
- [`LegalSearchController`](app/Http/Controllers/LegalSearchController.php) — the heart of the
  feature. Handles the page, search endpoint, the print report data builder, "cleanup mode"
  data-maintenance endpoints, and comment staging.
- [`OnPremiseController`](app/Http/Controllers/OnPremiseController.php) — thin subclass for the
  pay-per-search variant.
- [`LegalsearchreportsController`](app/Http/Controllers/LegalsearchreportsController.php) —
  DataTables feed for the **search audit log** (who searched what, when, result, printed flag).
- [`LegalSearchTokenController`](app/Http/Controllers/LegalSearchTokenController.php) — issues
  and validates **pre-paid search tokens** (one token per file number, consumed on use). Super
  Admins bypass token checks.

### Service
- [`LegalSearchService`](app/Services/LegalSearchService.php) — all the heavy lifting: querying
  the four source tables, prop_id expansion, normalization, sorting, arrangement persistence,
  file-number pattern logic, and the "cleanup mode" mutations.

### Models
- [`LegalSearchLog`](app/Models/LegalSearchLog.php) — audit row per search (`legal_search_logs`).
- [`LegalSearchToken`](app/Models/LegalSearchToken.php) — pre-paid token (`legal_search_tokens`).

### Views
- [`resources/views/legal_search/index.blade.php`](resources/views/legal_search/index.blade.php) —
  page shell, composes the partials and bootstraps `window.LEGAL_SEARCH_CONTEXT`.
- Partials: `dashboard`, `file-history` (timeline table), `report`, `search-modal`, plus
  `style.blade.php` and `js.blade.php`.
- Print templates (static HTML): `OFFICIAL SEARCH REPORT.html`, `PAY-PER-SEARCH.html`,
  `ONLINE.html` under `resources/views/legal_search/templates/`.

### Routes
Defined in [`routes/app3.php`](routes/app3.php) (the live route file — the `legal_search`
blocks in `web.php` are commented out). Key routes:
- `legal_search.index` / `legalsearch.search` — page + search.
- `legal_search.print.{official,onpremise,online}` — serve print templates.
- `legal_search.print.data` (`reportTemplateData`) — JSON payload that fills the print template.
- Cleanup-mode: `legalsearch.{match,drop,remove,update,getRecord,detectConflicts,
  saveArrangement,getArrangement,transferCaveat,createRecord}`.
- Comments: `legalsearch.{getComments,saveComment,saveCofoComment}`.
- `onpremise.*` and `legalsearchreports.*` groups.
- `legal-search-tokens` group → `LegalSearchTokenController`.

---

## 3. The Data Model — Four Source Tables

A property's history is scattered across four "staging" tables with **inconsistent schemas**.
The service normalizes all of them into a single row shape.

| Source | Table | Notable quirks |
|--------|-------|----------------|
| **File History** | `file_history_staging` | uses `plot_size`, `is_caveated` bit, `serialNo/pageNo/volumeNo`, `districtName` |
| **CofO** | `CofO_staging` | **no** `party_1/2/3`, **no** size, **no** `districtName`; has `transaction_time` (mapped to `reg_time`) |
| **PRA** | `pra` | has `parent_prop_id` (used for mergers), `property_description` preferred for location |
| **Deed Registration** | `deed_registrations` | uses `instrument_type`, `grantor/grantee`, `plot_number`, `size`, `district/lga`, has **no** caveat support |

Normalization (`normalizeRow`) resolves the primary parties from whichever role columns are
populated (`party_1` → Assignor → Mortgagor → Grantor → Surrenderor → Lessor, and the grantee
equivalents), builds a `serial/page/volume` registration string, and tags each row with a
human `source_table` label (`File History`, `CofO`, `PRA`, `Deed Registration`).

Supporting tables:
- `file_indexings` — authoritative file metadata (title, district, LGA, land use, plot, TP no,
  `related_fileno`, `prop_id`, `parent_prop_id`). Preferred source for header fields on the report.
- `related_file_number` — recertification ("orange") rows appended as synthetic timeline entries.
- `caveats` — resolves a numeric caveat id → human caveat number.
- `ls_comment_staging` — manual remarks (ground rent, no-CofO, encumbrance, litigation).
- `legal_search_timeline_arrangements` — saved manual ordering per prop_id.

---

## 4. How a Search Works (`LegalSearchService::search`)

1. **Collect criteria** — file number, guarantor/guarantee names, LGA, district, location,
   plot/plan number, size, caveat flag. If none provided → empty result.
2. **SME guard** — `getSmeAllowedFileNos()` checks `file_indexings.related_fileno` (a JSON array)
   to see if the file is part of a **S**ubdivision/**M**erger/**E**xtension group. If so, the
   search is restricted to that explicit allow-list and **prop_id expansion is bypassed** (to
   avoid pulling unrelated records via shared prop_ids).
3. **Query each of the four tables** with table-aware filters (`applyFilters` knows each table's
   real column names; all comparisons are `UPPER()`-folded; file-number match is exact-trimmed).
4. **prop_id cross-table expansion** (non-SME) — gather every `prop_id` (and `parent_prop_id`
   from active indexing, the `fileNumber` table, and PRA merger rows), then re-query all four
   tables for those prop_ids, excluding already-fetched ids. This is what stitches a property's
   full lineage together even when individual records carry different file numbers.
5. **Append recertification rows** from `related_file_number`.
6. **Subdivided-unit filtering** — if searching a unit (e.g. `COM-2025-4-001`), keep only that
   unit's rows + the mother file's rows, excluding sibling units. ST (Sectional Titling) files
   are explicitly excluded from this logic — they are a separate system.
7. **Sort chronologically** by `sort_date`, then apply any **saved manual arrangement** for the
   prop_id.
8. **Derive header fields** from `file_indexings` (title, district, LGA, land use, plot, TP no)
   and compute an aggregate **size** using source weighting: `CofO(4) > FH(3) > PRA(2) > Deed(1)`.
9. Return `transactions` + header metadata + per-source counts + `total_count`.

### File-number pattern intelligence
`identifyFileNumberType()` and `isSubdividedUnit()` classify inputs:
- `mls` — e.g. `COM-2026-336`, `CON-COM-2026-336`
- `kangis` — e.g. `ABCD 1234`
- `new_kangis` — e.g. `KN1234`
- `st` / `parent` — Sectional Titling unit / parent
- subdivided unit detection distinguishes `COM-2025-4-001` (unit) from `CON-COM-2026-302`
  (region-prefixed MLS) by checking whether the third segment is a 4-digit year.

---

## 5. Building the Printable Report (`reportTemplateData`)

This ~850-line method is the most intricate part of the feature. It takes `file_number` and/or
`prop_id` (plus optional client-supplied display overrides and a `timeline_order`) and produces
the JSON the print template renders. Highlights:

- **prop_id filtering** — when a `prop_id` is given, rows are restricted to that prop_id group
  (including merger `parent_prop_id` members), but rows explicitly listed in `timeline_order`
  are always kept so the print matches the on-screen Timeline exactly.
- **Dedup of FH ↔ PRA duplicates** — the same instrument can appear in both File History and
  PRA. A canonical key (`recordKey`) is built primarily from **registration particulars**
  (serial/page/volume) — chosen because PRA stores the deed execution date while FH stores the
  registration date, so date-based keys never matched. A party+date fallback is used when reg
  particulars are absent (e.g. RoFO).
- **Tie-breaking** uses two scores: a **source base score** (PRA/Deed/CofO = 5, FH = 2.5) and a
  **richness score** (FH/PRA only — 2 pts each for parties, reg particulars, transaction date,
  reg time, reg date; max 10). Richer row wins; ties fall to higher source score.
- **Instrument-type canonicalization** (`canonicalTransactionType`) collapses spelling/format
  variants — e.g. "R of O", "ROFO", "Statutory Right of Occupancy" → `right of occupancy`;
  mortgage variants → `deed of mortgage`; surrender/release variants → one key; POA variants → one.
- **Priority + chronological sort** — Rule B weights: `Occupancy Permit (10) > Transfer of Title
  (9) > Right of Occupancy (8) > everything else incl. CofO (5)`. Within the same weight, rows
  sort chronologically by reg/deed/transaction date-time.
- **Caveat / encumbrance detection**:
  - DB caveat flag (`is_caveated`) → resolves `caveat_number` from the `caveats` table.
  - **Mortgage caveat** — a Deed of Mortgage with no later Deed of Surrender & Release marks the
    property as under an active mortgage.
  - Produces a `caveat_note` covering: active caveat, active mortgage, both, "Letter of Grant
    stage / no CofO yet", or "free from encumbrances".
- **Manual comments** from `ls_comment_staging` (ground rent w/ amount, no-CofO, encumbrance,
  litigation) are folded in.
- **Display overrides** — the client may pass `display_*` query params that override the computed
  header fields (file number, title, district/LGA, land use, size, plot, TP no).
- **Verification hash** — a 9-char HMAC (`sha256` over file number + timestamp + app key) is
  emitted as `qr_data` for report verification.
- File-number display logic deliberately preserves **what the user actually searched** (MLS vs
  KANGIS), only appending a related MLS number in parentheses when the search was by KANGIS.

---

## 6. Cleanup Mode (Data Maintenance)

Because the source data is messy, the UI exposes an in-place "cleanup mode" backed by these
endpoints (all whitelist-validated against the four tables and editable-column lists in the
service):

| Endpoint | Action |
|----------|--------|
| `match` | Assign orphan record(s) to a `prop_id` group |
| `drop` | Unlink record(s) from a prop_id (set `prop_id = NULL`) |
| `remove` | Soft-delete record(s) (`is_deleted = 1`) |
| `update` | Edit whitelisted fields on one record |
| `getRecord` | Fetch a single record for editing |
| `detectConflicts` | Report distinct prop_ids across a selection (multi-prop_id warning) |
| `saveArrangement` / `getArrangement` | Persist / load manual timeline order per prop_id |
| `transferCaveat` | Move caveat fields from one PRA/CofO record to another (transactional) |
| `createRecord` | Add a new PRA (via `PropertyRecordController::store`) or CofO record |

**Security note:** table names and column names are validated against hard-coded whitelists
(`VALID_TABLES`, `EDITABLE_COLUMNS`) before any mutation, and raw SQL filters use bound
parameters — a deliberate guard given the dynamic table/column handling.

---

## 7. Access Control & Auditing

- **Tokens** (`LegalSearchToken`): a token is issued per file number with payment metadata
  (receipt, amount, reason, date). `checkAvailableToken` finds an unused token for the file;
  `useToken` consumes it. **Super Admins bypass** the token requirement. Token generation, use,
  and deletion are all written to the audit service.
- **Search log** (`LegalSearchLog`): every meaningful search is logged with the user, the
  search parameter/value, result status (Found/Not Found), result count, LGA, an optional
  receipt/token, the printed flag, and a `direct_link` that reproduces the search via query
  params. `LegalsearchreportsController::data` serves this as a DataTable.

---

## 8. Request / Data Flow (End to End)

```
User → legal_search.index (Blade page)
     → JS posts criteria to legalsearch.search
          → LegalSearchController::search
               → LegalSearchService::search   (4 tables + prop_id expansion + sort)
               → logs LegalSearchLog
          ← JSON { transactions[], header fields, counts }
     → Timeline table rendered (file-history partial), user may run Cleanup Mode actions
     → User prints → opens print template (official/onpremise/online HTML)
          → template fetches legal_search.print.data (reportTemplateData)
               → dedupe + canonicalize + priority sort + caveat detection + comments
          ← JSON { data: { header, rows[], caveat_note, qr_data, ... } }
     → Print template fills in and renders the official search report
```

---

## 9. Observations & Notable Design Decisions

- **Single source of truth for logic, three skins.** The Official/On-Premise/Online split is
  pure configuration on top of one controller+service — low duplication.
- **Schema heterogeneity is handled in SQL select-lists**, not in the DB. Every query aliases
  divergent column names into a common shape; this keeps `normalizeRow` simple but means the
  big select-maps are duplicated between `search*` methods and `searchByPropIds`.
- **prop_id is the real join key**, not file number. Much of the complexity (SME guard,
  expansion, subdivided-unit filtering) exists to get prop_id grouping right without over- or
  under-pulling records.
- **Dedup/weighting rules are business-critical and well-commented** — the FH↔PRA reg-particulars
  key and the richness scoring encode hard-won domain knowledge (PRA = execution date,
  FH = registration date). Related design docs exist in the repo:
  `FH_PRA_WEIGHTING_UPDATE_PLAN.md`, `LEGAL_SEARCH_CLIENT_REQUIREMENTS.md`,
  `LEGAL_SEARCH_COMPREHENSIVE_REPORT.md`.
- **Routing lives in `app3.php`, not `web.php`** (where the legal_search routes are commented
  out) — worth knowing when tracing or adding routes.

### Potential follow-ups (not bugs, just candidates for review)
- The four large per-table select-maps are duplicated between the initial search methods and
  `searchByPropIds`; consolidating them would reduce drift risk.
- `search()` and `reportTemplateData()` each independently compute size via source weighting —
  the weighting tables are repeated.
- `reportTemplateData()` is very long; the dedup/sort/caveat blocks are good candidates for
  extraction into the service for testability.
- `receipt_no => $request->input('token')` in `search()` is annotated "Maybe token is sent?" —
  worth confirming the client actually sends it.

---

---

## 10. The Timeline Table (Front-End)

The Timeline is the central UI artifact — a single combined, chronological table of every
transaction for the property, rendered client-side from the search JSON. It lives in
[`partials/file-history.blade.php`](resources/views/legal_search/partials/file-history.blade.php)
and is driven by [`js.blade.php`](resources/views/legal_search/js.blade.php) (~4,800 lines).

### Layout & columns
The page has three layers of tables, all fed from the same `searchResults` array:

1. **Per-source tabs** ("Multi-Source Data Aggregation Model" — billed as a "blockchain concept"):
   - **File History** (`transaction-history-tab`), **Property Record** (PRA), **Deeds Registration**
     (deed_registrations), **CofO**. Each tab shows that one source's rows with **RW** (record
     weight / richness) and **TW** (table/source weight) columns, and a colored dedup dot
     (green = unique, amber = preferred, red = duplicate).
2. **Timeline table** (`#timeline-table`) — the merged, deduped, priority-sorted view. Columns:
   `S/N · File No · Source · Weight · Instrument Type · Party 1/2/3 · Reg Particulars ·
   Transaction Date · Reg Time · Reg Date · Size · Caveat · Comments · Actions`, plus two
   normally-hidden columns: a checkbox (Cleanup mode) and a `#` order box (Arrange mode).
3. **Excluded / Duplicate Records table** (`#excluded-table`) — rows bypassed by weighting or
   manually dropped, grayed out, with a **Promote** action to force one back into the timeline.

### Render pipeline (JS)
```
searchResults (API JSON)
  └─ renderFileHistory()
       ├─ populates File Information panel (prefers file_indexings _file_* fields)
       └─ renderTransactionTables()
            ├─ dedupeTransactionsForTimelineAndReport()  → { preferred, excluded }
            │     stored in window._preferredRelatedTransactions / _excludedRelatedTransactions
            ├─ per-source tabs: dedupeWithinSource() + sortTimelineChronologically()
            └─ renderTimeline()  → builds #timeline-table from _preferredRelatedTransactions
```

Each source carries a `source_table` label (`File History`, `PRA`, `CofO`, `Deed Registration`,
and the synthetic `Related Fileno`), which drives row tint and badge classes
(`sourceBadgeClass` / `sourceRowTintClass`).

### Interactive modes on the Timeline
- **Cleanup mode** (toggle) — reveals row checkboxes and Match / Drop / Demote / Remove / Edit
  buttons, all hitting the `legalsearch.*` endpoints. `detectConflicts` warns before mixing
  prop_id groups.
- **Arrange mode** — drag/number rows to a custom order, persisted via `saveArrangement`
  (`legal_search_timeline_arrangements`); the saved order is re-applied server-side by
  `applyArrangementOrder()` and client-side, and is also sent to the print as `timeline_order`.
- **Weighing** button — opens the transparency panel (see §12).
- **Manual overrides** — `window._manualDroppedIds` / `_manualIncludedIds` (keyed by
  `source_table::id`) let a user force-exclude or force-include a row, taking precedence over the
  automatic dedup.

---

## 11. The Field-Mapping Layer

Because the same logical field lives under different column names across sources (and across the
many other modules whose records can appear), the front-end resolves values through a single
`getMappedValue(item, fieldType)` function. It walks an **ordered candidate list per field type**
and returns the first non-empty value (stripping trailing `.0`).

Representative mappings (from `js.blade.php`):

| `fieldType` | Candidate columns (in priority order) |
|-------------|----------------------------------------|
| `transactionType` | `transaction_type`, `instrument_type`, `title_type`, `typeForm`, `landUseType`, `application_status`, `deeds_status`, `land_use`, … |
| `grantor` (Party 1) | `Assignor`, `Grantor`, `Mortgagor`, `Lessor`, `Surrenderor`, `owner_fullname`, `corporate_name`, … |
| `grantee` (Party 2) | `Assignee`, `Grantee`, `Mortgagee`, `Lessee`, `Surrenderee`, `sub_owner_fullname`, … |
| `serialNo` / `pageNo` / `volumeNo` | `serialNo`/`serial_no`, `pageNo`/`page_no`, `volumeNo`/`volume_no` (+ legacy `oldTitle*`) |
| `size` | `size`, `plot_size`, `NoOfUnits`, `NoOfSections`, `NoOfBlocks` |
| `lga` | `property_lga`, `address_lga`, `lga`, `lgaName`, `lgsaOrCity` |
| `district` | `property_district`, `address_district`, `district`, `districtName` |
| `regDate` / `regTime` | `deeds_date`/`reg_date`/`transaction_date` · `deeds_time`/`reg_time`/`transaction_time` |
| `plotNo` | `plot_no`, `plotNo`, `plotNumber`, `property_plot_no`, `scheme_no` |

This mirrors the **server-side** normalization in `LegalSearchService::normalizeRow()`
(party resolution `party_1 → Assignor → Mortgagor → Grantor → Surrenderor → Lessor`, the
serial/page/volume registration string, etc.). In effect there are two normalization layers:
the SQL select-list aliasing + `normalizeRow` on the server, and `getMappedValue` on the client
to absorb anything still divergent.

**File-number mapping** is a sub-system of its own: `extractFileNumbers()` + `identifyFileNumberType()`
classify and slot raw values into five buckets — `st`, `parent` (NP), `mls`, `kangis`,
`new_kangis` — by regex pattern, preferring backend-computed aliases, then known column names,
then pattern-scanning everything as a last resort. `parseRelatedFilenoValue()` unpacks the
`['CON-COM-2014-82']`-style stored arrays.

---

## 12. The Weighting System (the "Weighing" feature)

Weighting is the rule engine that decides, when the same property data appears multiple times,
**which copy is authoritative** and **what order rows appear in**. There are three distinct
weights, surfaced in the transparency panel as Record-to-Record, Table-to-Table, and Timeline.
The logic is implemented **identically on client and server** (the server copy in
`LegalSearchController::reportTemplateData` governs the printed report).

### Rule A.1 — Table/Source weight (`getTableSourceWeight`, "TW")
Which source is trusted when two sources hold the same record:

| Source | Weight |
|--------|--------|
| PRA | 5 |
| Deed Registration | 5 |
| CofO | 5 |
| **File History** | **2.5** |

File History is deliberately the lowest because it is the oldest/most error-prone capture.

### Rule A.2 — Record-to-Record richness (`recordRichnessScore`, "RW")
A **completeness** score, applied only to FH/PRA to break ties. Five categories × 2 points
(max 10):
1. Parties — any of Party 1/2/3 present
2. Reg Particulars — any of serial/page/volume present (and non-zero)
3. Transaction Date present
4. Reg Time present
5. Reg Date present

### Deduplication algorithm (`dedupeTransactionsForTimelineAndReport`)
1. Apply manual overrides first (`_manualDroppedIds` → excluded, `_manualIncludedIds` → preferred).
2. Compute a **fingerprint** (`recordKey`) per row:
   - **Primary key**: `reg|<canonical instrument>|<serial>/<page>/<volume>` — registration
     particulars are source-neutral and authoritative. (This solves the core problem: PRA stores
     the **deed execution date** while FH stores the **registration date** for the *same*
     instrument, so any date-based key failed to match them.)
   - **Fallback key** (no reg particulars, e.g. RoFO): `instrument|party1|party2|party3|party4|date`,
     with date dropped for Right of Occupancy.
   - Instrument types are canonicalized (`canonicalInstrumentType`) so "Deed Of Assignment" vs
     "Assignment", "R of O"/"ROFO"/"Statutory Right of Occupancy", mortgage variants, POA
     variants, etc. collapse to one key.
3. For each fingerprint group, the **winner** is: higher richness (RW); on a tie, higher source
   base score (PRA/Deed/CofO = 5, FH = 2.5). The loser goes to the Excluded table.
4. Per-source tabs additionally collapse identical fingerprints **within** their own source
   (`dedupeWithinSource`), so four identical FH rows show as one on the FH tab.

### Rule B — Timeline priority weight (`recordPriorityWeight`, the "Weight" column)
Controls timeline **order**, independent of dedup:

| Instrument | Weight |
|-----------|--------|
| Occupancy Permit (OP) | 10 |
| Transfer of Title (TOT) | 9 |
| Right of Occupancy (RoFO) | 8 |
| CofO + everything else | 1 |

`sortTimelineChronologically` sorts by priority weight **descending** first (so the
grant-lineage instruments lead), then **chronologically ascending** within the same weight, then
by id. Notably, `getTransactionTimestamp` picks the date field differently by weight: priority
instruments prefer `transaction_date`; weight-1 records prefer `reg_date`.

### Transparency panel (`renderWeightingTable`)
The "Weighing" section renders `window._weightingData`: one row per record with its fingerprint,
source, Record-to-Record score, Table-to-Table score, Timeline weight, status
(Preferred / Duplicate / Unique), and an instrument/date summary — so an officer can audit
exactly why a given record was kept or dropped.

### Why it matters
The whole subsystem exists so the **printed official report** shows one clean, correctly-ordered
row per real-world transaction, drawn from the most complete and trustworthy source — even though
the underlying data is duplicated and inconsistent across four tables. The client computes it for
the on-screen Timeline; `reportTemplateData` re-computes the same rules server-side (and honors
the client's `timeline_order`) so the print exactly mirrors the screen.

---

---

## 13. The PHS Portal — Institutional SaaS Channel

Beyond the three in-house modes (Official / On-Premise / Online), the repo carries **prototype
front-ends for a fourth, premium channel**: the **Property History Search (PHS) Portal**, a
Software-as-a-Service platform aimed at **financial and legal institutions** (banks, law firms,
corporates) needing large-scale land-record access for due diligence, credit assessment, and
mortgage processing — including institutions **outside Kano State**.

These live as standalone HTML mockups under [`docs/templates/LS/`](docs/templates/LS/):

| File | Role | ~Lines |
|------|------|--------|
| [`online-legal-search-saas.html`](docs/templates/LS/online-legal-search-saas.html) | The institutional **end-user portal** (landing → sign-in/register → dashboard → search → print slip) | ~1,750 |
| [`online-legal-search-saas-user.html`](docs/templates/LS/online-legal-search-saas-user.html) | The **Organization / User Management console** (the "Manage Organization" / `admin.html` target) | ~1,156 |

> **Status:** Both are **self-contained static prototypes** — Tailwind via CDN, Lucide icons,
> `localStorage` for state, hard-coded `sampleFiles` / `demoAccounts`, and `alert()`-based payment
> flows. They are **not yet wired** to `LegalSearchService` or any backend route. They define the
> intended product/UX; the production wiring would reuse the existing search engine (§4) and the
> token model already implemented in `LegalSearchToken` (§7).

### 13.1 The institutional portal (`online-legal-search-saas.html`)
Branded **"Kano State Ministry of Land and Physical Planning — Property History Search (PHS)
Portal"**. A single-file SPA that swaps between `.page` divs:

- **Landing page** — hero, image slider, value props (Token-Based / Official & Secure / Instant),
  pricing, footer. Footer lists "API Documentation" — i.e. **API integration is advertised as a
  planned feature**.
- **Sign-in / Register** — institution-level accounts (Institution Type = Bank / Law Firm /
  Corporate). Demo accounts: *First Bank of Nigeria* and *Templars Legal* (`demo123`).
- **Dashboard** — per-organization branding (logo/banner/colors pulled from `localStorage`
  `klaes_organizations`), a **token balance** widget, the search box, results cards, and a
  **file-details "Legal Search Slip"** with a **Transaction Timeline** (the SaaS analog of the
  internal Timeline, §10 — here simplified to date/type/from/to/description steps).
- **Token economy** — *each search consumes 1 token*. Purchase modal offers three packages:
  **Starter** ₦50,000 / 2,000 tokens · **Professional** ₦100,000 / 5,000 (POPULAR) ·
  **Enterprise** ₦180,000 / 10,000. Payment is **Pay Online** or **Request Invoice**. New
  registrations are credited 100 bonus tokens. This is the **annual-subscription / token-based
  access model** described for PHS.
- **Print slip** — `generatePrintSlip()` renders a certified "OFFICIAL SEARCH SLIP" in a hidden
  iframe (dual ministry logos, property info, search details incl. Token ID & tokens consumed,
  timeline, signature, verify-URL footer) — conceptually the SaaS counterpart to the internal
  `templates/*.html` print templates (§1, §5).

Core JS: `performSearch()` → `deductToken()` → `searchFiles()` (in-memory filter) →
`renderSearchResults()` → `selectFile()` → timeline render. `applyOrganizationBranding()` is the
multi-tenant theming layer.

### 13.2 The organization console (`online-legal-search-saas-user.html`)
The self-service admin surface a subscribing institution's Super Admin uses to run their tenant.
Tabs:

- **Team Members** — list/add/manage members (name, email, job title, department, tokens used,
  status); "Add New Team Member" modal.
- **Roles & Permissions** — two roles:
  - **Super Administrator (Full Access):** full platform management, user & role management,
    token allocation & billing, branding & organization settings.
  - **Regular User (Limited):** perform searches, view personal history, download own documents;
    **cannot** manage users or change branding.
  - Plus a finer **Access Role** on each member (`search_only`, `report_viewer`,
    `analytics_viewer`).
- **Activity Log** — recent member activity.
- **Branding** — org name, primary/secondary colors, logo & banner upload, with a **live
  preview**; this is what feeds the portal's `applyOrganizationBranding()` (shared via the
  `klaes_organizations` localStorage key, demonstrating the multi-tenant intent).

### 13.3 How PHS relates to the rest of the Legal Search module
PHS is positioned as the **top tier** of the same Legal Search capability:

```
            depth / scale / access level
  Official (internal filing)        — staff, no payment, "FOR OFFICE USE ONLY"
  On-Premise (Pay-per-Search)       — counter, per-file token (LegalSearchToken)
  Online (public self-service)      — docs/templates/online.html
  ▶ PHS Portal (institutional SaaS) — subscription + token wallet, multi-tenant,
                                       bulk + API (planned), in/out of state
```

It reuses the module's central ideas — **token-gated access** (already real in
`LegalSearchToken`/`LegalSearchTokenController`, §7), a **transaction timeline + certified print
slip** (§5, §10), and **official government records** — but layers on SaaS concerns the internal
tool doesn't have: institution accounts, a prepaid **token wallet** with subscription packages,
**per-tenant branding**, **team/role management**, and **bulk + API** access for high-volume
institutional clients. Productionizing it would mean backing these mockups with the real
`LegalSearchService` search (§4) and persisting tenants/wallets/members rather than `localStorage`.

---

_This report describes the code as of the current working tree; it does not modify any code._
