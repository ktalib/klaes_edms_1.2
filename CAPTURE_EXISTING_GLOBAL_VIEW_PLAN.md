# Capture Existing File – Global View Plan

## Objective

Transform the **Capture an Existing File** page (`/existing-file-numbers`) from showing only captured/migrated MLS file numbers to a **global view of ALL file numbers** in the `fileNumber` table, with a unified "Primary FileNo" column and aggregated "Related FileNo" from three source tables.

---

## Current State

### What it shows now
- Only records where `type IN ('Captured','Migrated','indexing','Indexing','INDEXING','KANGIS GIS')` OR matching `SOURCE`.
- Columns: MLS File No | KANGIS File No | New KANGIS File No | Source | File Name | Plot No | TP No | Location | Captured By | Capture Date | Actions

### Key files
| File | Role |
|------|------|
| `app/Http/Controllers/FileNumberController.php` | `captureIndex()` (stats + view), `getCaptureData()` → `getData()` (DataTable AJAX) |
| `resources/views/generate_fileno/capture_existing.blade.php` | Blade view + DataTable JS |
| `resources/views/components/partials/capture-existing-fileno-modal-html.blade.php` | Capture modal partial |
| `routes/file_numbers.php` | Routes for `/existing-file-numbers` |

---

## Target State – New Columns

| # | Column | Source |
|---|--------|--------|
| 1 | **Primary FileNo** | First non-empty of: `fn.mlsfNo`, `fn.kangisFileNo`, `fn.NewKANGISFileNo`, `fn.st_file_no` |
| 2 | **Related FileNo** | Aggregated (see section below) |
| 3 | **Source** | `mls.source` or `fn.SOURCE` (existing logic) |
| 4 | **File Name** | `fn.FileName` |
| 5 | **TP No** | `fn.tp_no` |
| 6 | **Plot No** | `fn.plot_no` |
| 7 | **LGA** | `COALESCE(fn.lga, mls.lga)` |
| 8 | **Location** | `fn.location` |
| 9 | **Captured By** | `COALESCE(mls.created_by, fn.created_by)` |
| 10 | **Capture Date** | `fn.created_at` |
| 11 | **Actions** | Edit / Delete dropdown (existing) |

### Removed columns (merged into Primary FileNo)
- MLS File No
- KANGIS File No
- New KANGIS File No

These three separate columns are replaced by a single **Primary FileNo** column that shows the first non-empty value. All three values will still be returned in the JSON response for the edit modal and tooltip display.

---

## Related FileNo – Aggregation Strategy

The Related FileNo must be gathered from **three tables** and merged into a de-duplicated, comma-separated display:

### Source 1: `fileNumber.related_fileno`
```sql
SELECT related_fileno FROM fileNumber WHERE id = ?
```
- Direct column on the main table (if it exists).
- Verify column presence at runtime with `Schema::hasColumn` or a simple try/catch.

### Source 2: `dciv_link`
```sql
SELECT related_file_number
FROM dciv_link
WHERE main_file_number = <primary_file_no>
```
- Links a DCIV number to its related file numbers.
- `main_file_number` matches against the primary file number (mlsfNo, kangisFileNo, etc.).

### Source 3: `file_indexing_links`
```sql
SELECT file_number
FROM file_indexing_links
WHERE file_indexing_id IN (
    SELECT id FROM file_indexings WHERE file_number = <primary_file_no>
)
```
- Links are via `file_indexing_id` FK → `file_indexings.id`.
- The `file_indexings` table has a `file_number` column that can be matched to the primary file no.

### Implementation approach – SQL OUTER APPLY (Phase 2 enrichment)

Since related file numbers are only needed for the **page-size rows** (max 20), add to the Phase 2 enrichment query:

```sql
-- Related from dciv_link
OUTER APPLY (
    SELECT STRING_AGG(dl.related_file_number, ', ') AS dciv_related
    FROM dciv_link dl
    WHERE dl.main_file_number IN (fn.mlsfNo, fn.kangisFileNo, fn.NewKANGISFileNo, fn.st_file_no)
) AS dciv_rel

-- Related from file_indexing_links (via file_indexings)
OUTER APPLY (
    SELECT STRING_AGG(fil.file_number, ', ') AS fil_related
    FROM file_indexing_links fil
    WHERE fil.file_indexing_id IN (
        SELECT fi.id FROM file_indexings fi
        WHERE fi.file_number IN (fn.mlsfNo, fn.kangisFileNo, fn.NewKANGISFileNo, fn.st_file_no)
    )
) AS fil_rel
```

In PHP, merge and de-duplicate:
```php
$relatedParts = array_filter([
    $row->related_fileno ?? '',       // from fileNumber table itself
    $row->dciv_related ?? '',         // from dciv_link
    $row->fil_related ?? '',          // from file_indexing_links
]);
$allRelated = [];
foreach ($relatedParts as $part) {
    foreach (explode(',', $part) as $r) {
        $r = trim($r);
        if ($r !== '' && !in_array($r, $allRelated)) {
            $allRelated[] = $r;
        }
    }
}
$relatedFileNo = implode(', ', $allRelated) ?: 'N/A';
```

---

## Implementation Steps

### Step 1: Update `captureIndex()` in FileNumberController

**File:** `app/Http/Controllers/FileNumberController.php`

- Remove the `Captured`/`Migrated` type filter from the `$totalCount` query.
- Show total count of ALL non-deleted file numbers.
- Update stats cards:
  - Keep MLS, KANGIS, New KANGIS counts.
  - Add **Total File Numbers** count as the primary stat.
  - Add **ST File No** count.

### Step 2: Update `getCaptureData()` / `getData()` in FileNumberController

