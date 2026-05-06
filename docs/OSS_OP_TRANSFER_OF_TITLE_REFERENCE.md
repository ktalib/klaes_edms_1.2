# OSS OP Change of Name / Transfer of Title Reference

## Purpose

This document is the focused reference for the OSS Occupancy Permit Change of Name flow.

Use it when working on:

- the OP Change of Name table
- Transfer of Title display logic
- FileNo Commissioning for OP Change of Name
- PRA write rules for `OSSOPCHANGEOFNAME`
- debugging production differences between OP rows and Transfer of Title rows

This document is intentionally narrower than the broader OSS OP system documentation.

## Feature Summary

In OSS OP Change of Name, the business transaction shown to users is the Transfer of Title record, not the source Occupancy Permit row.

The source OP still matters because it provides the mother record and the temporary file number used during commissioning and tracing, but the table row shown on the Change of Name page must represent the Transfer of Title transaction.

## Canonical Page

- Route: `/lands-one-stop-shop/applications/op-resettlement?type=change-of-name&source=lands-one-stop-shop`
- Controller: `App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController@index`
- View: `resources/views/lands_one_stop_shop/applications.blade.php`

## Canonical Data Source

For Change of Name mode, the table must be driven from `pra`, filtered by:

- `system_source = 'OSSOPCHANGEOFNAME'`
- `instrument_type LIKE '%Transfer of Title%'`

This is the key rule.

The page must not fall back to `instrument_capture` rows that do not have a matching Transfer of Title record in `pra`.

## Display Contract

For the Change of Name table, the row contract is:

1. `Source` must be `TRANSFER OF TITLE (OP)`.
2. `File Title` must be the current holder from the Transfer of Title row, normally `pra.Grantee`.
3. `File Title` must never display `KANO STATE GOVERNMENT` for Change of Name rows.
4. The main `MLS File No` shown in the table must be the Change of Name / Transfer of Title MLS file number from PRA.
5. The OP temporary file number must still be visible as the secondary line or link beneath the main MLS file number.
6. The OP temporary file number is the source OP trace number. It is not a replacement for the Transfer of Title MLS number.

## Correct Field Mapping

When mapping a Change of Name row for the table:

- Main row source: `p.instrument_type`
- Main file number: `COALESCE(NULLIF(p.mlsFNo, ''), p.fileno)`
- File title: `p.Grantee`
- Previous holder: `p.Grantor` or source OP holder fallback
- Secondary temp number: `source_temp_fileno`

For Change of Name mode:

- `source` should be forced to `TRANSFER OF TITLE (OP)`
- `file_title` should prefer `p.Grantee`
- `mls_file_no` should prefer the Transfer of Title MLS number
- `source_temp_fileno` should remain available for the clickable detail link

## Important Distinction

Two numbers are involved and they serve different purposes:

### Transfer of Title MLS File Number

This is the main file number for the commissioned Change of Name row.

Examples:

- `RES-2026-13`
- `RES-2026-12`

This is what should appear on the first line of the `MLS File No` column.

### Source OP Temporary File Number

This is the temporary file number of the mother OP record used to create the Transfer of Title.

Examples:

- `TEMP-21899`
- `TEMP-21898`

This should appear as the secondary line or link beneath the main MLS file number.

## Query Rules

### Change of Name Mode

For `?type=change-of-name`:

- include only PRA rows stamped with `OSSOPCHANGEOFNAME`
- include only Transfer of Title rows
- do not include IC-only OP rows
- do not display OP rows as the table source
- do not use OP holder semantics for file title

### Regular OP / No Change of Name Mode

For non-Change-of-Name OP pages, mixed OP source behavior may still be valid, including source OP rows and supporting IC fallback logic.

This is why Change of Name mode needs its own explicit guardrails.

## Why Regressions Happen

The most common mistakes are:

1. Reusing the generic OP Resettlement query for Change of Name mode.
2. Leaving the `instrument_capture` fallback active in Change of Name mode.
3. Using OP-style title mapping such as `Kano State Government` instead of the Transfer of Title grantee.
4. Replacing the Transfer of Title MLS number with the source OP temp file number.
5. Showing both OP and Transfer of Title rows on the Change of Name page.

