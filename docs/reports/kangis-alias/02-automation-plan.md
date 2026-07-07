# 02 — Automation Plan: KANGIS → MLS Alias Resolution

> Goal: a **reusable, non-hard-coded** mechanism that treats every KANGIS
> recertification number as an alias of its mother MLS number, for all existing
> and future recertified files. No production data changes at this stage — this
> is a design.

---

## 1. Principle

Do **not** special-case `MLKN 2455`. Derive the mapping generically from the
fact that recertified records **co-locate the MLS number and the KANGIS number
on the same row** (see `01-...analysis.md` §1). Any KANGIS value that appears in
a `kangisFileNo` / `NewKANGISFileno` column next to a non-empty MLS number is,
by definition, an alias of that MLS number.

---

## 2. The canonical mapping source

Two ready-made sources exist — no new table is required. **Production data
(2026-07-07) proved that source (B) must be PRIMARY**, because the co-located
columns are frequently empty (for `MLKN 2455` they are NULL — the KANGIS number
was mis-stored in `mlsFNo`). Use (B) first, (A) as a supplement/confirmation.

### (B) `related_file_number` — PRIMARY source (proven)

`related_file_number` already materialises KANGIS↔MLS links as
`transaction_type = 'KANGIS Recertification'` rows — built by
[create_related_file_number_table.sql](../../database/migrations/manual/create_related_file_number_table.sql).
Confirmed rows for our case: `CON-AG-2014-35 ↔ MLKN 2455` (ids 7807/7810) and
`CON-AG-2014-35 ↔ MLKN 02455` with **`prop_id = 7530`** (id 8328).

```sql
-- KANGIS -> MLS map from recert links. Each link has two endpoints; pick the
-- MLS-formatted one as canonical and the KANGIS-formatted one as the alias.
SELECT
    -- normalized KANGIS key (strip spaces AND leading zeros: MLKN 02455 -> MLKN2455)
    UPPER(REPLACE(REPLACE(LTRIM(RTRIM(k.kangis_no)),' ',''),' ','')) AS kangis_key,
    k.mls_no,
    MAX(k.prop_id) AS prop_id            -- mother prop_id when the link carries one
FROM (
    SELECT
        CASE WHEN file_number   LIKE '%[A-Z][A-Z][A-Z][A-Z]%[0-9]%' AND file_number   NOT LIKE '%-%' THEN file_number   ELSE related_fileno END AS kangis_no,
        CASE WHEN file_number   LIKE '%-%' THEN file_number ELSE related_fileno END AS mls_no,
        prop_id
    FROM dbo.related_file_number WITH (NOLOCK)
    WHERE transaction_type = 'KANGIS Recertification'
) k
GROUP BY UPPER(REPLACE(LTRIM(RTRIM(k.kangis_no)),' ','')), k.mls_no;
```

> The endpoint-classification above is illustrative — in the service, decide the
> MLS vs KANGIS side with the existing `$isKangis()` predicate rather than LIKE
> heuristics.

### (A) Co-located columns — SUPPLEMENTARY (works only for correctly-mapped files)

Union the pairs from every table that stores both numbers **on the same row**.
This catches files that were mapped correctly (KANGIS in `kangisFileNo`), and is
a useful cross-check — but it returns **nothing** for `MLKN 2455` (its columns
are NULL), which is exactly why it can't be the primary source.

```sql
-- Canonical KANGIS -> MLS pairs (read-only; for validation / view definition)
;WITH pairs AS (
    SELECT kangisFileNo AS kangis_no, mlsFNo AS mls_no, prop_id
    FROM   dbo.pra WITH (NOLOCK)
    WHERE  NULLIF(LTRIM(RTRIM(kangisFileNo)),'') IS NOT NULL
      AND  NULLIF(LTRIM(RTRIM(mlsFNo)),'')       IS NOT NULL
    UNION ALL
    SELECT kangisFileNo, mlsFNo, prop_id FROM dbo.CofO_staging WITH (NOLOCK)
    WHERE  NULLIF(LTRIM(RTRIM(kangisFileNo)),'') IS NOT NULL
      AND  NULLIF(LTRIM(RTRIM(mlsFNo)),'')       IS NOT NULL
    UNION ALL
    SELECT kangisFileNo, mlsFNo, prop_id FROM dbo.file_history_staging WITH (NOLOCK)
    WHERE  NULLIF(LTRIM(RTRIM(kangisFileNo)),'') IS NOT NULL
      AND  NULLIF(LTRIM(RTRIM(mlsFNo)),'')       IS NOT NULL
    UNION ALL
    SELECT kangis_file_no, mls_file_no, prop_id FROM dbo.file_indexings WITH (NOLOCK)
    WHERE  deleted_at IS NULL
      AND  NULLIF(LTRIM(RTRIM(kangis_file_no)),'') IS NOT NULL
      AND  NULLIF(LTRIM(RTRIM(mls_file_no)),'')    IS NOT NULL
    UNION ALL
    SELECT kangisFileNo, mlsfNo, NULL FROM dbo.fileNumber WITH (NOLOCK)
    WHERE  NULLIF(LTRIM(RTRIM(kangisFileNo)),'') IS NOT NULL
      AND  NULLIF(LTRIM(RTRIM(mlsfNo)),'')       IS NOT NULL
)
SELECT
    UPPER(REPLACE(LTRIM(RTRIM(kangis_no)),' ','')) AS kangis_key,
    MAX(mls_no)   AS mls_no,          -- deterministic pick; see edge cases
    COUNT(DISTINCT mls_no) AS mls_variants
FROM pairs
GROUP BY UPPER(REPLACE(LTRIM(RTRIM(kangis_no)),' ',''));
```

