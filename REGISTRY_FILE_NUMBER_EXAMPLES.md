# KLAES — Registry File Number Examples (Live Data)

> Generated: April 2026 | All examples pulled from live SQL Server database

---

## Registry Overview

| # | Registry | Code | Grouping Table | Records | Has File Numbers |
|---|----------|------|----------------|---------|------------------|
| 1 | Lands Registry | LANDS | `grouping` | 100 | Yes — MLS format |
| 2 | Cadastral Registry | CAD | *(none yet)* | 0 | Not yet implemented |
| 3 | DCIV Registry | DCIV | `dciv_grouping` | 100 | Yes — DCIV format |
| 4 | Secret Registry | SECRET | *(none yet)* | 0 | Not yet implemented |
| 5 | KANGIS Registry | KANGIS | `kangis_grouping` | 400 | Yes — KANGIS legacy format |
| 6 | SLTR Registry | SLTR | `sltr_grouping` | 100 | Yes — SLTR format |
| 7 | ST Registry | ST | `st_file_numbers` | 31 | Yes — ST format |
| 8 | Deeds Registry | DEEDS | *(none yet)* | 0 | Not yet implemented |
| 9 | SIT Registry | SIT | `sit_grouping` | 4,600 | Yes — SIT format |
| 10 | Survey Registry | GKN | `gkn_grouping` | 1,100 | Yes — LPKN format |

---

## 1. Lands Registry (LANDS)

**Table:** `grouping` | **100 records** (49 mapped, 51 unmapped)

### File Number Format
```
{LAND_USE_PREFIX}-{YEAR}-{SERIAL}
```

### Real Examples — Mapped (awaiting → MLS linked)

| awaiting_fileno | mls_fileno | land_use | tracking_id | batch |
|-----------------|------------|----------|-------------|-------|
| `RES-1981-87` | `RES-1981-87` | Residential | `TRK-TEST00087-GEN00` | 1 |
| `RES-1981-67` | `RES-1981-67` | Residential | `TRK-TEST00067-GEN00` | 1 |
| `RES-1981-66` | `RES-1981-66` | Residential | `TRK-TEST00066-GEN00` | 1 |
| `RES-1981-62` | `RES-1981-62` | Residential | `TRK-TEST00062-GEN00` | 1 |
| `RES-1981-55` | `RES-1981-55` | Residential | `TRK-TEST00055-GEN00` | 1 |

### Real Examples — Unmapped (awaiting only)

| awaiting_fileno | land_use | tracking_id |
|-----------------|----------|-------------|
| `RES-1981-100` | Residential | `TRK-TEST00100-GEN00` |
| `RES-1981-99` | Residential | `TRK-TEST00099-GEN00` |
| `RES-1981-98` | Residential | `TRK-TEST00098-GEN00` |

### MLS Commissioned File Numbers (from `fileNumber` + `mls_file_no`)

| mlsfNo | land_use | year | serial | customer_type | source |
|--------|----------|------|--------|---------------|--------|
| `RES-2026-7` | RES | 2026 | 7 | Individual | OP Resettlement |
| `RES-2026-6` | RES | 2026 | 6 | Individual | OP Resettlement |
| `RES-2026-5` | RES | 2026 | 5 | Individual | OP Resettlement |
| `RES-2026-4` | RES | 2026 | 4 | Individual | OP Direct Allocation |
| `RES-2026-3` | RES | 2026 | 3 | Individual | OP Resettlement |
| `RES-2026-2` | RES | 2026 | 2 | Individual | OP Direct Allocation |
| `RES-RC-2026-1` | RES-RC | 2026 | 1 | Individual | Direct Allocation |
| `RES-1981-47(T)` | RES | 2026 | 0 | Individual | Temporary File Commissioning |
| `RES-1981-67(T)` | RES | 2026 | 0 | Individual | Temporary File Commissioning |
| `SIT-2026-6` | SIT | 2026 | 6 | Government | Direct Allocation |

### All Prefix Variants (from `prefix` table)

