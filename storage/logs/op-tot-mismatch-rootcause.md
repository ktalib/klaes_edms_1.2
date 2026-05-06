# OSS OP → ToT Mismatch — Root-Cause & Prevention Plan

_Generated: 2026-04-30_  ·  _Scope: `lands-one-stop-shop/applications/op-resettlement?source=lands-one-stop-shop&type=change-of-name`_

---


## 1. Summary

When a user opens the OSS Change-of-Name page, picks an OP card, and clicks **"FileNo Commissioning"**, the resulting Transfer-of-Title (ToT) row is sometimes saved against the **wrong OP**, producing two cards on the page that share the same `prop_id` but describe different physical properties (different plot, parties, land use).

A read-only audit of records commissioned today + yesterday found **15 mismatch pairs**. Of these:

- **14 pairs**: classic contamination — `OP src = ic`, `ToT src = pra`, same `temp_fileno` reused, different physical property.
- **1 pair** (`prop_id 88138`): same `temp_fileno` and `fileno`, only `party_2` differs — likely a legitimate same-property transfer between family members; needs human review, not a code fix.

---

## 2. The Flow Today

1. UI renders OP cards aggregated **by `prop_id`** (page query joins OP↔ToT on `prop_id`).
2. User clicks an OP card → modal opens → user fills in the **new grantee + transfer details** for the ToT.
3. Backend creates a new `pra` row of `instrument_type = 'Transfer of Title (OP)'` and **reuses the same `prop_id`** (and very often the same `temp_fileno`) it found on the OP.
4. The user-typed values for `party_2`, `plot_no`, `tp_no`, `location`, `land_use` are written into that PRA row.

---

## 3. Failure Points

### Failure 1 — `prop_id` is not unique-per-property

`prop_id` is intended to be the cross-table 12-digit unique identifier (per `AGENTS.md`), but several OPs in `instrument_capture` and `pra` already share the same `prop_id` because earlier flows allocated by `prop_id` lookup-by-fileno/temp_fileno before a real fileno existed.

Result: the page's "OP card" can fan into multiple physical properties with one `prop_id`.

### Failure 2 — the commissioning controller picks a wrong-but-valid OP at write time

When `MlsFileNoController` / `InstrumentCaptureService` writes the ToT to `pra`, it effectively does:

```sql
SELECT * FROM instrument_capture
 WHERE prop_id = :prop_id
   AND instrument_type = 'Occupancy Permit (OP)'
ORDER BY id DESC
LIMIT 1
```

If the `prop_id` has siblings, this returns the **most recent** IC OP — not necessarily the OP the user actually clicked. The user's typed property values are then stored against that "winner" OP's `prop_id`/`temp_fileno`, and on the next page render the OP card the user *sees* is a different sibling than the ToT card next to it.

### Telltale signature in the data

- 14 of 14 contamination pairs share `same_temp = YES` (OP `temp_fileno` == ToT `temp_fileno`).
- ToTs frequently have `location = "Other"` and blank `plot_no`/`tp_no` — the form did **not** inherit OP property facts; the user typed placeholders.

---

## 4. What Uniquely Identifies an OP

`prop_id` alone is insufficient. The real unique identifiers, in priority order:

| Rank | Identifier | Source | Why it's unique |
|---|---|---|---|
| 1 | `instrument_capture.id` (PK) | IC | True surrogate, never reused |
| 2 | `deed_registrations.id` (PK) | DR | True surrogate |
| 3 | `pra.id` (PK) | PRA | True surrogate |
| 4 | `op_serial_number` + year | All three | Issued sequentially per OP, year disambiguates |
| 5 | `regNo` / `registration_number` (vol/page/serial) | All three | Once registered, this triple is unique per instrument |
| 6 | `temp_fileno` (`TEMP-NNNNN`) | All three | Allocated from `temp_fileno_sequence` IDENTITY — unique unless reused at write time |
| 7 | `mlsFNo` / `fileno` (`RES-YYYY-NNN`) | All three | Unique only after final commissioning |

