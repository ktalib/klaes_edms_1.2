# Agent Guide — Legal Search Timeline Lifecycle Grouping

**Audience:** an automated agent (or developer) asked to change anything about how the Legal Search timeline
**groups rows into per-file blocks** (Commissioning → Transactions → Decommissioning), the KANGIS alias folding,
the commissioning-date display, the child decommissioning logic, or the "Last Transaction" status field.

**Read the "why" first:** [`../reports/LEGAL_SEARCH_LIFECYCLE_GROUPING_REPORT.md`](../reports/LEGAL_SEARCH_LIFECYCLE_GROUPING_REPORT.md).
This guide is the "how to work on it without breaking it."

---

## 0. The one rule that saves you

> **The backend (`LegalSearchService`) is authoritative for grouping. The frontend mirrors it.**
> Both the on-screen timeline and the printable report consume backend `lifecycle_file_no` tags. Fix the PHP
> first, then make the JS agree. If the on-screen view and the print report disagree, the JS mirror has drifted
> from the PHP — reconcile toward the PHP.

Also, from [`AGENTS.md`](../../AGENTS.md): the `sqlsrv` connection may point at **live production**
(`10.50.1.1`) — **all DB probes must be READ-ONLY** (`SELECT` / tinker only). Never mutate during investigation.

---

## 1. Function map (change here, check there)

### Backend — [`app/Services/LegalSearchService.php`](../../app/Services/LegalSearchService.php)

| Function | Role | Invariant |
|----------|------|-----------|
| `aliasHintsFromDisplay(?string $display)` | Parse `"MAIN (ALIAS)"` → `{normKangis => normMain}` | R1 |
| `tagRowsWithLifecycleFileNo(array $rows, array $aliasHints=[])` | Stamp each row's `lifecycle_file_no`; **locks** hint keys | R1 |
| `isKangisRecertificationRow(array $row)` | Identify recert rows (must not repoint a locked alias) | R1 |
| `extractKangisLifecycleKey(array $row)` | Normalise a row's KANGIS key for alias lookup | R1 |
| `classifyLifecycleEventType(array $row)` | Phase = commissioning / transaction / decommissioning, from **`transaction_type`** | R2 |
| `placeKangisRecertBeforeCofo(array $rows)` | Recert directly above its C of O; drop dup recerts | R2 |
| `dedupeLifecycleRows(array $rows)` | Each event type's winner at **first-occurrence** position | R2 |
| `arrangeLifecycleFileRows(array $rows)` | Order one block: Commissioning → txns → Decommissioning | R2 |
| `resolveCommissioningInfo(?string $fileNumber, ?string $altFileNo=null)` | Commissioning date; **year-only unless in `mls_file_no`** | R3 |
| `groupTimelineByLifecycle($rows, $primaryFileNo, $searchedFileNo, $aliasHints=[])` | Build grouped blocks for the **print report** | R1–R4 |

`search()` calls `tagRowsWithLifecycleFileNo($all, $this->aliasHintsFromDisplay($fileNumberDisplay))` and returns
`lifecycle_meta` (per-file commissioning/decommissioning/temp metadata). `buildPrintReport()` calls
`groupTimelineByLifecycle(... aliasHintsFromDisplay($fileNumberDisplay))`.

### Frontend — [`resources/views/legal_search/js.blade.php`](../../resources/views/legal_search/js.blade.php)

| Function | Mirrors | Invariant |
|----------|---------|-----------|
| `buildKangisAliasMap(rows)` | `aliasHintsFromDisplay` + row rollup | R1 |
| `groupAndSortTimeline(transactions)` | grouping + `resolveLifecycleOwner` | R1–R4 |
| `classifyLifecycleEventType(row)` | phase classifier | R2 |
| `placeKangisRecertBeforeCofo(rows)` | recert-above-CofO | R2 |
| `dedupeLifecycleRows(groupedRows)` | first-occurrence winner | R2 |
| `arrangeLifecycleFileRows(rows)` | order one block | R2 |
| `buildCommissioningTimelineRow()` / `buildLifecycleCommissioningRow(fileNo, rowsForFile)` | Commissioning synthetic rows (`meta.commissioning_date` then year) | R3 |
| `buildDecommissioningTimelineRow()` | Decommissioning synthetic row | R4 |
| `ensureLifecycleSyntheticRows(groupedRows)` | scope decommissioning to searched block | R4 |
| `sortTimelineChronologically(transactions)` | chronological sort; undated → floaters at end | R5 |
| `dedupeTransactionsForTimelineAndReport(transactions)` | `.preferred` / `.excluded` sets | R5 |
| Last Transaction block in `renderFileHistory` (writes `#last-transaction-value`) | R5 logic | R5 |

---

## 2. The five invariants as a checklist

Before you claim a change is done, re-verify each rule you could have touched:

- [ ] **R1 (alias lock):** KANGIS rows of the searched file fold into its block; the searched file's alias is
      seeded from `file_number_display` and never repointed by a stray recert. No phantom bottom KANGIS group.
- [ ] **R2 (phase order):** Commissioning first, Decommissioning last; each Recertification directly above its
      C of O; no duplicate recerts. Classification reads `transaction_type`.