| Prefix | Land Use | Meaning |
|--------|----------|---------|
| `RES` | Residential | Standard residential |
| `RES-RC` | Residential | Right of Occupancy Conversion |
| `CON-RES` | Residential | Consent application |
| `CON-RES-RC` | Residential | Consent + R/O Conversion |
| `COM` | Commercial | Standard commercial |
| `COM-RC` | Commercial | Right of Occupancy Conversion |
| `CON-COM` | Commercial | Consent application |
| `CON-COM-RC` | Commercial | Consent + R/O Conversion |
| `IND` | Industrial | Standard industrial |
| `IND-RC` | Industrial | Right of Occupancy Conversion |
| `CON-IND` | Industrial | Consent application |
| `CON-IND-RC` | Industrial | Consent + R/O Conversion |
| `AG` | Agricultural | Standard agricultural |
| `AG-RC` | Agricultural | Right of Occupancy Conversion |
| `CON-AG` | Agricultural | Consent application |
| `CON-AG-RC` | Agricultural | Consent + R/O Conversion |
| `MISC` | Mixed Use | Standard mixed use |
| `MISC-RC` | Mixed Use | Right of Occupancy Conversion |
| `CON-MISC` | Mixed Use | Consent application |
| `CON-MISC-RC` | Mixed Use | Consent + R/O Conversion |

---

## 2. Cadastral Registry (CAD)

**Status:** Registry defined but **no grouping table or file numbers yet**

```
No cad_grouping table exists.
No CAD-prefixed file numbers in the fileNumber table.
```

> This registry is reserved for cadastral survey records and will be implemented when needed.

---

## 3. DCIV Registry

**Table:** `dciv_grouping` | **100 records**

### File Number Format
```
DCIV-{YEAR}-{SERIAL}
```

### Real Examples

| ID | dciv_awaiting_fileno | year | group | batch | tracking_id |
|----|----------------------|------|-------|-------|-------------|
| 100 | `DCIV-2026-100` | 2026 | 1 | 1 | `TRK-DCIV-FBAE7481-00100` |
| 99 | `DCIV-2026-99` | 2026 | 1 | 1 | `TRK-DCIV-A4AFB50F-00099` |
| 98 | `DCIV-2026-98` | 2026 | 1 | 1 | `TRK-DCIV-3A7254EB-00098` |
| 97 | `DCIV-2026-97` | 2026 | 1 | 1 | `TRK-DCIV-2B018B34-00097` |
| 96 | `DCIV-2026-96` | 2026 | 1 | 1 | `TRK-DCIV-0CC5552F-00096` |

### Format Anatomy
```
DCIV-2026-100
 │     │    │
 │     │    └─ Serial (sequential within year)
 │     └────── Year
 └──────────── DCIV registry prefix
```

### Tracking ID Pattern
```
TRK-DCIV-{8 HEX}-{5 ZERO-PADDED SERIAL}
         │                │
         │                └─ Matches the file serial, zero-padded to 5 digits
         └──────────────── Random hex identifier

Example: TRK-DCIV-FBAE7481-00100
```

> **Note:** DCIV tracking IDs uniquely include the registry prefix (`DCIV`) inside the tracking ID, unlike other registries.

---

## 4. Secret Registry (SECRET)

**Status:** Registry defined but **no grouping table or file numbers yet**

```
No secret_grouping table exists.
No SECRET-prefixed file numbers found.
```

> Reserved for restricted/classified land files.

---

## 5. KANGIS Registry

**Table:** `kangis_grouping` | **400 records**

### File Number Format
```
{KANGIS_PREFIX} {SERIAL}
```
> Note: Uses **space** separator, not dash. This is the legacy KANGIS format.

### Real Examples

| ID | kangis_awaiting_fileno | kangis_fileno | group | batch | tracking_id |
|----|------------------------|---------------|-------|-------|-------------|
| 400 | `KNGP 100` | `KNGP 100` | 1 | 1 | `TRK-0AC3234-ABF93` |
| 399 | `MNKL 100` | `MNKL 100` | 1 | 1 | `TRK-E0C7273-FF787` |
| 398 | `MLKN 100` | `MLKN 100` | 1 | 1 | `TRK-FF183CA-53B1F` |
| 397 | `KNML 100` | `KNML 100` | 1 | 1 | `TRK-3EF735E-9F304` |
| 396 | `KNGP 99` | `KNGP 99` | 1 | 1 | `TRK-16B5C93-4B61D` |

