# File Log Table — Performance Fix & Production Deployment Guide

The **File Log Table** on the Create File Tracker page is served by
`CreateFileTrackerController@list` (`GET /create-file-tracker/list`). It had
become extremely slow — locally a single page (20 rows) **timed out at over 2
minutes**.

## Root cause

The list query on `file_tracker` itself is cheap (that table is small). The cost
was entirely in **per-row decoration** (`decorateTrackerForResponse`), which ran
an N+1 explosion against the 130k-row `file_indexings` table:

| Per-row call | Before | Why |
|---|---|---|
| `getTempFileLocation` / `getMotherFileLocation` | **~1,400 ms, 44 queries** | Ran the full `FileLocationResolver` (≈22 queries) **twice** to look up a `(T)` counterpart file that almost never exists. |
| `getFileIndexingCreatedAt` | **~46 ms/row** | `WHERE UPPER(file_number) IN (…)` — the `UPPER()` wrapper made the query **non-sargable**, forcing a full scan of `file_indexings` on every row. |

For a 20-row page that was **~900 queries** and multiple full-table scans.

## The fix (application code — already applied)

All in `app/Http/Controllers/CreateFileTrackerController.php`:

1. **Batched counterpart existence check.** `primeRelatedLocationCache()` runs
   **two** indexed queries per page to find which mother/temp counterparts
   actually exist in `file_tracker` / `file_indexings`. The per-row
   mother/temp lookups now skip the heavy `FileLocationResolver` unless a
   counterpart truly exists. Behaviour is identical — the resolver only ever
   returned a location for files present in those tables anyway.
2. **Batched `created_at` lookup.** `primeIndexingCreatedAtCache()` loads the
   "home location" timestamp for the whole page in **one** query.
3. **Sargable queries.** Dropped the `UPPER(…)` / `LTRIM(RTRIM(…))` wrappers on
   `file_number` comparisons against `file_indexings` / `file_tracker`. The
   database collation (`SQL_Latin1_General_CP1_CI_AS`) is already
   case-insensitive and ignores trailing spaces, so plain equality matches the
   same rows **and lets the existing `file_number` index seek** instead of scan.

### Result (local, 20-row page)

| | Queries | Time |
|---|---|---|
| Before | ~900 | timeout (>120 s) |
| After | ~33 | ~1.5 s |

## Indexing

The queries rely on a `file_number` index on both `file_indexings` and
`file_tracker`. These **already exist on local/dev**, so the slowness was the
non-sargable SQL defeating them (fixed above), not a missing index.

A guard migration ships the requirement explicitly for environments where the
indexes might be missing:

```
database/migrations/2026_07_20_120000_ensure_file_log_table_indexes.php
```

It is **idempotent** — it only creates `IX_file_indexings_file_number` /
`IX_file_tracker_file_number` when no equivalent leading-column index already
exists, so re-running is harmless.

## Production deployment steps

1. **Deploy the code** (pull the branch / release containing the controller
   change and the migration).

2. **Run the guard migration** — safe no-op if the indexes already exist:

   ```bash
   php artisan migrate --force \
     --path=database/migrations/2026_07_20_120000_ensure_file_log_table_indexes.php
   ```

   > Run this single migration by `--path` if the full `migrate` batch contains
   > unrelated pending migrations you are not ready to apply.

3. **Rebuild the framework caches:**

   ```bash
   php artisan config:clear   && php artisan config:cache
   php artisan route:clear    && php artisan route:cache
   php artisan view:clear     && php artisan view:cache
   ```

4. **Verify the indexes are present** (SQL Server):

   ```sql
   SELECT t.name AS [table], i.name AS [index], c.name AS [column]
   FROM sys.indexes i
   JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
   JOIN sys.columns c        ON ic.object_id = c.object_id AND ic.column_id = c.column_id
   JOIN sys.tables t         ON t.object_id = i.object_id
   WHERE t.name IN ('file_indexings','file_tracker')
     AND c.name = 'file_number' AND ic.key_ordinal = 1;
   ```

5. **Smoke test:** open the File Log Table and confirm it loads in a couple of
   seconds; page through it and switch module tabs (KANGIS / New KANGIS / DG).

### Post-deploy: update statistics (recommended if the table is large)

If `file_indexings` / `file_tracker` are much bigger in production, refresh
statistics so the optimizer chooses the index seek:

```sql
UPDATE STATISTICS file_indexings WITH FULLSCAN;
UPDATE STATISTICS file_tracker    WITH FULLSCAN;
```

## Known remaining cost (future optimization, not blocking)

The dashboard **stat counts** and the module/tab filters use non-sargable
expressions like `LOWER(LTRIM(RTRIM(ISNULL(module,''))))` and
`PATINDEX(...)` on `file_tracker`. On the current table size this is negligible
(tens of ms), but it scales with row count. If `file_tracker` grows large in
production and the KANGIS/DG views feel slow, the next step is to make those
filters sargable — e.g. persisted computed columns for normalized
`module` / `workflow_type` / `file_number` with supporting indexes. This was
intentionally left out of this change to keep it low-risk.