**File:** `app/Http/Controllers/FileNumberController.php`

- Modify `getCaptureData()` to pass a new source flag (e.g., `'All'`) instead of `'Captured'`.
- In `getData()`, add a new branch for `source === 'All'`:
  - Remove the type/source WHERE filter (show all non-deleted records).
  - This means: `WHERE (fn.is_deleted IS NULL OR fn.is_deleted = 0)` only.
- In Phase 2 enrichment SQL, add:
  - `fn.related_fileno` to the SELECT (if column exists).
  - OUTER APPLY for `dciv_link` → `dciv_related`.
  - OUTER APPLY for `file_indexing_links` (via `file_indexings`) → `fil_related`.
- In the formatted data map, add:
  - `'primaryFileNo'` – first non-empty of mlsfNo, kangisFileNo, NewKANGISFileNo, st_file_no.
  - `'relatedFileNo'` – merged from the three sources.
- Update search WHERE to also search `related_fileno`, `st_file_no`.

### Step 3: Update the Blade view DataTable columns

**File:** `resources/views/generate_fileno/capture_existing.blade.php`

- **Table headers:** Replace the three file-no columns with `Primary FileNo` and `Related FileNo`.
- **DataTable JS columns array:** Map to new JSON fields:
  ```js
  columns: [
      { data: 'primaryFileNo', render: ... },   // NEW: unified primary file no
      { data: 'relatedFileNo', render: ... },    // NEW: aggregated related
      { data: 'source', ... },
      { data: 'FileName', ... },
      { data: 'tp_no', ... },
      { data: 'plot_no', ... },
      { data: 'lga', ... },                     // NEW column in table
      { data: 'location', ... },
      { data: 'created_by', ... },
      { data: 'created_at', ... },
      { data: 'action', ... },
  ]
  ```
- Update stats card section to reflect "Global File Registry" branding.

### Step 4: Update stats cards in the Blade view

Replace the three separate MLS/KANGIS/NewKANGIS stat cards with:
1. **Total File Numbers** – all records in fileNumber
2. **MLS File No** – records with non-empty mlsfNo
3. **KANGIS File No** – records with non-empty kangisFileNo
4. **New KANGIS File No** – records with non-empty NewKANGISFileNo
5. *(Optional)* **ST File No** – records with non-empty st_file_no

### Step 5: Update search filtering

In the getData search SQL, expand the search to cover:
```sql
AND (
    fn.kangisFileNo    LIKE ?
    OR fn.NewKANGISFileNo LIKE ?
    OR fn.FileName        LIKE ?
    OR fn.mlsfNo          LIKE ?
    OR fn.st_file_no      LIKE ?
    OR fn.tracking_id     LIKE ?
    OR fn.lga             LIKE ?    -- NEW
    OR fn.location        LIKE ?    -- NEW
    OR fn.plot_no         LIKE ?    -- NEW
    OR fn.tp_no           LIKE ?    -- NEW
)
```

---

## Performance Considerations

1. **Related FileNo lookups are expensive** – only run in Phase 2 (max 20 rows per page).
2. `STRING_AGG` is available in SQL Server 2017+. If the DB is older, use `FOR XML PATH` instead:
   ```sql
   OUTER APPLY (
       SELECT STUFF((
           SELECT ', ' + dl.related_file_number
           FROM dciv_link dl
           WHERE dl.main_file_number IN (fn.mlsfNo, fn.kangisFileNo, fn.NewKANGISFileNo, fn.st_file_no)
           FOR XML PATH(''), TYPE
       ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS dciv_related
   ) AS dciv_rel
   ```
3. **Indexes to check/add:**
   - `dciv_link.main_file_number` – index for JOIN performance.
   - `file_indexing_links.file_indexing_id` – already FK, likely indexed.
   - `file_indexings.file_number` – index for the sub-select.
4. **Cache invalidation** – the total-count cache key must change from `file_numbers_total_v2_Captured` to `file_numbers_total_v2_All`.

---

## Risk Assessment

| Risk | Mitigation |
|------|-----------|
| `related_fileno` column may not exist on `fileNumber` table | Check with `Schema::hasColumn()` at runtime; gracefully skip if missing |
| `STRING_AGG` not available on older SQL Server | Use `FOR XML PATH` fallback (shown above) |
| Removing source filter inflates row count dramatically | Server-side pagination already handles this (Phase 1 is cheap) |
| Existing pages that call `getCaptureData()` might break | `getCaptureData()` currently just delegates to `getData()` with `source = 'Captured'`. Create a new method `getGlobalData()` or add a `mode` parameter |
| Edit/delete modals rely on current JSON shape | Keep all original fields in the JSON response; only change the DataTable column mapping |

---

## Files to Change

| File | Changes |
|------|---------|
| `app/Http/Controllers/FileNumberController.php` | `captureIndex()` stats, `getCaptureData()` source flag, `getData()` new source branch + related-fileno enrichment |
| `resources/views/generate_fileno/capture_existing.blade.php` | Table headers, DataTable columns JS, stats cards, page title/description |
| `routes/file_numbers.php` | No route changes needed (same endpoints) |

---

## Testing Plan

1. **Load page** – verify stats show correct global counts.
2. **DataTable display** – confirm Primary FileNo shows the correct merged value.
3. **Related FileNo** – spot-check records that have dciv_link entries and file_indexing_links entries.
4. **Search** – search by kangisFileNo, mlsfNo, location, LGA, plot_no, tp_no.
5. **Edit modal** – ensure edit still works and populates all three legacy file-no fields.
6. **Delete** – ensure soft-delete still works.
7. **Pagination** – navigate through pages to verify performance.
8. **Performance** – check response times with the new OUTER APPLY queries; target < 2s per page load.