### KANGIS Prefix Types

| Prefix | Full Name |
|--------|-----------|
| `KNGP` | Kano Geographic Plan |
| `MNKL` | Municipal Kano Land |
| `MLKN` | Municipal Land Kano |
| `KNML` | Kano Municipal Land |

### Grouping Table Columns
```
id, kangis_awaiting_fileno, kangis_fileno, mapping, registry, group,
mdc_batch_no, sys_batch_no, registry_batch_no, tracking_id,
year, landuse, shelf_rack, indexing_mapping, indexing_kangis_fileno,
year_batch_no, number, date, test_control
```

> **Note:** KANGIS file numbers are all legacy. No new KANGIS numbers are being generated; the `kangisFileNo` and `NewKANGISFileNo` columns on `fileNumber` table currently have no data.

---

## 6. SLTR Registry

**Table:** `sltr_grouping` | **100 records**

### File Number Format
```
SLTR-{SERIAL}
```
> No year component — just a sequential serial number.

### Real Examples

| ID | sltr_awaiting_fileno | group | batch | tracking_id |
|----|----------------------|-------|-------|-------------|
| 100 | `SLTR-100` | 1 | 1 | `TRK-44D375D3-27E3F` |
| 99 | `SLTR-99` | 1 | 1 | `TRK-8EF91322-234C2` |
| 98 | `SLTR-98` | 1 | 1 | `TRK-1202B656-90688` |
| 97 | `SLTR-97` | 1 | 1 | `TRK-41061EA1-A121A` |
| 96 | `SLTR-96` | 1 | 1 | `TRK-E3F98BC5-9454A` |

### Format Anatomy
```
SLTR-100
  │    │
  │    └─ Sequential serial number (no year, no land use)
  └────── Systematic Land Title Registration prefix
```

---

## 7. ST Registry (Sectional Titling)

**Table:** `st_file_numbers` | **31 records** (13 PRIMARY, 10 SUA, 8 PUA)

### File Number Format
```
PRIMARY:  ST-{LAND_USE_CODE}-{YEAR}-{SERIAL}
SUA:      ST-{LAND_USE_CODE}-{YEAR}-{SERIAL}-001
PUA:      ST-{LAND_USE_CODE}-{YEAR}-{SERIAL}-{UNIT_SEQ}
```

### Real PRIMARY Examples

| np_fileno | fileno (MLS link) | mls_fileno | code | status | year | serial |
|-----------|--------------------|------------|------|--------|------|--------|
| `ST-COM-2026-7` | `COM-2024-44` | `COM-2024-44` | COM | ACTIVE | 2026 | 7 |
| `ST-COM-2026-6` | `IND-2025-212` | `IND-2025-212` | COM | ACTIVE | 2026 | 6 |
| `ST-COM-2026-4` | `IND-2026-3` | `IND-2026-3` | COM | ACTIVE | 2026 | 4 |
| `ST-COM-2026-3` | `IND-2026-4` | `IND-2026-4` | COM | ACTIVE | 2026 | 3 |
| `ST-COM-2026-1` | `COM-RC-2026-1` | `COM-RC-2026-1` | COM | ACTIVE | 2026 | 1 |

> A PRIMARY has an `np_fileno` (the ST number) and also links to an existing MLS `fileno`.

### Real SUA Examples (Standalone Unit — always unit 001)

| np_fileno | fileno | mls_fileno | unit | status |
|-----------|--------|------------|------|--------|
| `ST-COM-2026-5` | `ST-COM-2026-5-001` | `ST-COM-2026-5` | 1 | ACTIVE |
| `ST-COM-2026-2` | `ST-COM-2026-2-001` | `ST-COM-2026-2` | 1 | ACTIVE |
| `ST-COM-2025-8` | `ST-COM-2025-8-001` | `ST-COM-2025-8` | 1 | USED |
| `ST-COM-2025-7` | `ST-COM-2025-7-001` | `ST-COM-2025-7` | 1 | USED |
| `ST-COM-2025-6` | `ST-COM-2025-6-001` | `ST-COM-2025-6` | 1 | ACTIVE |