**Recommended single key for the commissioning flow:** the row's **primary key from the source table the user clicked** — `(source_op_table, source_op_id)`.

The existing `resolveOpDuplicates` route already accepts those two parameters; the OSS FileNo Commissioning flow simply needs to require them too.

---

## 5. Where to Fix It (Prevention)

### Place 1 — Page click handler (frontend)

[`public/js/instruments-capture.js`](../public/js/instruments-capture.js) and the OSS page JS already pass `source_temp_fileno` / `source_op_id` in some flows. Make this **mandatory** for FileNo Commissioning:

- Each OP card's HTML must carry `data-source-table` + `data-source-id` (IC id, DR id, or PRA id) of the exact row rendered.
- The "FileNo Commissioning" button must read those attributes and POST them with the form.

### Place 2 — Commissioning controller (backend, the actual fix)

The OSS FileNo Commissioning endpoint (the one that writes the `pra` row with `instrument_type = 'Transfer of Title (OP)'` for `system_source = 'OSSOPCHANGEOFNAME'`) must:

1. **Require** `source_op_id` + `source_op_table` in the request.
2. Look up the OP by `(source_op_table, source_op_id)` only — never by `prop_id`.
3. Before inserting the ToT row, run the `InstrumentController::resolveOpDuplicates` logic if the OP's `prop_id` has siblings: keep the clicked OP's `prop_id`/`temp_fileno`, reassign siblings.
4. Allocate a **fresh `prop_id`** for the ToT row — a new property record is being created, so the ToT's `prop_id` should not equal the OP's; the OP→ToT linkage is stored as `parent_prop_id` / `source_op_id`, not by reusing `prop_id`.
5. **Inherit `plot_no`, `tp_no`, `location`, `lga`, `land_use` from the OP** — the user types only the new grantee and transfer-specific fields. This also fixes the `location = "Other"` placeholder pattern.

### Place 3 — Page query (rendering)

[`OpResettlementApplicationController::index`](../app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php) joins OP↔ToT on `prop_id`. Once Place 2 stops sharing `prop_id` between OP and ToT, this join must change to use the lineage:

```sql
LEFT JOIN pra t
       ON t.parent_prop_id = ic.prop_id        -- or t.source_op_id = ic.id
      AND t.instrument_type LIKE 'Transfer of Title%'
```

i.e. join by **OP→ToT lineage**, not by reusing the same `prop_id`.

---

## 6. Schema Change Required

Add to `pra` (and to `instrument_capture` ToT rows when applicable):

| Column | Type | Purpose |
|---|---|---|
| `source_op_table` | `nvarchar(50)` | `instrument_capture` / `deed_registrations` / `pra` |
| `source_op_id` | `bigint` | PK of the originating OP row |
| `parent_prop_id` | `nvarchar(50)` | The OP's `prop_id`, so the lineage survives without overloading `prop_id` |

Indexes:

- `(source_op_table, source_op_id)`
- `(parent_prop_id)`

---

## 7. Change Set Summary

1. **Schema**: add `source_op_table`, `source_op_id`, `parent_prop_id` to `pra` (migration on `sqlsrv`).
2. **Controller**: OSS FileNo Commissioning must require + validate the OP source key, write a fresh `prop_id` for the ToT, and copy property facts from the OP.
3. **Frontend**: every OP card embeds `data-source-table` + `data-source-id`; the form posts them.
4. **Page query**: join ToT to OP by `parent_prop_id` / `source_op_id`, not by reused `prop_id`.
5. **Existing data**: the 14 contaminated ToT rows already produced get fixed by the dry-run + apply scripts (gated, transactional, reversible) — held until ops approval.

This eliminates both failure points: the ambiguous join (Place 3) and the wrong-OP write (Place 2). The unique key going forward is `(source_op_table, source_op_id)` — the actual primary key of the row the user clicked.