- [ ] **R3 (commissioning date):** file in `mls_file_no` → real date; absent → **year only**. No legacy date leak.
- [ ] **R4 (child decommissioning):** a Decommissioning row appears only for files actually decommissioned; the
      searched file's `is_superseded` global does **not** leak onto other blocks.
- [ ] **R5 (Last Transaction):** deduped set, main block only, admin/parcel/synthetic excluded, **last in
      `sortTimelineChronologically` order** (so an undated later dealing still wins).

---

## 3. Verification recipes (read-only)

Put throwaway scripts in the **project root** (so `require __DIR__.'/vendor/autoload.php'` resolves) and delete
them after. `search()` takes an **array** with key `query`; `buildPrintReport()` takes an array with key
`file_number`.

**Inspect grouping + fields for a file:**
```php
// _probe.php  (run: php _probe.php ; then: rm _probe.php)
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$svc = new App\Services\LegalSearchService();
$res = $svc->search(['query' => 'CON-AG-2014-35']);
foreach ($res['transactions'] as $i => $t) {
  $a = (array)$t;
  printf("%2d type=%-26s tdate=%-14s life=%s\n", $i,
    substr((string)($a['transaction_type'] ?? ''),0,26),
    (string)($a['transaction_date'] ?? '-'),
    (string)($a['lifecycle_file_no'] ?? '-'));
}
```

**Confirm which connection you're hitting** (local vs live — data differs):
```bash
php artisan tinker --execute="echo config('database.connections.sqlsrv.host');"
```

**After any Blade/JS edit, clear compiled views** (WAMP/opcache caches them):
```bash
php artisan view:clear
```

**Lint PHP before finishing:**
```bash
php -l app/Services/LegalSearchService.php
```

---

## 4. Common pitfalls (learned the hard way)

1. **Editing only the JS.** The print report ignores your JS change — it uses PHP `groupTimelineByLifecycle`.
   Symptom: screen right, print wrong (or vice-versa). Fix both, PHP-first.
2. **Classifying phases by `source_table`/`instrument_type`.** Related files carry their
   Commissioning/Decommissioning label in **`transaction_type`**; use `classifyLifecycleEventType`.
3. **Trusting a recert row to name the alias owner.** A KANGIS number can be recerted against several files.
   Only the searched file's `file_number_display` is authoritative; keep hint keys **locked**.
4. **Date-max for "latest" anything.** Undated dealings (mortgage-by-reg-particulars, etc.) exist. Use the
   timeline's own `sortTimelineChronologically` ordering and take the last element instead.
5. **Applying the searched file's `is_superseded` to every block.** It's a per-searched-file flag; other blocks
   use their own `meta.is_decommissioned`.
6. **Leaking a commissioning date for non-KLAES files.** Only `mls_file_no` presence authorises a real date.
7. **Forgetting `php artisan view:clear`.** Your Blade edit won't show until the compiled view is cleared.
8. **Re-implementing the JS dedup in PHP.** The weighting/dedup (`dedupeTransactionsForTimelineAndReport`,
   which fills the on-screen "Excluded / Duplicate Records" panel) lives only in the JS. The **print** honours
   its verdict by receiving the excluded rows' `db:id` keys as the `excluded_keys` query param and dropping them
   in `buildPrintReport()` — do **not** clone the dedup logic server-side. If a duplicate prints that the screen
   crossed out, check that both print handlers still send `excluded_keys` from
   `window._excludedRelatedTransactions` and that `timelineSourceToDbTable` still matches the PHP `labelToDbKey`
   map. Mortgage/release **encumbrance** detection, by contrast, IS mirrored in both (family-word match:
   `mortgage`; `surrender` + `release`) — keep those two in sync.

---

## 5. Where to look when a report says…

| Report | Suspect | Rule |
|--------|---------|------|
| "KANGIS rows are in a group at the bottom" | alias not locked / display string missing | R1 |
| "Recertification shows twice" or "below the CofO" | `dedupeLifecycleRows` / `placeKangisRecertBeforeCofo` | R2 |
| "Commissioning/Decommissioning at the wrong end" | `classifyLifecycleEventType` reading wrong field | R2 |
| "This child file shouldn't be decommissioned" | `ensureLifecycleSyntheticRows` flag scoping | R4 |
| "Commissioning date should be just the year" | `resolveCommissioningInfo` / `mls_file_no` check | R3 |
| "Last Transaction is wrong" | deduped-set + exclusions + chronological-last | R5 |

---

## 6. Related

- **Report (why + verified cases):** [`../reports/LEGAL_SEARCH_LIFECYCLE_GROUPING_REPORT.md`](../reports/LEGAL_SEARCH_LIFECYCLE_GROUPING_REPORT.md)
- **Architecture:** [`../LEGAL_SEARCH_OVERVIEW.md`](../LEGAL_SEARCH_OVERVIEW.md)
- **Weighting/sort spec:** [`KLAES Legal Search Timeline Developer ImplementationFlow.md`](KLAES%20Legal%20Search%20Timeline%20Developer%20ImplementationFlow.md)
- **Recert display:** [`../reports/LEGAL_SEARCH_RECERTIFICATION_UPDATE.md`](../reports/LEGAL_SEARCH_RECERTIFICATION_UPDATE.md)
- **Case files:** [`../reports/kangis-alias/`](../reports/kangis-alias/)
- **Project conventions:** [`../../AGENTS.md`](../../AGENTS.md)