> SUA = single standalone unit — always gets unit sequence `001`.

### Real PUA Examples (Parented Unit — multiple buyers per building)

| np_fileno (parent) | fileno (unit) | unit | status |
|--------------------|---------------|------|--------|
| `ST-RES-2025-4` | `ST-RES-2025-4-001` | 1 | ACTIVE |
| `ST-COM-2025-5` | `ST-COM-2025-5-002` | 2 | ACTIVE |
| `ST-COM-2025-5` | `ST-COM-2025-5-003` | 3 | ACTIVE |
| `ST-COM-2025-5` | `ST-COM-2025-5-004` | 4 | ACTIVE |
| `ST-COM-2025-4` | `ST-COM-2025-4-003` | 3 | ACTIVE |

> PUA = parented unit application. Multiple units share the same `np_fileno` (building). Each buyer gets an incrementing unit sequence.

### ST Tree View (Real Data)
```
ST-COM-2025-5 (PRIMARY — the building)
  ├── ST-COM-2025-5-002 (PUA, unit 2, ACTIVE)
  ├── ST-COM-2025-5-003 (PUA, unit 3, ACTIVE)
  └── ST-COM-2025-5-004 (PUA, unit 4, ACTIVE)

ST-COM-2025-4 (PRIMARY — another building)
  └── ST-COM-2025-4-003 (PUA, unit 3, ACTIVE)

ST-RES-2025-4 (PRIMARY — residential building)
  └── ST-RES-2025-4-001 (PUA, unit 1, ACTIVE)

ST-COM-2026-5 (SUA — standalone)
  └── ST-COM-2026-5-001 (SUA, always unit 001, ACTIVE)
```

### Status Distribution

| Type | ACTIVE | USED | Total |
|------|--------|------|-------|
| PRIMARY | 6 | 7 | 13 |
| SUA | 5 | 5 | 10 |
| PUA | 8 | 0 | 8 |
| **All** | **19** | **12** | **31** |

---

## 8. Deeds Registry (DEEDS)

**Status:** Registry defined but **no grouping table or file numbers yet**

```
No deeds_grouping table exists.
```

> Reserved for deed filing records.

---

## 9. SIT Registry

**Table:** `sit_grouping` | **4,600 records** (largest registry by volume)

### File Number Format
```
SIT-{YEAR}-{SERIAL}
```

### Real Examples

| ID | sit_awaiting_fileno | year | group | batch | tracking_id |
|----|---------------------|------|-------|-------|-------------|
| 4600 | `SIT-2026-100` | 2026 | 46 | 46 | `TRK-C60B54E9-12DE5` |
| 4599 | `SIT-2026-99` | 2026 | 46 | 46 | `TRK-C4CFD0B0-4D528` |
| 4598 | `SIT-2026-98` | 2026 | 46 | 46 | `TRK-17542F68-2883E` |
| 4597 | `SIT-2026-97` | 2026 | 46 | 46 | `TRK-D8E9489B-1825E` |
| 4596 | `SIT-2026-96` | 2026 | 46 | 46 | `TRK-04645A6E-BF8C1` |

### Format Anatomy
```
SIT-2026-100
 │    │    │
 │    │    └─ Serial (sequential within year)
 │    └────── Year
 └─────────── Site allocation prefix
```

### Batching Pattern
SIT uses **100 records per group/batch** (4,600 records ÷ 46 batches = 100 per batch)

### SIT in MLS System
SIT files are also commissioned through MLS:

| mlsfNo | land_use | customer_type | source |
|--------|----------|---------------|--------|
| `SIT-2026-6` | SIT | Government | Direct Allocation |

---

## 10. Survey Registry (GKN)

**Table:** `gkn_grouping` | **1,100 records**

### File Number Format
```
LPKN-{SERIAL}
```
> Note: The data uses `LPKN` (Lands Plan Kano) prefix, mapped to the GKN (Survey) registry. No year in the file number.

### Real Examples

