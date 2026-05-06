# Prop ID Timeline Study (PRA, FH, CofO, Instrument Capture)

Date: 2026-04-01
Database: SQL Server (`sqlsrv`)

## Scope
This report studies `prop_id` timeline behavior across the 4 requested tables:
1. `pra`
2. `file_history_staging` (treated as FH)
3. `CofO_staging`
4. `instrument_capture`

## How this was measured
Read-only SQL queries were executed through Laravel bootstrap (`DB::connection('sqlsrv')`) to collect:
1. `rows_with_prop_id`, `distinct_prop_id`, min/max `prop_id`
2. first/last event timestamp per table using `COALESCE(...)` over available date columns
3. pairwise overlap of distinct `prop_id` values between tables
4. all-4-table overlap of distinct `prop_id`
5. sample per-`prop_id` timeline rows

## Key code signals (implementation context)
1. `PropertyIdAllocationService` uses legacy mapping set: `file_history_staging`, `pra`, `pic`, `CofO_staging` for lookup/next-id fallback.
2. `PraRecordService::syncPropIdTimeline()` currently syncs `prop_id` only to `file_history_staging` (based on primary identifier) after PRA create/update.
3. `InstrumentCaptureService` allocates/reuses `prop_id` and writes directly to `instrument_capture`.
4. `RebuildPropIds` command still targets `file_history_staging`, `CofO_staging`, and `pic` (legacy), not `instrument_capture`.

## Results summary

### 1) Per-table prop_id distribution

| Table | Rows with `prop_id` | Distinct `prop_id` | Min `prop_id` | Max `prop_id` | First event | Last event |
|---|---:|---:|---:|---:|---|---|
| `pra` | 19 | 10 | 31 | 40 | 2026-04-01 08:36:58.697 | 2026-04-01 19:00:36.183 |
| `file_history_staging` (FH) | 0 | 0 | null | null | null | null |
| `CofO_staging` | 0 | 0 | null | null | null | null |
| `instrument_capture` | 3 | 3 | 2 | 990001 | 2026-03-29 21:21:35.880 | 2026-04-01 07:43:46.420 |

### 2) Distinct prop_id overlap (pairwise)

| Pair | Distinct shared `prop_id` |
|---|---:|
| `pra` <-> `file_history_staging` | 0 |
| `pra` <-> `CofO_staging` | 0 |
| `pra` <-> `instrument_capture` | 1 |
| `file_history_staging` <-> `CofO_staging` | 0 |
| `file_history_staging` <-> `instrument_capture` | 0 |
| `CofO_staging` <-> `instrument_capture` | 0 |

### 3) Distinct prop_id present in all 4 tables

`all4_overlap_distinct_prop_id = 0`

## Timeline sample (most recent prop_id activity)

| prop_id | first_seen | last_seen | pra_rows | fh_rows | cofo_rows | instrument_capture_rows | total_rows |
|---:|---|---|---:|---:|---:|---:|---:|
| 31 | 2026-04-01 07:43:46.420 | 2026-04-01 19:00:36.183 | 2 | 0 | 0 | 1 | 3 |
| 40 | 2026-04-01 13:06:24.350 | 2026-04-01 18:13:43.210 | 3 | 0 | 0 | 0 | 3 |
| 36 | 2026-04-01 12:37:04.577 | 2026-04-01 13:28:49.753 | 3 | 0 | 0 | 0 | 3 |
| 39 | 2026-04-01 12:53:48.910 | 2026-04-01 12:53:48.910 | 1 | 0 | 0 | 0 | 1 |
| 38 | 2026-04-01 12:52:26.130 | 2026-04-01 12:52:26.130 | 1 | 0 | 0 | 0 | 1 |
| 37 | 2026-04-01 12:38:25.577 | 2026-04-01 12:38:25.613 | 2 | 0 | 0 | 0 | 2 |
| 35 | 2026-04-01 12:35:04.223 | 2026-04-01 12:35:04.287 | 2 | 0 | 0 | 0 | 2 |
| 34 | 2026-04-01 08:43:58.057 | 2026-04-01 08:43:58.073 | 2 | 0 | 0 | 0 | 2 |
| 33 | 2026-04-01 08:38:32.497 | 2026-04-01 08:38:32.497 | 1 | 0 | 0 | 0 | 1 |
| 32 | 2026-04-01 08:36:58.697 | 2026-04-01 08:37:22.247 | 2 | 0 | 0 | 0 | 2 |
| 990001 | 2026-03-30 19:40:52.953 | 2026-03-30 19:40:52.953 | 0 | 0 | 0 | 1 | 1 |
| 2 | 2026-03-29 21:21:35.880 | 2026-03-29 21:21:35.880 | 0 | 0 | 0 | 1 | 1 |

## Legacy PIC context (important)
Although this report focuses on instrument capture, the codebase still contains legacy `pic` paths.

| Metric | Value |
|---|---:|
| `pic_rows_with_prop_id` | 2 |
| `pic_distinct_prop_id` | 2 |
| `pic_min_prop_id` | 4 |
| `pic_max_prop_id` | 5 |
| overlap `pic` <-> `pra` (distinct `prop_id`) | 0 |
| overlap `pic` <-> `instrument_capture` (distinct `prop_id`) | 0 |

## Interpretation
1. Current active timeline is concentrated in `pra` and partially in `instrument_capture`.
2. In this dataset snapshot, FH (`file_history_staging`) and `CofO_staging` have no populated `prop_id`, so cross-table timeline continuity across all 4 tables is not yet realized.
3. There is only one currently shared `prop_id` between `pra` and `instrument_capture` (`prop_id = 31`), indicating limited propagation between those two modules.
4. Legacy `pic` still exists in allocation/rebuild logic, but its `prop_id` values are isolated from both `pra` and `instrument_capture` in this snapshot.

## Practical timeline model (as implemented)
1. `prop_id` allocation/retrieval happens from `PropertyIdAllocationService` (master-first, with legacy table fallback).
2. On PRA create/update, PRA record gets `prop_id`, then PRA service attempts FH sync by primary file number.
3. On Instrument Capture create/update, record gets `prop_id` directly in `instrument_capture`.
4. CofO/FH participation depends on whether records are written with matching identifiers and whether sync/rebuild jobs are run for those tables.

## Suggested next checks
1. Validate why `file_history_staging` and `CofO_staging` currently have zero `prop_id` rows (ETL/import path, sync trigger, or table environment mismatch).
2. Decide whether to include `instrument_capture` in `RebuildPropIds` command or keep `pic` as the canonical legacy table.
3. Add a reconciliation report showing identifier-level misses (`mlsFNo`, `kangisFileNo`, `NewKANGISFileno`, `temp_fileno`) between PRA and Instrument Capture for failed `prop_id` propagation.
