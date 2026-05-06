# KLAES GIS EDMS — File Numbers & Registries: Real Examples Guide

> Generated: April 2026 | Source: Live database queries against SQL Server

---

## Table of Contents

1. [Registries (Live Data)](#1-registries)
2. [Land Uses & Prefixes](#2-land-uses--prefixes)
3. [MLS File Number Examples](#3-mls-file-number-examples)
4. [ST File Number Examples](#4-st-file-number-examples)
5. [Grouping Tables Examples](#5-grouping-tables-examples)
6. [File Number ↔ Registry Mapping](#6-file-number--registry-mapping)
7. [Complete Format Reference with Real Data](#7-complete-format-reference-with-real-data)
8. [Serial Control & Counters](#8-serial-control--counters)
9. [Source & Customer Type Breakdown](#9-source--customer-type-breakdown)
10. [End-to-End Lifecycle Examples](#10-end-to-end-lifecycle-examples)

---

## 1. Registries

**10 active registries** in the `registries` table:

| ID | Registry Name       | Code    | Active |
|----|---------------------|---------|--------|
| 1  | Lands Registry      | LANDS   | Yes    |
| 2  | Cadastral Registry  | CAD     | Yes    |
| 3  | DCIV Registry       | DCIV    | Yes    |
| 4  | Secret Registry     | SECRET  | Yes    |
| 5  | KANGIS Registry     | KANGIS  | Yes    |
| 6  | SLTR Registry       | SLTR    | Yes    |
| 7  | ST Registry         | ST      | Yes    |
| 8  | Deeds Registry      | DEEDS   | Yes    |
| 9  | SIT Registry        | SIT     | Yes    |
| 10 | Survey Registry     | GKN     | Yes    |

### Registry Roles

| Registry | Purpose | Grouping Table | Example File No |
|----------|---------|----------------|-----------------|
| **Lands** (LANDS) | Core land title files, MLS file numbers | `grouping` | `RES-1981-99` |
| **Cadastral** (CAD) | Cadastral survey records | — | — |
| **DCIV** (DCIV) | DCIV instrument records | `dciv_grouping` | `DCIV-2026-100` |
| **Secret** (SECRET) | Restricted/classified files | — | — |
| **KANGIS** (KANGIS) | Legacy KANGIS GIS files | — | `KN/RES/2024/001` |
| **SLTR** (SLTR) | Systematic Land Title Registration | `sltr_grouping` | `SLTR-100` |
| **ST** (ST) | Sectional Titling applications | `st_file_numbers` | `ST-RES-2025-4` |
| **Deeds** (DEEDS) | Deed filing | — | — |
| **SIT** (SIT) | Site allocation files | `sit_grouping` | `SIT-2026-100` |
| **Survey** (GKN) | Survey plans and records | `gkn_grouping` | `LPKN-100` |

---

## 2. Land Uses & Prefixes

### Land Use Categories

| ID | Land Use      | MLS Prefixes                      | ST Code |
|----|---------------|-----------------------------------|---------|
| 1  | RESIDENTIAL   | `RES`, `RES-RC`, `CON-RES`, `CON-RES-RC` | RES     |
| 2  | COMMERCIAL    | `COM`, `COM-RC`, `CON-COM`, `CON-COM-RC` | COM     |
| 3  | INDUSTRIAL    | `IND`, `IND-RC`, `CON-IND`, `CON-IND-RC` | IND     |
| 4  | AGRICULTURAL  | `AG`, `AG-RC`, `CON-AG`, `CON-AG-RC`     | —       |
| 5  | MIXED USE     | `MISC`, `MISC-RC`, `CON-MISC`, `CON-MISC-RC` | MIXED   |

### Prefix Pattern Explanation

| Prefix Part | Meaning | Example |
|-------------|---------|---------|
| `RES` | Residential base | `RES-2026-7` |
| `COM` | Commercial base | `COM-2024-44` |
| `IND` | Industrial base | `IND-2025-212` |
| `AG` | Agricultural base | `AG-2026-1` |
| `MISC` | Mixed Use base | `MISC-KN-0203` |
| `-RC` suffix | Right of Occupancy Conversion | `RES-RC-2026-1` |
| `CON-` prefix | Consent application | `CON-RES-2025-1` |
| `CON-*-RC` | Consent + R/O Conversion | `CON-COM-RC-2024-1234567` |

---

## 3. MLS File Number Examples

### 3.1 Real MLS File Numbers from `fileNumber` Table

#### Normal Commissioned Files
| ID | mlsfNo | SOURCE | type |
|----|--------|--------|------|
| 131183 | `RES-2026-7` | MLS_Commissioned | MlsFileNO |
| 131179 | `RES-2026-6` | MLS_Commissioned | MlsFileNO |
| 130167 | `RES-2026-2` | MLS_Commissioned | MlsFileNO |
| 130166 | `RES-2026-1` | MLS_Commissioned | MlsFileNO |

#### Captured Existing Files (FFR)
| ID | mlsfNo | SOURCE | type |
|----|--------|--------|------|
| 131182 | `RES-2026-48` | FFR_Existing_Capture | Captured |
| 131181 | `RES-1981-47` | FFR_Existing_Capture | Captured |
| 131178 | `RES-1981-55` | FFR_Existing_Capture | Captured |
| 131176 | `RES-1981-42` | FFR_Existing_Capture | Captured |

#### Direct OP Capture Files
| ID | mlsfNo | SOURCE | type |
|----|--------|--------|------|
| 130173 | `RES-2026-5` | FFR_Direct_OP_Capture | MlsFileNO |
| 130172 | `RES-RC-2026-1` | FFR_Direct_OP_Capture | MlsFileNO |
| 130169 | `RES-2026-4` | FFR_Direct_OP_Capture | MlsFileNO |

#### Temporary Files
| ID | mlsfNo | SOURCE | type |
|----|--------|--------|------|
| 131184 | `RES-1981-47(T)` | MLS_Commissioned | MlsFileNO |
| 131180 | `RES-1981-67(T)` | MLS_Commissioned | MlsFileNO |

#### SIT Files (through MLS system)
| ID | mlsfNo | SOURCE | type |
|----|--------|--------|------|
| 130174 | `SIT-2026-6` | MLS_Commissioned | MlsFileNO |

### 3.2 MLS Enrichment Data (`mls_file_no` Table)

| # | full_file_number | land_use | year | serial | customer_type | source |
|---|------------------|----------|------|--------|---------------|--------|
| 1 | `RES-1981-47(T)` | RES | 2026 | 0 | Individual | Temporary File Commissioning |
| 2 | `RES-2026-7` | RES | 2026 | 7 | Individual | OP Resettlement |
| 3 | `RES-1981-67(T)` | RES | 2026 | 0 | Individual | Temporary File Commissioning |
| 4 | `RES-2026-6` | RES | 2026 | 6 | Individual | OP Resettlement |
| 5 | `SIT-2026-6` | SIT | 2026 | 6 | Government | Direct Allocation |
| 6 | `RES-RC-2026-1` | RES-RC | 2026 | 1 | Individual | Direct Allocation |
| 7 | `RES-2026-5` | RES | 2026 | 5 | Individual | OP Resettlement |
| 8 | `RES-2026-4` | RES | 2026 | 4 | Individual | OP Direct Allocation |
| 9 | `RES-2026-3` | RES | 2026 | 3 | Individual | OP Resettlement |
| 10 | `RES-2026-2` | RES | 2026 | 2 | Individual | OP Direct Allocation |

---

## 4. ST File Number Examples

### 4.1 Real ST File Numbers from `st_file_numbers` Table

#### PRIMARY Type — File numbers tied to mother applications
| ID | np_fileno | fileno (actual) | mls_fileno | code | status | year | serial |
|----|-----------|-----------------|------------|------|--------|------|--------|
| 30103 | `ST-COM-2026-7` | `COM-2024-44` | `COM-2024-44` | COM | ACTIVE | 2026 | 7 |
| 30101 | `ST-COM-2026-6` | `IND-2025-212` | `IND-2025-212` | COM | ACTIVE | 2026 | 6 |
| 20102 | `ST-COM-2026-4` | `IND-2026-3` | `IND-2026-3` | COM | ACTIVE | 2026 | 4 |
| 20100 | `ST-COM-2026-3` | `IND-2026-4` | `IND-2026-4` | COM | ACTIVE | 2026 | 3 |
| 20098 | `ST-COM-2026-1` | `COM-RC-2026-1` | `COM-RC-2026-1` | COM | ACTIVE | 2026 | 1 |

#### SUA Type — Standalone Unit Applications (unit_sequence always `001`)
| ID | np_fileno | fileno (unit) | mls_fileno | code | status | year | serial | unit |
|----|-----------|---------------|------------|------|--------|------|--------|------|
| 20103 | `ST-COM-2026-5` | `ST-COM-2026-5-001` | `ST-COM-2026-5` | COM | ACTIVE | 2026 | 5 | 1 |
| 20099 | `ST-COM-2026-2` | `ST-COM-2026-2-001` | `ST-COM-2026-2` | COM | ACTIVE | 2026 | 2 | 1 |
| 20096 | `ST-COM-2025-8` | `ST-COM-2025-8-001` | `ST-COM-2025-8` | COM | USED | 2025 | 8 | 1 |
| 20095 | `ST-COM-2025-7` | `ST-COM-2025-7-001` | `ST-COM-2025-7` | COM | USED | 2025 | 7 | 1 |

#### PUA Type — Parented Unit Applications (unit_sequence increments)
| ID | np_fileno | fileno (unit) | code | status | year | serial | unit |
|----|-----------|---------------|------|--------|------|--------|------|
| 20097 | `ST-RES-2025-4` | `ST-RES-2025-4-001` | RES | ACTIVE | 2025 | 4 | 1 |
| 20094 | `ST-COM-2025-5` | `ST-COM-2025-5-004` | COM | ACTIVE | 2025 | 5 | 4 |
| 20093 | `ST-COM-2025-5` | `ST-COM-2025-5-003` | COM | ACTIVE | 2025 | 5 | 3 |
| 20092 | `ST-COM-2025-4` | `ST-COM-2025-4-003` | COM | ACTIVE | 2025 | 4 | 3 |

### 4.2 ST Status Distribution

| Type | Status | Count |
|------|--------|-------|
| PRIMARY | ACTIVE | 6 |
| PRIMARY | USED | 7 |
| PUA | ACTIVE | 8 |
| SUA | ACTIVE | 5 |
| SUA | USED | 5 |
| **Total** | | **31** |

### 4.3 How ST File Numbers Relate

```
ST-COM-2025-5 (PRIMARY, np_fileno)
  ├── ST-COM-2025-5-001 (PUA, unit 1)    ← First buyer's unit
  ├── ST-COM-2025-5-002 (PUA, unit 2)    ← Second buyer's unit
  ├── ST-COM-2025-5-003 (PUA, unit 3)    ← Third buyer's unit
  └── ST-COM-2025-5-004 (PUA, unit 4)    ← Fourth buyer's unit

ST-COM-2026-5 (SUA)
  └── ST-COM-2026-5-001 (SUA, unit always 001)  ← Single standalone unit
```

---

## 5. Grouping Tables Examples

### 5.1 Lands Registry → `grouping` Table

**100 records** total | 49 mapped, 51 unmapped

| ID | awaiting_fileno | mls_fileno | mapped | registry | land_use | tracking_id | batch |
|----|-----------------|------------|--------|----------|----------|-------------|-------|
| 99 | `RES-1981-99` | *(empty)* | 0 | 1 | Residential | `TRK-TEST00099-GEN00` | 1 |
| 98 | `RES-1981-98` | *(empty)* | 0 | 1 | Residential | `TRK-TEST00098-GEN00` | 1 |
| 97 | `RES-1981-97` | *(empty)* | 0 | 1 | Residential | `TRK-TEST00097-GEN00` | 1 |
| 96 | `RES-1981-96` | *(empty)* | 0 | 1 | Residential | `TRK-TEST00096-GEN00` | 1 |

> **Workflow:** Files arrive with `awaiting_fileno` (original reference). When matched to an MLS file number, `mls_fileno` is populated and `mapping` flips to `1`.

### 5.2 SLTR Registry → `sltr_grouping` Table

| ID | sltr_awaiting_fileno | registry | group | sys_batch_no | tracking_id |
|----|----------------------|----------|-------|--------------|-------------|
| 100 | `SLTR-100` | SLTR | 1 | 1 | `TRK-44D375D3-27E3F` |
| 99 | `SLTR-99` | SLTR | 1 | 1 | `TRK-8EF91322-234C2` |
| 98 | `SLTR-98` | SLTR | 1 | 1 | `TRK-1202B656-90688` |

**Format:** `SLTR-{SERIAL}` — No year component, just sequential

### 5.3 SIT Registry → `sit_grouping` Table

| ID | sit_awaiting_fileno | year | registry | group | sys_batch_no | tracking_id |
|----|---------------------|------|----------|-------|--------------|-------------|
| 4600 | `SIT-2026-100` | 2026 | SIT | 46 | 46 | `TRK-C60B54E9-12DE5` |
| 4599 | `SIT-2026-99` | 2026 | SIT | 46 | 46 | `TRK-C4CFD0B0-4D528` |
| 4598 | `SIT-2026-98` | 2026 | SIT | 46 | 46 | `TRK-17542F68-2883E` |

**Format:** `SIT-{YEAR}-{SERIAL}` — Includes year, batches of ~100 per group

### 5.4 DCIV Registry → `dciv_grouping` Table

| ID | dciv_awaiting_fileno | year | registry | group | sys_batch_no | tracking_id |
|----|----------------------|------|----------|-------|--------------|-------------|
| 100 | `DCIV-2026-100` | 2026 | DCIV | 1 | 1 | `TRK-DCIV-FBAE7481-00100` |
| 99 | `DCIV-2026-99` | 2026 | DCIV | 1 | 1 | `TRK-DCIV-A4AFB50F-00099` |
| 98 | `DCIV-2026-98` | 2026 | DCIV | 1 | 1 | `TRK-DCIV-3A7254EB-00098` |

**Format:** `DCIV-{YEAR}-{SERIAL}`  
**Tracking ID Format:** `TRK-DCIV-{8HEX}-{5DIGITS}` — Note: DCIV uses a distinct tracking ID pattern with registry prefix

### 5.5 GKN/Survey Registry → `gkn_grouping` Table

| ID | gkn_awaiting_fileno | year | registry | group | sys_batch_no | tracking_id |
|----|---------------------|------|----------|-------|--------------|-------------|
| 1100 | `LPKN-100` | 2026 | LPKN | 1 | 1 | `TRK-E69B7040-00100` |
| 1099 | `LPKN-99` | 2026 | LPKN | 1 | 1 | `TRK-2CE5E0AD-00099` |
| 1098 | `LPKN-98` | 2026 | LPKN | 1 | 1 | `TRK-ADF07681-00098` |

**Format:** `LPKN-{SERIAL}` — No year in file number, but year tracked in row  
**Note:** Registry stored as `LPKN` (Lands Plan Kano), mapped to GKN (Survey Registry)

---

## 6. File Number ↔ Registry Mapping

### Complete Mapping: Which File Number Goes Where

```
┌──────────────────┬──────────────────┬──────────────────────────┬────────────────────┐
│ File No Pattern  │ Registry         │ Grouping Table           │ Awaiting Column    │
├──────────────────┼──────────────────┼──────────────────────────┼────────────────────┤
│ RES-YYYY-NNNN    │ Lands (LANDS)    │ grouping                 │ awaiting_fileno    │
│ COM-YYYY-NNNN    │ Lands (LANDS)    │ grouping                 │ awaiting_fileno    │
│ IND-YYYY-NNNN    │ Lands (LANDS)    │ grouping                 │ awaiting_fileno    │
│ AG-YYYY-NNNN     │ Lands (LANDS)    │ grouping                 │ awaiting_fileno    │
│ MISC-YYYY-NNNN   │ Lands (LANDS)    │ grouping                 │ awaiting_fileno    │
│ RES-RC-YYYY-NN   │ Lands (LANDS)    │ grouping                 │ awaiting_fileno    │
│ CON-RES-YYYY-NN  │ Lands (LANDS)    │ grouping                 │ awaiting_fileno    │
│ *-YYYY-NNNN(T)   │ Lands (LANDS)    │ grouping                 │ awaiting_fileno    │
│ * AND EXTENSION  │ Lands (LANDS)    │ grouping                 │ awaiting_fileno    │
├──────────────────┼──────────────────┼──────────────────────────┼────────────────────┤
│ ST-RES-YYYY-NN   │ ST               │ st_file_numbers          │ np_fileno / fileno │
│ ST-COM-YYYY-NN   │ ST               │ st_file_numbers          │ np_fileno / fileno │
│ ST-IND-YYYY-NN   │ ST               │ st_file_numbers          │ np_fileno / fileno │
│ ST-*-YYYY-NN-UUU │ ST               │ st_file_numbers          │ fileno             │
├──────────────────┼──────────────────┼──────────────────────────┼────────────────────┤
│ SLTR-NNNN        │ SLTR             │ sltr_grouping            │ sltr_awaiting_fileno│
├──────────────────┼──────────────────┼──────────────────────────┼────────────────────┤
│ SIT-YYYY-NNNN    │ SIT              │ sit_grouping             │ sit_awaiting_fileno │
├──────────────────┼──────────────────┼──────────────────────────┼────────────────────┤
│ DCIV-YYYY-NNNN   │ DCIV             │ dciv_grouping            │ dciv_awaiting_fileno│
├──────────────────┼──────────────────┼──────────────────────────┼────────────────────┤
│ LPKN-NNNN        │ Survey (GKN)     │ gkn_grouping             │ gkn_awaiting_fileno │
└──────────────────┴──────────────────┴──────────────────────────┴────────────────────┘
```

### Tracking ID Format Variations (Real Examples)

| Registry | Tracking ID Pattern | Example |
|----------|---------------------|---------|
| Lands | `TRK-{TEST+5DIGITS}-{GEN00}` | `TRK-TEST00099-GEN00` |
| SLTR | `TRK-{8HEX}-{5HEX}` | `TRK-44D375D3-27E3F` |
| SIT | `TRK-{8HEX}-{5HEX}` | `TRK-C60B54E9-12DE5` |
| DCIV | `TRK-DCIV-{8HEX}-{5DIGITS}` | `TRK-DCIV-FBAE7481-00100` |
| GKN | `TRK-{8HEX}-{5DIGITS}` | `TRK-E69B7040-00100` |

---

## 7. Complete Format Reference with Real Data

### 7.1 All File Number Formats

| # | Format Name | Pattern | Real Example | Registry | Year Included |
|---|-------------|---------|--------------|----------|---------------|
| 1 | **Residential** | `RES-{YYYY}-{SERIAL}` | `RES-2026-7` | Lands | Yes |
| 2 | **Commercial** | `COM-{YYYY}-{SERIAL}` | `COM-2024-44` | Lands | Yes |
| 3 | **Industrial** | `IND-{YYYY}-{SERIAL}` | `IND-2025-212` | Lands | Yes |
| 4 | **Agricultural** | `AG-{YYYY}-{SERIAL}` | — | Lands | Yes |
| 5 | **Mixed/Misc** | `MISC-{CODE}-{SERIAL}` | `MISC-KN-0203` | Lands | No |
| 6 | **R/O Conversion** | `RES-RC-{YYYY}-{SERIAL}` | `RES-RC-2026-1` | Lands | Yes |
| 7 | **Consent** | `CON-{LU}-{YYYY}-{SERIAL}` | `CON-COM-RC-2024-1234567` | Lands | Yes |
| 8 | **Temporary** | `{FILE_NO}(T)` | `RES-1981-47(T)` | Lands | Inherited |
| 9 | **Extension** | `{FILE_NO} AND EXTENSION` | — | Lands | Inherited |
| 10 | **SLTR** | `SLTR-{SERIAL}` | `SLTR-100` | SLTR | No |
| 11 | **SIT** | `SIT-{YYYY}-{SERIAL}` | `SIT-2026-6` | SIT | Yes |
| 12 | **DCIV** | `DCIV-{YYYY}-{SERIAL}` | `DCIV-2026-100` | DCIV | Yes |
| 13 | **GKN/Survey** | `LPKN-{SERIAL}` | `LPKN-100` | GKN | No |
| 14 | **ST Primary** | `ST-{LU}-{YYYY}-{SERIAL}` | `ST-COM-2026-7` | ST | Yes |
| 15 | **ST SUA** | `ST-{LU}-{YYYY}-{SERIAL}-001` | `ST-COM-2026-5-001` | ST | Yes |
| 16 | **ST PUA** | `ST-{LU}-{YYYY}-{SERIAL}-{UNIT}` | `ST-COM-2025-5-004` | ST | Yes |
| 17 | **KANGIS** | `{PREFIX}/{YYYY}/{SERIAL}` | *(legacy, no current data)* | KANGIS | Yes |
| 18 | **New KANGIS** | `N{PREFIX}/{YYYY}/{SERIAL}` | *(legacy, no current data)* | KANGIS | Yes |

### 7.2 Anatomy of Each Format

#### MLS Format: `RES-2026-7`
```
RES  -  2026  -  7
 │       │       │
 │       │       └─ Serial number (sequential per land_use + year)
 │       └───────── Year of commissioning
 └───────────────── Land use prefix (from prefix table)
```

#### ST Primary: `ST-COM-2026-7`
```
ST  -  COM  -  2026  -  7
 │      │       │       │
 │      │       │       └─ Serial number (sequential per land_use_code + year)
 │      │       └───────── Year
 │      └───────────────── Land use code
 └──────────────────────── Sectional Titling marker
```

#### ST Unit (SUA/PUA): `ST-COM-2025-5-003`
```
ST  -  COM  -  2025  -  5  -  003
 │      │       │       │      │
 │      │       │       │      └─ Unit sequence (001 for SUA, 001+ for PUA)
 │      │       │       └──────── Parent serial number
 │      │       └──────────────── Year
 │      └──────────────────────── Land use code
 └─────────────────────────────── Sectional Titling marker
```

#### Temporary File: `RES-1981-47(T)`
```
RES-1981-47  (T)
    │          │
    │          └─ Temporary marker suffix
    └──────────── Original file number being referenced
```

#### Right of Occupancy Conversion: `CON-COM-RC-2024-1234567`
```
CON  -  COM  -  RC   -  2024   -  1234567
 │       │       │        │         │
 │       │       │        │         └─ Serial number
 │       │       │        └─────────── Year
 │       │       └──────────────────── Right of Occupancy Conversion
 │       └──────────────────────────── Commercial land use
 └──────────────────────────────────── Consent application marker
```

#### DCIV: `DCIV-2026-100`
```
DCIV  -  2026  -  100
  │       │        │
  │       │        └─ Serial number
  │       └────────── Year
  └────────────────── DCIV registry identifier
```

#### SLTR: `SLTR-100`
```
SLTR  -  100
  │       │
  │       └─ Serial number (no year component)
  └────────── SLTR registry identifier
```

---

## 8. Serial Control & Counters

### 8.1 ST Land Use Serials (from `land_use_serials` table)

| Land Use Type | Prefix | Year | Current Serial |
|---------------|--------|------|----------------|
| COMMERCIAL | ST-COM | 2025 | 1 |
| RESIDENTIAL | ST-RES | 2025 | 170 |
| INDUSTRIAL | ST-IND | 2025 | 0 |
| MIXED | ST-MIXED | 2025 | 0 |

> **Insight:** Residential has the highest volume (170 serials in 2025). Industrial and Mixed have no ST file numbers yet.

### 8.2 How Serial Allocation Works

```
Step 1: Request arrives for COM file number, year 2026
Step 2: DB Transaction BEGIN
Step 3: SELECT current_serial FROM land_use_serials 
        WHERE land_use_type='COMMERCIAL' AND year=2026 
        FOR UPDATE  ← locks the row
Step 4: current_serial is 7, so next = 8
Step 5: UPDATE land_use_serials SET current_serial = 8
Step 6: INSERT INTO st_file_numbers (np_fileno='ST-COM-2026-8', ...)
Step 7: COMMIT ← releases lock

Result: ST-COM-2026-8
```

---

## 9. Source & Customer Type Breakdown

### 9.1 File Number Sources (`fileNumber.SOURCE`)

| Source | Count | Description |
|--------|-------|-------------|
| `MLS_Commissioned` | 9 | Generated through MLS commissioning flow |
| `FFR_Existing_Capture` | 7 | Captured from existing File Folder Register |
| `FFR_Direct_OP_Capture` | 6 | Captured via direct OP (Offer of Terms) |

### 9.2 MLS Commissioning Sources (`mls_file_no.source`)

| Source | Count | Description |
|--------|-------|-------------|
| `OP Resettlement` | 5 | Offer of terms for resettlement purposes |
| `Temporary File Commissioning` | 2 | Creating temporary file references |
| `Direct Allocation` | 2 | Direct government allocation |
| `OP Direct Allocation` | 2 | Offer of terms for direct allocation |

### 9.3 Sub-Sources

| Sub-Source | Count | Description |
|------------|-------|-------------|
| `OP Change of Name` | 7 | Change of ownership name on OP file |

### 9.4 Customer Types

| Customer Type | Count | Description |
|---------------|-------|-------------|
| `Individual` | 10 | Personal file numbers |
| `Government` | 1 | Government-assigned files |
| *(Not yet seen)* | — | `Corporate`, `Multiple` also supported |

---

## 10. End-to-End Lifecycle Examples

### Example 1: New MLS Residential File (Direct Allocation)

```
1. USER → Clicks "Commission New File Number"
2. SYSTEM → Checks MLS serial control for RES, year 2026
3. SERIAL → Next available: 8 (after RES-2026-7)
4. FILE NUMBER → RES-2026-8
5. SAVED TO:
   • fileNumber table: mlsfNo='RES-2026-8', SOURCE='MLS_Commissioned', type='MlsFileNO'
   • mls_file_no table: full_file_number='RES-2026-8', land_use='RES', year=2026, serial=8
6. TRACKING ID → TRK-A3B7C912-5D8E2 (auto-generated)
7. STATUS → Active, searchable via global selector
```

### Example 2: ST Sectional Titling (SUA — Standalone Unit)

```
1. USER → Commission new ST application (SUA, Commercial)
2. SYSTEM → Checks land_use_serials for COMMERCIAL, year 2026
3. SERIAL → Current is 7, next = 8
4. PRIMARY FILE → ST-COM-2026-8
5. UNIT FILE → ST-COM-2026-8-001 (SUA always gets -001)
6. SAVED TO st_file_numbers:
   • np_fileno='ST-COM-2026-8', fileno='ST-COM-2026-8-001'
   • file_no_type='SUA', status='ACTIVE'
   • unit_sequence=1
7. LIFECYCLE: RESERVED → (24h expiry) → ACTIVE → USED
```

### Example 3: ST Sectional Titling (PUA — Multi-Unit Building)

```
1. PARENT → ST-COM-2025-5 exists as PRIMARY (a building)
2. BUYER 1 → Gets ST-COM-2025-5-001 (PUA, unit=1)
3. BUYER 2 → Gets ST-COM-2025-5-002 (PUA, unit=2)
4. BUYER 3 → Gets ST-COM-2025-5-003 (PUA, unit=3)  ← Real record ID 20093
5. BUYER 4 → Gets ST-COM-2025-5-004 (PUA, unit=4)  ← Real record ID 20094
6. ALL share the same np_fileno: ST-COM-2025-5
7. Each has unique fileno with incrementing unit_sequence
```

### Example 4: Temporary File Creation

```
1. EXISTING FILE → RES-1981-47 (captured from FFR)
2. USER → Requests temporary file
3. SYSTEM → Creates RES-1981-47(T)
4. SAVED TO:
   • fileNumber: mlsfNo='RES-1981-47(T)', SOURCE='MLS_Commissioned'
   • mls_file_no: source='Temporary File Commissioning', serial=0
5. RELATIONSHIP → Temp file references original RES-1981-47
```

### Example 5: Grouping / EDMS Pipeline (Lands Registry)

```
1. FILE ARRIVES at registry → Physical file with number RES-1981-99
2. INTO GROUPING:
   • awaiting_fileno = 'RES-1981-99'
   • mapping = 0 (unmapped)
   • registry = '1' (Lands)
   • tracking_id = 'TRK-TEST00099-GEN00'
3. MATCHING STEP → User matches to MLS file number
   • mls_fileno = 'RES-1981-99' (or different if cross-matched)
   • mapping = 1 (mapped)
4. FILE INDEXING → Record created in file_indexings with file_number
5. SCANNING → Physical pages scanned, page typing done
6. ARCHIVE → File stored at shelf/rack location
```

### Example 6: SIT File Through MLS System

```
1. USER → Commissions SIT file number
2. FORMAT → SIT-2026-6 (SIT prefix, year, serial)
3. REGISTRY → SIT Registry (ID=9)
4. STORED IN:
   • fileNumber: mlsfNo='SIT-2026-6', SOURCE='MLS_Commissioned'
   • mls_file_no: land_use='SIT', source='Direct Allocation', customer_type='Government'
5. SIT GROUPING → sit_awaiting_fileno='SIT-2026-6' in sit_grouping table
```

---

## Quick Reference Card

```
╔═══════════════════════════════════════════════════════════════════════╗
║                    FILE NUMBER QUICK REFERENCE                       ║
╠═══════════════════════════════════════════════════════════════════════╣
║                                                                       ║
║  LANDS:      RES-2026-7        COM-2024-44       IND-2025-212        ║
║              RES-RC-2026-1     CON-COM-RC-2024-1234567               ║
║              RES-1981-47(T)    {FILE} AND EXTENSION                  ║
║                                                                       ║
║  ST:         ST-RES-2025-4     (Primary)                              ║
║              ST-COM-2026-5-001 (SUA - standalone unit)               ║
║              ST-COM-2025-5-004 (PUA - parented unit #4)              ║
║                                                                       ║
║  SLTR:       SLTR-100          (no year)                              ║
║  SIT:        SIT-2026-6        (with year)                            ║
║  DCIV:       DCIV-2026-100     (with year)                            ║
║  GKN:        LPKN-100          (no year)                              ║
║                                                                       ║
║  TRACKING:   TRK-A3B7C912-5D8E2                                      ║
║  DCIV TRK:   TRK-DCIV-FBAE7481-00100                                 ║
║                                                                       ║
╚═══════════════════════════════════════════════════════════════════════╝
```