| ID | gkn_awaiting_fileno | year | group | batch | tracking_id |
|----|---------------------|------|-------|-------|-------------|
| 1100 | `LPKN-100` | 2026 | 1 | 1 | `TRK-E69B7040-00100` |
| 1099 | `LPKN-99` | 2026 | 1 | 1 | `TRK-2CE5E0AD-00099` |
| 1098 | `LPKN-98` | 2026 | 1 | 1 | `TRK-ADF07681-00098` |
| 1097 | `LPKN-97` | 2026 | 1 | 1 | `TRK-1AC61ABF-00097` |
| 1096 | `LPKN-96` | 2026 | 1 | 1 | `TRK-D93E8037-00096` |

### Format Anatomy
```
LPKN-100
  │    │
  │    └─ Sequential serial number
  └────── Lands Plan Kano prefix (Survey registry)
```

### Tracking ID Pattern
```
TRK-{8 HEX}-{5 ZERO-PADDED SERIAL}

Example: TRK-E69B7040-00100
         TRK-2CE5E0AD-00099
```

---

## Summary Comparison

### File Number Format Cheat Sheet

| Registry | Format | Has Year | Has Land Use | Separator | Example |
|----------|--------|----------|--------------|-----------|---------|
| **Lands** | `{PREFIX}-{YYYY}-{SERIAL}` | Yes | Yes (prefix) | Dash `-` | `RES-2026-7` |
| **Lands (temp)** | `{FILE}(T)` | Inherited | Inherited | Parens `()` | `RES-1981-47(T)` |
| **Lands (R/O)** | `{PREFIX}-RC-{YYYY}-{SERIAL}` | Yes | Yes | Dash `-` | `RES-RC-2026-1` |
| **Lands (consent)** | `CON-{PREFIX}-{YYYY}-{SERIAL}` | Yes | Yes | Dash `-` | `CON-COM-RC-2024-1` |
| **KANGIS** | `{PREFIX} {SERIAL}` | No | No | Space ` ` | `KNGP 100` |
| **ST primary** | `ST-{CODE}-{YYYY}-{SERIAL}` | Yes | Yes (code) | Dash `-` | `ST-COM-2026-7` |
| **ST unit** | `ST-{CODE}-{YYYY}-{SERIAL}-{UNIT}` | Yes | Yes (code) | Dash `-` | `ST-COM-2025-5-003` |
| **SLTR** | `SLTR-{SERIAL}` | No | No | Dash `-` | `SLTR-100` |
| **SIT** | `SIT-{YYYY}-{SERIAL}` | Yes | No | Dash `-` | `SIT-2026-100` |
| **DCIV** | `DCIV-{YYYY}-{SERIAL}` | Yes | No | Dash `-` | `DCIV-2026-100` |
| **GKN/Survey** | `LPKN-{SERIAL}` | No | No | Dash `-` | `LPKN-100` |

### Volume Breakdown

```
  SIT Registry ........ 4,600 records  ████████████████████████████████████  (74%)
  GKN/Survey .......... 1,100 records  █████████                            (18%)
  KANGIS .............. 400 records    ███                                  (6%)
  Lands ............... 100 records    █                                    (2%)
  DCIV ................ 100 records    █                                    (2%)
  SLTR ................ 100 records    █                                    (2%)
  ST .................. 31 records     ▏                                    (<1%)
  Cadastral ........... —                                                   
  Secret .............. —                                                   
  Deeds ............... —                                                   
```

### Tracking ID Patterns by Registry

| Registry | Pattern | Example |
|----------|---------|---------|
| Lands | `TRK-{TESTSERIAL}-{GEN00}` | `TRK-TEST00087-GEN00` |
| KANGIS | `TRK-{7HEX}-{5HEX}` | `TRK-0AC3234-ABF93` |
| SLTR | `TRK-{8HEX}-{5HEX}` | `TRK-44D375D3-27E3F` |
| SIT | `TRK-{8HEX}-{5HEX}` | `TRK-C60B54E9-12DE5` |
| DCIV | `TRK-DCIV-{8HEX}-{5DIGITS}` | `TRK-DCIV-FBAE7481-00100` |
| GKN | `TRK-{8HEX}-{5DIGITS}` | `TRK-E69B7040-00100` |
| MLS | `TRK-{8ALPHANUM}-{5ALPHANUM}` | `TRK-A3B7C912-5D8E2` |