> The `mls_variants` column is the data-quality canary: any `kangis_key` with
> `mls_variants > 1` is a dirty mapping to review before trusting it. Note that
> for `MLKN 2455` this supplementary query returns **no row** (its
> `kangisFileNo` is NULL) — the recert-link source (B) is what resolves it.

### Merge order in the resolver

For a searched KANGIS number: try (B) `related_file_number` first (it covers
mis-mapped files and can hand back the mother `prop_id` directly, e.g. 7530);
fall back to (A) co-located columns for cleanly-mapped files. Both feed the same
normalized `kangis_key`.

---

## 3. Where the logic should live

**A single Laravel service — the one source of truth — consumed by the search
layer.** Do **not** scatter KANGIS handling across controllers/queries.

```
app/Services/FileNumberAliasResolver.php   (new)
    resolveCanonical(string $searched): AliasResult
    // AliasResult { canonical: string, alias: ?string, propId: ?string }
```

- **Primary consumer:** `LegalSearchService::search()` and
  `::buildPrintReport()` call it once at the top and use `canonical` for all
  lookups, `alias` for display.
- **Secondary consumers (reuse, same service):** the global file-number modal,
  `FileNumberApiController` / `MlsFileNoController`, mobile file search, and any
  "resolve a file number" API. They all get consistent behaviour for free.

### Layering decision

| Layer | Verdict | Rationale |
|---|---|---|
| **Laravel service (recommended)** | ✅ | Single source of truth, testable, cache-able, reusable across web/API/mobile, no schema change. |
| **Search layer only (inline)** | ⚠️ | Fixes search but duplicates logic for every other consumer; drifts over time. |
| **DB view `v_kangis_mls_alias`** | ➕ optional | Nice as the *data* backing the service (query A as a view). Keep the *decision logic* in the service. Requires a (later, approved) `CREATE VIEW`. |
| **Stored procedure** | ❌ | Hard to test/version with the app; splits logic across DB + app. |

**Recommended shape:** service class holds the logic; it reads either the
co-located columns directly (query A) or a read-only view `v_kangis_mls_alias`
built from query A. A short-lived in-request/Redis cache keyed by the normalized
KANGIS string keeps it cheap.

---

## 4. Normalization rules (must be shared)

Reuse the same normalization the service already uses for file numbers
(`norm()` in `LegalSearchService`): uppercase, collapse whitespace, and treat
`"MLKN 2455"`, `"MLKN2455"`, and `"MLKN 02455"` (zero-padding) as equal. The
code already documents this KANGIS zero-padding hazard
([LegalSearchService.php:990, 1049](../../app/Services/LegalSearchService.php)).

---

## 5. Risks & edge cases

| # | Risk / edge case | Mitigation |
|---|---|---|
| 1 | **KANGIS-only orphan** (`fileNumber.mlsfNo = NULL`, e.g. `MLKN 2455` id 107959) | Require the source row to have a **non-empty MLS** number; orphans are excluded by construction. |
| 2 | **One KANGIS → several MLS** (`mls_variants > 1`) | Deterministic pick (e.g. the record with a `prop_id` and the most complete `file_indexings` row); log the conflict; expose in a data-quality report. |
| 3 | **Zero-padding / spacing variants** | Normalize both sides with the shared `norm()` before comparison. |
| 4 | **Different prop_ids** (mother vs KANGIS orphan/child) | Resolve by *number co-location*, not prop_id; never widen the search by the orphan's prop_id. |
| 5 | **Reverse lookups** (search MLS, want KANGIS shown) | The pair table is symmetric; expose `resolveAlias(mls)` too for the `MLS (KANGIS)` display. |
| 6 | **Sibling contamination** (mother has subdivision children) | Already handled by the sibling guard added in `fetchRelatedRecertificationRows` and `isSubdividedUnit`; the resolver must not re-introduce siblings — it only rewrites the *searched* number. |
| 7 | **Performance** | Single indexed lookup or cached map; do not run query A per search — cache it (TTL) or back it with an indexed view. |
| 8 | **False aliases from `NewKANGISFileno`** (points to a *successor*, not self) | Prefer `kangisFileNo` (self) over `NewKANGISFileno` (successor pointer) when both could match; treat `NewKANGISFileno` as lower-priority. |

---

## 6. Testing recommendations

**Golden equivalence test (the acceptance criterion):**
- `search('MLKN 2455')` and `search('CON-AG-2014-35')` must produce the same
  primary number, title, Lon/Lat, location, plot/TP, timeline rows, and printed
  report body.

**Unit tests for `FileNumberAliasResolver`:**
- KANGIS with a co-located MLS → returns MLS canonical + KANGIS alias.
- KANGIS-only orphan → returns the KANGIS value unchanged (no MLS invented).
- Plain MLS search → returns unchanged, `alias = null`.
- Zero-padding / spacing variants resolve identically.
- Conflicting mapping (`mls_variants > 1`) → deterministic, logged.

**Regression tests:**
- Searching the mother directly still lists its children (subdivision).
- Searching a subdivision child does **not** pull siblings (sibling guard).
- A non-recertified MLS file is completely unaffected.

**Data-quality report (ongoing):** run query A periodically; alert on any
`mls_variants > 1` or any `kangisFileNo` present with no MLS partner.
