# PRA created_at Write Audit

Date: 2026-04-06
Scope: Application modules that write to SQL Server table `pra` and may influence `created_at` format.

## Executive Summary

Current production code paths that write to `pra` are mostly safe for `created_at` format.

- No active module was found writing `created_at` with a text format like `Apr  5 2026  4:24PM`.
- Current writers use `now()` or repository auto-stamping.
- The mixed legacy values in `pra.created_at` are most likely historical/manual SQL/import artifacts, not from current module logic.

## Findings (By Module)

## 1) PRA Repository (central writer)

- File: app/Services/Pra/Repositories/PraRecordRepository.php:139
- File: app/Services/Pra/Repositories/PraRecordRepository.php:140
- Behavior:
  - If `created_at` is not supplied, repository sets `created_at = now()`.
- Risk of wrong format: Low
- Notes:
  - This is the safest write path and should be preferred.

## 2) Change Of Purpose module

- File: app/Http/Controllers/ChangeOfPurpose/ChangeOfPurposeController.php:430
- File: app/Http/Controllers/ChangeOfPurpose/ChangeOfPurposeController.php:436
- Method: `handlePra()`
- Behavior:
  - Clones latest `pra` row, then overrides `created_at` with `now()` before insert.
- Risk of wrong format: Low for `created_at` format, Medium for data hygiene due to clone pattern.
- Notes:
  - Format is safe, but row-cloning can carry stale values in other columns.

## 3) Property Record import module (OP import to PRA)

- File: app/Http/Controllers/PropertyRecordController.php:1728
- File: app/Http/Controllers/PropertyRecordController.php:1749
- File: app/Http/Controllers/PropertyRecordController.php:1755
- Behavior:
  - Batch payload sets `created_at => now()` and inserts into `pra`.
- Risk of wrong format: Low
- Notes:
  - Timestamps are valid, but many rows in a batch may share the same timestamp.

## 4) Lands One Stop Shop modules via PRA service

- File: app/Http/Controllers/LandsOneStopShop/ApplicationController.php:1787
- File: app/Http/Controllers/LandsOneStopShop/ApplicationController.php:1960
- File: app/Http/Controllers/LandsOneStopShop/ApplicationController.php:2024
- File: app/Http/Controllers/MlsFileNoController.php:1553
- File: app/Http/Controllers/MlsFileNoController.php:1946
- File: app/Http/Controllers/MlsFileNoController.php:2238
- Behavior:
  - These call `PraRecordService::createRecord(...)`, which writes through repository auto-stamping (`now()`).
- Risk of wrong format: Low
- Notes:
  - No inline text formatting of `created_at` found in these payloads.

## 5) API PRA create endpoint

- File: app/Http/Controllers/Api/Pra/PraRecordController.php:164
- File: app/Http/Requests/Pra/PraStoreRequest.php
- Behavior:
  - API create uses validated payload and repository write path.
  - `created_at` is not exposed in request rules; repository stamps it.
- Risk of wrong format: Low

## Not a PRA writer (important clarification)

- FileIndexing controller writes to `CofO_staging`, not `pra`:
  - app/Http/Controllers/FileIndexingController.php:25 (`COFO_TABLE = 'CofO_staging'`)

## Root Cause Assessment

Given current module code, wrong `created_at` string formats in `pra` are likely from one or more of:

1. Historical manual SQL updates/imports.
2. Legacy data migrated before current repository/service flow.
3. Direct DB operations outside Laravel module write paths.

## Recommended Hardening

1. Convert `pra.created_at` to `datetime2` at DB level (best long-term fix).
2. Add DB check/constraint or trigger guard to reject non-date strings if column must remain text.
3. Keep all new writes through `PraRecordService`/`PraRecordRepository`.
4. Avoid clone-and-insert pattern where possible; use explicit payload mapping.

## Quick Verification SQL

```sql
SELECT TOP 100 id, mlsFNo, created_at
FROM pra
WHERE created_at IS NOT NULL
  AND TRY_CONVERT(datetime2(0), CAST(created_at AS varchar(100)), 126) IS NULL
  AND TRY_PARSE(CAST(created_at AS varchar(100)) AS datetime2 USING 'en-US') IS NULL
ORDER BY id DESC;
```

If this returns rows, those values were not produced by current safe write paths and need backfill normalization.
