Read [](file:///c%3A/wamp64/www/klaes/app/Http/Controllers/PropertySearchController.php#1-1), lines 1 to 120

Read [](file:///c%3A/wamp64/www/klaes/app/Http/Controllers/PropertySearchController.php#200-200), lines 200 to 380

Read [](file:///c%3A/wamp64/www/klaes/app/Http/Controllers/PropertySearchController.php#380-380), lines 380 to 480

Here's exactly how `prop_id` works in this module:

---

## What is `prop_id`?

`prop_id` is a **12-digit property identifier** stored as a column in all 4 staging tables — `file_history_staging`, `CofO_staging`, `pra`, and `deed_registrations`. It represents a single physical land parcel and is the canonical cross-table link allocated by `PropertyIdAllocationService`.

---

## How it links the 4 tables

The UNION ALL query in `buildUnionQuery()` casts `prop_id` to `NVARCHAR(50)` under the alias `prop_id_raw` from every table:

```sql
CAST(prop_id AS NVARCHAR(50)) AS prop_id_raw   -- same in all 4 tables
```

Then a **`group_key`** expression is computed on top of the union:

```sql
CASE
  WHEN prop_id_raw IS NOT NULL AND LTRIM(RTRIM(prop_id_raw)) <> ''
  THEN prop_id_raw          ← use prop_id if it exists
  ELSE COALESCE(file_number, record_id)   ← fall back to file number
END AS group_key
```

That `group_key` is then used in the window functions:

```sql
ROW_NUMBER() OVER (PARTITION BY group_key ORDER BY transaction_date DESC)
COUNT(*)      OVER (PARTITION BY group_key)
```

---

## What this means in practice

| Scenario | group_key value | Result |
|---|---|---|
| All 4 tables have the same `prop_id` for a parcel | `prop_id` (e.g. `120045009001`) | All rows collapse into 1; Timeline badge shows full count |
| Only file number is common (no `prop_id`) | `file_number` (e.g. `COM-2022-572`) | Rows grouped by file number instead |
| Clicking Timeline link | passes `?prop_id=…` to `timeline()` | Fetches from all 4 tables with `WHERE prop_id = ?` |

---

## The fallback

`prop_id` is **not guaranteed** to be populated in all rows — the staging tables are imported data. When it's `NULL` or blank, the `group_key` falls back to `file_number`, so the collapsing logic still works, just with less precision (if two different properties accidentally share a file number across tables, they'd collapse together — a known limitation of staging data).