## Write-Side Expectations

When the feature saves or commissions a Change of Name record, the system should preserve these cross-table markers:

- `pra.system_source = 'OSSOPCHANGEOFNAME'`
- `mls_file_no.sub_source = 'OP Change of Name'`
- `fileNumber.SOURCE = 'OSS_CHANGE_OF_NAME'`

These are part of the feature identity and are used for later tracing and support.

## Expected User-Facing Result

For a correct Change of Name row, users should see:

- `Source`: `TRANSFER OF TITLE (OP)`
- `MLS File No`: the commissioned Change of Name MLS file number
- secondary link under it: the source OP temp file number
- `File Title`: the new holder's name

Example:

- `Source`: `TRANSFER OF TITLE (OP)`
- `MLS File No`: `RES-2026-13`
- secondary temp number: `TEMP-21899`
- `File Title`: `JAQUELIN GIBSON`

## Quick Validation SQL

### 1. Confirm Change of Name PRA rows exist

```sql
SELECT TOP 50
    id,
    prop_id,
    mlsFNo,
    fileno,
    temp_fileno,
    instrument_type,
    Grantor,
    Grantee,
    created_at
FROM pra
WHERE system_source = 'OSSOPCHANGEOFNAME'
  AND instrument_type LIKE '%Transfer of Title%'
ORDER BY created_at DESC;
```

### 2. Find bad IC-only OP rows that must not appear on the Change of Name page

```sql
SELECT TOP 50
    ic.id,
    ic.prop_id,
    ic.mlsFNo,
    ic.temp_fileno,
    ic.op_serial_number,
    ic.party_1_name,
    ic.created_at
FROM instrument_capture ic
LEFT JOIN pra p
    ON p.prop_id = TRY_CONVERT(nvarchar(100), ic.prop_id)
   AND p.system_source = 'OSSOPCHANGEOFNAME'
   AND p.instrument_type LIKE '%Transfer of Title%'
WHERE ic.instrument_type = 'Occupancy Permit (OP)'
  AND ic.prop_id IS NOT NULL
  AND ic.prop_id <> 0
  AND ISNULL(ic.is_deleted, 0) = 0
  AND p.id IS NULL
ORDER BY ic.created_at DESC;
```

### 3. Confirm source OP temp file number for a Transfer of Title prop_id

```sql
SELECT
    p.id AS pra_id,
    p.prop_id,
    p.mlsFNo AS transfer_mls,
    p.Grantee AS current_holder,
    op.temp_fileno AS source_op_temp_fileno,
    op.mlsFNo AS source_op_mls,
    op.instrument_type AS source_op_type
FROM pra p
OUTER APPLY (
    SELECT TOP 1 p2.temp_fileno, p2.mlsFNo, p2.instrument_type
    FROM pra p2
    WHERE p2.prop_id = p.prop_id
      AND p2.system_source = 'OSSOPCHANGEOFNAME'
      AND p2.instrument_type = 'Occupancy Permit (OP)'
    ORDER BY p2.id ASC
) op
WHERE p.system_source = 'OSSOPCHANGEOFNAME'
  AND p.instrument_type LIKE '%Transfer of Title%'
ORDER BY p.created_at DESC;
```

## Do Not Regress Checklist

Before shipping any Change of Name table change, confirm all of these are true:

- only Transfer of Title rows are shown in Change of Name mode
- no IC-only OP rows leak into the page
- `Source` is never `OCCUPANCY PERMIT (OP)` in Change of Name mode
- `File Title` is never `KANO STATE GOVERNMENT` in Change of Name mode
- the main MLS number is the Transfer of Title MLS number
- the OP temp file number still appears as the secondary trace link

## Relevant Files

- `app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php`
- `resources/views/lands_one_stop_shop/applications.blade.php`
- `app/Http/Controllers/LandsOneStopShop/ApplicationController.php`
- `docs/OSS_OP_SYSTEM_DOCUMENTATION.md`

## Maintenance Note

If future work changes the meaning of `MLS File No` or `source_temp_fileno` on the Change of Name page, update this file at the same time as the controller and Blade changes.