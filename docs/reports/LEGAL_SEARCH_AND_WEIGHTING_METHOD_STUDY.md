# Legal Search System & Weighting Method — Comprehensive Study

**Date:** April 10, 2026  
**Scope:** Full analysis of the Legal Search module architecture, data flow, deduplication weighting algorithm, and related subsystems.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Legal Search System Overview](#2-legal-search-system-overview)
3. [Architecture & Code Structure](#3-architecture--code-structure)
4. [Data Sources & Tables](#4-data-sources--tables)
5. [Search Algorithm & Flow](#5-search-algorithm--flow)
6. [The Weighting Method — Deep Dive](#6-the-weighting-method--deep-dive)
7. [Cleanup Mode Operations](#7-cleanup-mode-operations)
8. [Arrangement (Reorder) System](#8-arrangement-reorder-system)
9. [Report Generation & Print](#9-report-generation--print)
10. [Frontend Architecture](#10-frontend-architecture)
11. [Roles & Permissions](#11-roles--permissions)
12. [Route Map](#12-route-map)
13. [Evolution History (From Docs)](#13-evolution-history)
14. [Key Findings & Current State](#14-key-findings--current-state)
15. [Open Issues & Risks](#15-open-issues--risks)

---

## 1. Executive Summary

The **Legal Search** module is a land records investigation tool used by the Ministry of Land & Physical Planning (Kano State) to search across **4 staging tables** and produce legal search reports for property transactions. It exists in three channels: **Official**, **On-Premise (Pay-per-Search)**, and **Online**.

A critical cross-cutting concern is **deduplication**: the same logical transaction can appear in multiple staging tables (File History, PRA, CofO, Deed Registrations). The **Weighting Method** is the scoring algorithm that determines which duplicate record is the "canonical" version shown in reports and timelines.

### Key Numbers

| Metric | Value |
|--------|-------|
| Staging tables searched | 4 (file_history_staging, CofO_staging, pra, deed_registrations) |
| Search channels | 3 (Official, On-Premise, Online) |
| Controllers | 2 active (`LegalSearchController`, `OnPremiseController`) + 1 reports |
| Service class | 1 (`LegalSearchService`) |
| Weighting score range | 1–5 points per record |
| Dedup scope | FH vs PRA records (CofO and Deed records bypass dedup) |

---

## 2. Legal Search System Overview

### 2.1 Purpose

Legal Search provides a single interface to search a property's **complete transaction history** across all 4 staging tables, producing an official report that shows:
- All instruments registered against a property (transfers, mortgages, powers of attorney, etc.)
- Certificate of Occupancy records
- Caveat/encumbrance status
- Registration Particulars (serial/page/volume)

### 2.2 Three Channels

| Channel | Controller | Audience | Revenue Model |
|---------|-----------|----------|---------------|
| **Official (for filing purpose)** | `LegalSearchController` | Government staff | Free (internal use) |
| **On-Premise (Pay-per-Search)** | `OnPremiseController` | Walk-in customers (lawyers, buyers) | Pay-per-search |
| **Online** | External HTML template | Public web users | Online fee |

### 2.3 How On-Premise Extends Official

`OnPremiseController` **extends** `LegalSearchController`, overriding only:
- `$pageTitle` → `'On-Premise - Pay-per-Search'`
- `$viewPrefix` → `'legal_search'` (same views!)
- `$searchRouteName` → `'onpremise.search'`
- `$watermarkText` → `'PAY-PER-SEARCH'`
- `$printManagerDocType` → `'Legal Search Pay-Per-Search'`

Both channels use the **same views**, **same service**, and **same database queries**. The only difference is branding and the print template watermark.

---

## 3. Architecture & Code Structure

### 3.1 File Map

```
Backend:
├── app/Services/LegalSearchService.php              ← Core search + cleanup logic
├── app/Http/Controllers/LegalSearchController.php    ← Official channel controller
├── app/Http/Controllers/OnPremiseController.php      ← Pay-per-Search (extends above)
├── app/Http/Controllers/LegalsearchreportsController.php ← Reports dashboard

Frontend:
├── resources/views/legal_search/
│   ├── index.blade.php                               ← Main SPA page
│   ├── js.blade.php                                  ← JavaScript (~1500+ lines)
│   ├── style.blade.php                               ← CSS styles
│   ├── report.blade.php                              ← Report view
│   └── templates/
│       ├── OFFICIAL SEARCH REPORT.html               ← Print template (official)
│       ├── PAY-PER-SEARCH.html                       ← Print template (on-premise)
│       └── ONLINE.html                               ← Print template (online)
├── resources/views/legalsearchreports/
│   └── index.blade.php                               ← Reports dashboard

Database:
├── database/migrations/2026_03_21_..._create_legal_search_timeline_arrangements_table.php

Routes:
├── routes/app3.php                                   ← All active legal search routes
```

### 3.2 Service Layer

`LegalSearchService` centralizes all data access:

| Method Category | Methods |
|-----------------|---------|
| **Search** | `search()`, `searchFileHistoryStaging()`, `searchCofoStaging()`, `searchPra()`, `searchDeedRegistrations()` |
| **Cross-table expansion** | `collectPropIds()`, `buildExistingIdMap()`, `searchByPropIds()` |
| **Filtering** | `applyFilters()`, `applySoftDeleteFilter()` |
| **Normalization** | `normalizeRow()` |
| **Cleanup** | `matchRecords()`, `dropRecords()`, `removeRecords()`, `updateRecord()` |
| **Arrangement** | `saveArrangement()`, `getArrangement()`, `applyArrangementOrder()` |
| **Validation** | `validateTable()` (whitelist-based) |

---

## 4. Data Sources & Tables

### 4.1 The Four Staging Tables

| Table | Label in UI | Key Columns | Has `prop_id`? | Has `is_deleted`? |
|-------|-------------|------------|----------------|-------------------|
| `file_history_staging` | File History (FH) | mlsFNo, fileno, kangisFileNo, NewKANGISFileno, party_1–4, transaction_type, serialNo/pageNo/volumeNo | Yes | Yes |
| `CofO_staging` | CofO | Same file number columns + np_fileno, party_1 (limited) | Yes | Yes |
| `pra` | PRA | Same structure as FH + plot_size, property_description | Yes | Yes |
| `deed_registrations` | Deed Registration | fileno, instrument_type, grantor, grantee, serial_no/page_no/volume_no, registration_number | Yes | Yes |

### 4.2 Cross-Table Linking via `prop_id`

All 4 tables share a `prop_id` column (12-digit property identifier). After the initial file number search returns results, the system:

1. Collects all `prop_id` values from initial results
2. Queries all 4 tables again by those `prop_id` values
3. Excludes already-fetched records by ID
4. Merges the additional records into the result set

This ensures that related transactions are found even if they were recorded under different file numbers.

### 4.3 Supporting Tables

| Table | Purpose |
|-------|---------|
| `file_indexings` | File metadata (title, plot, TP no, land use, district) |
| `caveats` | Active caveat records with `caveat_number` |
| `ls_comment_staging` | Legal search comments (per file/prop_id) |
| `legal_search_timeline_arrangements` | Saved manual display ordering |

---

## 5. Search Algorithm & Flow

### 5.1 Search Parameters

The system accepts 10 search fields:

| Field | Parameter | Description |
|-------|-----------|-------------|
| File Number | `query` | Any format: ST, MLS, KANGIS, New KANGIS |
| Guarantor | `guarantorName` | Grantor/Assignor/Mortgagor/Lessor/Surrenderor |
| Guarantee | `guaranteeName` | Grantee/Assignee/Mortgagee/Lessee/Surrenderee |
| LGA | `lga` | Local Government Area |
| District | `district` | District name |
| Location | `location` | Street/address |
| Plot Number | `plotNumber` | Plot number |
| Plan Number | `planNumber` | Plan number |
| Size | `size` | Property size |
| Caveat | `caveat` | Yes/No filter |

### 5.2 The Search Pipeline

```
User Input
    │
    ▼
┌─────────────────────────────────────────┐
│ Step 1: Direct table search             │
│                                         │
│   file_history_staging ──→ FH records   │
│   CofO_staging         ──→ CofO records │
│   pra                  ──→ PRA records  │
│   deed_registrations   ──→ Deed records │
└─────────────────────┬───────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────┐
│ Step 2: Prop ID cross-table expansion   │
│                                         │
│   Collect unique prop_ids from Step 1   │
│   Query all 4 tables by prop_id         │
│   Exclude already-fetched IDs           │
│   Merge extra records into results      │
└─────────────────────┬───────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────┐
│ Step 3: Sort chronologically            │
│                                         │
│   Sort by sort_date ASC (oldest first)  │
│   Apply saved arrangement order (if any)│
└─────────────────────┬───────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────┐
│ Step 4: Return combined result set      │
│                                         │
│   transactions[] (all 4 tables merged)  │
│   file_indexing metadata                │
│   per-table counts                      │
│   aggregate file_size (via weighting!)  │
└─────────────────────────────────────────┘
```

### 5.3 File Number Filter Logic

Each table has different column names for file numbers. The `applyFilters()` method knows which columns to search per table:

| Table | File Number Columns |
|-------|--------------------|
| file_history_staging | mlsFNo, fileno, kangisFileNo, NewKANGISFileno |
| CofO_staging | mlsFNo, fileno, kangisFileNo, NewKANGISFileno |
| pra | mlsFNo, fileno, kangisFileNo, NewKANGISFileno |
| deed_registrations | fileno, parent_fileno |

All searches use `UPPER(column) LIKE UPPER(?)` with parameterized values (no SQL injection risk).

### 5.4 Soft Delete Filtering

Every query calls `applySoftDeleteFilter()`, which checks if the table has an `is_deleted` column and, if so, filters to `is_deleted = 0 OR is_deleted IS NULL`. The column existence check is cached per table name within the request lifecycle.

---

## 6. The Weighting Method — Deep Dive

### 6.1 The Problem

The same real-world transaction (e.g., "Mortgage from Musa to Safiya in January 2024") can be recorded in **both** the `file_history_staging` (FH) table **and** the `pra` table. When building a timeline or generating a print report, showing both creates noise and confusion. The system must decide which version to keep.

### 6.2 Design Principles

1. **Dedup only applies to FH vs PRA records** — CofO and Deed Registration records always pass through (they don't participate in deduplication).
2. **Fingerprint matching** identifies "same" transactions across tables.
3. **Scoring** determines which copy wins when records match.
4. **Transparency** — users can see exactly why a record was preferred via the Weighting tab.

### 6.3 The Fingerprint

A fingerprint identifies a "logical transaction". It is composed of:

```
fingerprint = transaction_type | party_1 | party_2 | party_3 | party_4 | date
```

**Critically, registration particulars (serial/page/volume) are EXCLUDED from the fingerprint.** This is intentional: two records describing the same transaction may differ in whether they have reg particulars filled in. By excluding reg particulars from the fingerprint, the system can still detect them as duplicates and then use reg particulars as a **scoring bonus**.

Normalization applied:
- Lowercase
- Collapse whitespace to single space
- Remove commas and periods

A fingerprint is only computed for FH and PRA records. If no signal exists (all of transaction_type, party_1, party_2, and date are empty), the record is treated as unique.

### 6.4 The Scoring Formula

```
Total Score = Base Score + Reg Particulars Bonus
```

#### Base Scores (Source Weight)

| Source Table | Label | Base Score |
|-------------|-------|-----------|
| CofO_staging | CofO | 4 |
| file_history_staging | File History (FH) | 3 |
| pra | PRA | 2 |
| deed_registrations | Deed | 1 |

#### Registration Particulars Bonus

| Condition | Bonus |
|-----------|-------|
| Record has non-empty, non-zero serial_no, page_no, or volume_no | **+2** |
| Record has no registration particulars | **+0** |

#### Score Scenarios

| Scenario | FH Score | PRA Score | Winner |
|----------|----------|-----------|--------|
| Both have reg particulars | 3 + 2 = **5** | 2 + 2 = **4** | FH (5 > 4) |
| Neither has reg particulars | 3 + 0 = **3** | 2 + 0 = **2** | FH (3 > 2) |
| Only PRA has reg particulars | 3 + 0 = **3** | 2 + 2 = **4** | **PRA (4 > 3)** |
| Only FH has reg particulars | 3 + 2 = **5** | 2 + 0 = **2** | FH (5 > 2) |

**Key insight:** The reg bonus allows a PRA record with registration particulars to beat an FH record without them. This is the intentional design — registration particulars represent official registration data, so a record with them is more authoritative regardless of source.

### 6.5 Dedup Algorithm (Frontend — JS)

Located in `resources/views/legal_search/js.blade.php`, function `dedupeTransactionsForTimelineAndReport()`:

```
Pass 1: Build deduped set
  For each transaction:
    1. Compute fingerprint (null for non-FH/PRA → pass through)
    2. If fingerprint is new → add to deduped set
    3. If fingerprint exists → compare totalScore:
       - Higher score wins → replaces existing in deduped set
       - Equal or lower → existing stays
    4. Track all rows per fingerprint for weighting data

Pass 2: Tag records with _dedup_status
  For each fingerprint group:
    - Single record → status = 'unique'
    - Multiple records:
      - Winner → status = 'preferred'
      - Losers → status = 'duplicate'

Output:
  - Deduped array (for Timeline and Report views)
  - window._weightingData (for Weighting tab transparency table)
  - Each raw record tagged with _dedup_status and _dedup_score
```

### 6.6 Dedup Algorithm (Backend — PHP)

Located in `app/Http/Controllers/LegalSearchController.php`, method `reportTemplateData()`:

The PHP dedup mirrors the JS logic exactly:
- Same fingerprint formula (excluding reg particulars)
- Same scoring: `$sourceBaseScore()` + `$regBonus()` = `$totalScore()`
- Applied when generating print-ready report data
- Ensures the print template only shows preferred/unique records

### 6.7 Visual Indicators

#### Source Tab Dots

In the per-source tabs (FH, PRA, etc.), each row shows a colored dot based on `_dedup_status`:

| Dot Color | Status | Meaning |
|-----------|--------|---------|
| 🟢 Green | `preferred` | This record won the dedup — appears in Timeline/Report/Print |
| 🟡 Yellow/Amber | `duplicate` | A better version exists from another source — suppressed |
| (none) | `unique` | Only copy — no dedup needed |

#### Weighting Transparency Table

A collapsible section shows the full scoring breakdown:

| Column | Description |
|--------|-------------|
| S/N | Sequential number |
| Fingerprint | `TransactionType / Party1→Party2 / Date` |
| Source | FH, PRA, CofO, Deed |
| Base Score | Source weight (1–4) |
| Reg Bonus | +2 or 0 |
| Total Score | Base + Bonus |
| Status | ✓ Preferred (green bg) or ✗ Duplicate (amber bg) |
| Record Summary | Instrument type + reg particulars |

### 6.8 Where Weighting Is Also Used: File Size Resolution

The `LegalSearchService::search()` method uses the same source weighting to resolve conflicting `size` values:

```php
$sourceScores = [
    'CofO_staging'          => 4,  // Most authoritative
    'file_history_staging'  => 3,
    'pra'                   => 2,
    'deed_registrations'    => 1,  // Least authoritative
];
```

When multiple records report different sizes, the system picks the size from the highest-scored source.

---

## 7. Cleanup Mode Operations

Per the client requirements (documented in `LEGAL_SEARCH_CLIENT_REQUIREMENTS.md`), users can clean up data across the 4 tabs before generating reports:

### 7.1 Operations

| Operation | Effect | Database Change |
|-----------|--------|-----------------|
| **Match** | Assign orphan record(s) to a prop_id group | Sets `prop_id = target_value` |
| **Drop** | Unlink record(s) from a prop_id group | Sets `prop_id = NULL` |
| **Remove** | Soft-delete record(s) | Sets `is_deleted = 1` |
| **Update** | Edit any whitelisted field on a record | Updates specific columns |

### 7.2 Security

- Table names are validated against a strict whitelist (`VALID_TABLES` constant)
- Column names for updates are validated against per-table whitelist (`EDITABLE_COLUMNS` constant)
- All operations use parameterized queries

### 7.3 Routes

```
POST /legal_search/match           → legalsearch.match
POST /legal_search/drop            → legalsearch.drop
POST /legal_search/remove          → legalsearch.remove
POST /legal_search/update          → legalsearch.update
POST /legal_search/get-record      → legalsearch.getRecord
POST /legal_search/detect-conflicts → legalsearch.detectConflicts
```

---

## 8. Arrangement (Reorder) System

### 8.1 Purpose

Auto-chronological sorting by `transaction_date` can produce incorrect ordering when:
- Some transactions lack dates
- Capture order doesn't match real-world sequence (e.g., an OP captured from lands should appear first but shows last)

### 8.2 Database Table

`legal_search_timeline_arrangements` (SQL Server):

| Column | Type | Purpose |
|--------|------|---------|
| id | bigint PK | Auto-increment |
| prop_id | varchar(20) | Property identifier |
| source_table | varchar(50) | Which staging table |
| source_id | bigint | Record ID in source table |
| display_order | int | Manual position (1-based) |
| arranged_by | bigint nullable | User who arranged |
| arranged_at | timestamp nullable | When arranged |

**Unique constraint:** `(prop_id, source_table, source_id)` — each record can only have one arrangement per property.

### 8.3 Flow

1. User views Timeline tab with chronologically sorted transactions
2. Clicks "Arrange" to enter Arrange Mode
3. Clicks rows in desired order (1, 2, 3...) or drags
4. Clicks Save — system persists to `legal_search_timeline_arrangements`
5. Future searches for the same `prop_id` apply the saved order via `applyArrangementOrder()`

### 8.4 Routes

```
POST /legal_search/save-arrangement → legalsearch.saveArrangement
POST /legal_search/get-arrangement  → legalsearch.getArrangement
```

---

## 9. Report Generation & Print

### 9.1 Report Data Pipeline

```
Frontend requests print → GET /legal-search/report-template-data?file_number=X&prop_id=Y
    │
    ▼
LegalSearchController::reportTemplateData()
    │
    ├── Searches via LegalSearchService::search()
    ├── Filters by prop_id (excludes dropped records)
    ├── Applies PHP dedup (same weighting as JS)
    ├── Resolves file metadata from file_indexings
    ├── Resolves caveat status (DB flag + mortgage-based inference)
    ├── Resolves comments from ls_comment_staging
    ├── Applies source weighting for size/land_use/location
    │
    ▼
Returns JSON: { file_number, file_title, rows[], caveat status, remarks, etc. }
    │
    ▼
Print template (HTML) renders the data client-side
```

### 9.2 Three Print Templates

| Template | Route | Watermark |
|----------|-------|-----------|
| Official | `/legal-search/print-template/official` | "OFFICIAL SEARCH REPORT" |
| Pay-per-Search | `/legal-search/print-template/onpremise` | "PAY-PER-SEARCH" |
| Online | `/legal-search/print-template/online` | "ONLINE" |

All templates are standalone HTML files in `resources/views/legal_search/templates/`.

### 9.3 Caveat Detection

The report checks three sources of encumbrance:

1. **DB flag** — Any transaction with `is_caveated = 1` → looks up `caveat_number` from `caveats` table
2. **Mortgage-based** — If the latest "Deed of Mortgage" has no subsequent "Deed of Surrender and Release" → property is under active mortgage
3. **File number search** — Falls back to searching `caveats` table by file number/prop_id if caveat_id lookup fails

Report remarks are generated based on the combination:

| Situation | Remark |
|-----------|--------|
| Active caveat + active mortgage | "Under an Active Mortgage and Caveat" |
| Active caveat only | "Under an Active Caveat (See, {caveat_number})" |
| Active mortgage only | "Under an Active Mortgage" |
| No CofO found | "Currently at the Letter of Grant stage..." |
| Clean | "Title is free from encumbrances" |

---

## 10. Frontend Architecture

### 10.1 SPA-like Page Structure

The main page (`legal_search/index.blade.php`) operates as a single-page application with view toggling:

```
#dashboard-view     → Landing with search button, stats, recent activity
    ↓ [Search Records]
#search-modal       → Overlay with 10 filter fields + live results
    ↓ [View Records]
#file-history-view  → File info + 4 source tabs + Timeline + Weighting
    ↓ [View Detailed Records]
#legal-search-report-view → Print-ready report format
```

### 10.2 Key JavaScript Functions

| Function | Lines | Purpose |
|----------|-------|---------|
| `dedupeTransactionsForTimelineAndReport()` | ~140 | Core weighting/dedup algorithm |
| `renderTransactionTables()` | ~200 | Separates records by source, populates 4 tabs |
| `renderWeightingTable()` | ~35 | Builds weighting transparency table |
| `dedupDot()` | ~10 | Returns colored dot HTML for source tab rows |
| `renderLegalSearchReport()` | ~300 | Builds the printable report view |
| `performSearch()` | ~100 | Collects filters, sends AJAX, processes response |

### 10.3 State Management

| Window Variable | Purpose |
|----------------|---------|
| `window._allRelatedTransactions` | Raw records from all 4 tables (no dedup) |
| `window._preferredRelatedTransactions` | Deduped records (for Timeline/Report) |
| `window._weightingData` | Scoring details for Weighting tab |
| `window._currentPropId` | Active property's prop_id |
| `window._currentFileNumber` | Active file number |

---

## 11. Roles & Permissions

### 11.1 Spatie Permissions

| Permission | Department | Controls |
|-----------|------------|----------|
| `Deeds - Official (for filing purpose)` | Deeds | Access to Official Legal Search |
| `Deeds - On-Premise (Pay-Per-Search)` | Deeds | Access to On-Premise Legal Search |
| `Deeds - Legal Search Reports` | Deeds | Access to Reports dashboard |

### 11.2 Access Control Pattern

Access is gated at the **menu level** via `$hasRole()` checks against the user's `assign_role` comma-separated list. The controllers themselves do **not** have middleware-level permission checks.

---

## 12. Route Map

All active routes are in `routes/app3.php`:

### Legal Search — Official

| Method | URI | Route Name |
|--------|-----|------------|
| GET | `/legal_search` | `legal_search.index` |
| POST | `/legal_search/search` | `legalsearch.search` |
| GET | `/legal_search/report` | `legal_search.report` |
| GET | `/legal_search/legal_search_report` | `legal_search.legal_search_report` |
| POST | `/legal_search/match` | `legalsearch.match` |
| POST | `/legal_search/drop` | `legalsearch.drop` |
| POST | `/legal_search/remove` | `legalsearch.remove` |
| POST | `/legal_search/update` | `legalsearch.update` |
| POST | `/legal_search/get-record` | `legalsearch.getRecord` |
| POST | `/legal_search/detect-conflicts` | `legalsearch.detectConflicts` |
| POST | `/legal_search/save-arrangement` | `legalsearch.saveArrangement` |
| POST | `/legal_search/get-arrangement` | `legalsearch.getArrangement` |
| GET | `/legal_search/comments` | `legalsearch.getComments` |
| POST | `/legal_search/comments` | `legalsearch.saveComment` |

### Print Templates (shared)

| Method | URI | Route Name |
|--------|-----|------------|
| GET | `/legal-search/online` | `legal_search.online` |
| POST | `/legal-search/online/search` | `legalsearch.online.search` |
| GET | `/legal-search/print-template/official` | `legal_search.print.official` |
| GET | `/legal-search/print-template/onpremise` | `legal_search.print.onpremise` |
| GET | `/legal-search/print-template/online` | `legal_search.print.online` |
| GET | `/legal-search/report-template-data` | `legal_search.print.data` |

### On-Premise — Pay-per-Search

| Method | URI | Route Name |
|--------|-----|------------|
| GET | `/onpremise` | `onpremise.index` |
| POST | `/onpremise/search` | `onpremise.search` |
| GET | `/onpremise/report` | `onpremise.report` |
| GET | `/onpremise/legal-search-report` | `onpremise.legal_search_report` |

### Reports

| Method | URI | Route Name |
|--------|-----|------------|
| GET | `/legalsearchreports` | `legalsearchreports.index` |

---

## 13. Evolution History

### From the Documentation Trail

1. **Original architecture** — Two fully duplicated controllers (`LegalSearchController` ~1200 lines, `OnPremiseController` ~1100 lines) with identical search logic. Total duplication: ~3,700+ lines across controllers and views.

2. **Service extraction** — `LegalSearchService` was created to centralize search, filtering, and cleanup logic. `OnPremiseController` was refactored to extend `LegalSearchController` (~20 lines now).

3. **FH-over-PRA weighting** — Initial dedup used only base source scores (FH=3 always beats PRA=2). Problem: PRA records with registration particulars were being suppressed in favor of FH records without.

4. **Reg particulars bonus** — The weighting was enhanced with a +2 bonus for records having registration particulars. Registration particulars were also removed from the fingerprint key. This allows PRA with reg data (score 4) to beat FH without (score 3).

5. **Weighting tab** — Added transparency table so users can see exactly how each duplicate group was scored and resolved.

6. **Cleanup mode** — Match/Drop/Remove/Edit operations were added to let users correct data across all 4 tables before generating reports.

7. **Arrangement system** — Persistent manual reordering was added via `legal_search_timeline_arrangements` table, allowing users to override the chronological sort for cases where dates are missing or misleading.

8. **View consolidation** — `OnPremiseController` was updated to use `viewPrefix = 'legal_search'` (same views as Official), eliminating the `onpremise/` view duplication.

---

## 14. Key Findings & Current State

### What Works Well

| Aspect | Assessment |
|--------|-----------|
| **Service architecture** | Clean separation — `LegalSearchService` handles all data access |
| **Controller inheritance** | `OnPremiseController` extends `LegalSearchController` (20 lines vs former 1100) |
| **Weighting algorithm** | Well-designed scoring with reg bonus, correctly handles edge cases |
| **Prop_id cross-expansion** | Ensures complete results even when file numbers differ across tables |
| **Cleanup mode** | Full CRUD with table/column whitelisting for security |
| **Dedup consistency** | JS and PHP implementations mirror each other |

### Remaining Concerns

| Issue | Severity | Detail |
|-------|----------|--------|
| **No controller-level auth** | Medium | Permission checks are menu-visibility only; direct URL access is unprotected |
| **No search audit logging** | High | No record of who searched what, critical for pay-per-search billing |
| **No payment verification** | High | On-Premise has no check that payment was made |
| **CofO/Deed bypass dedup** | By Design | CofO and Deed records pass through untouched (only FH vs PRA dedup) |
| **External QR API** | Low | Report QR codes use `api.qrserver.com` — external dependency |
| **Reports module incomplete** | Medium | `LegalsearchreportsController` has no real database queries |

---

## 15. Open Issues & Risks

### 15.1 Security

- **Missing middleware auth:** Both `LegalSearchController` and `OnPremiseController` rely solely on menu visibility for access control. A user with a valid session but without the required role could access these routes directly. Adding `can:` middleware to the route groups would fix this.

### 15.2 Data Integrity

- **Cleanup operations are not audit-logged:** Match/Drop/Remove/Update operations modify live staging data but don't record who changed what or when (beyond `updated_at`). Integrating `AuditService::logAction()` would close this gap.

### 15.3 Weighting Edge Cases

- **Identical scores:** When FH and PRA both have the same total score (both have reg or both lack reg), FH always wins due to higher base score. This is the intended behavior but could be documented more explicitly.
- **Three-way duplicates:** If a transaction appears in FH, PRA, and CofO simultaneously, only the FH/PRA pair is deduped. The CofO copy passes through separately, potentially creating a visible duplicate in the timeline. This is by design (CofO records are considered authoritative and independent).

### 15.4 Business Process

- **Pay-per-search has no payment gate:** The On-Premise channel is meant to be revenue-generating but has no payment verification, receipt tracking, or billing integration.
- **Online channel is external HTML:** The online search template at `docs/templates/online.html` is a static HTML file served by the controller, separate from the main Laravel application. Integration points are unclear.

---

## Appendix A: Weighting Score Quick Reference

```
┌────────────────────────┬───────┬───────┬───────┐
│ Record Type            │ Base  │ +Reg  │ Total │
├────────────────────────┼───────┼───────┼───────┤
│ CofO with reg          │   4   │  +2   │   6   │  ← Highest possible (but bypasses dedup)
│ FH with reg            │   3   │  +2   │   5   │
│ PRA with reg           │   2   │  +2   │   4   │
│ FH without reg         │   3   │  +0   │   3   │
│ PRA without reg        │   2   │  +0   │   2   │
│ Deed with reg          │   1   │  +2   │   3   │  ← Bypasses dedup
│ Deed without reg       │   1   │  +0   │   1   │  ← Lowest possible
└────────────────────────┴───────┴───────┴───────┘

Dedup rule: Only FH vs PRA records compete.
Winner: Highest total score.
Tie-break: First encountered (insertion order).
```


## Appendix B: Source Files Referenced

| File | Purpose |
|------|---------|
| `app/Services/LegalSearchService.php` | Core service (search, cleanup, arrangement) |
| `app/Http/Controllers/LegalSearchController.php` | Official channel controller + report data |
| `app/Http/Controllers/OnPremiseController.php` | Pay-per-Search (extends LegalSearchController) |
| `resources/views/legal_search/js.blade.php` | Frontend JS with dedup/weighting |
| `FH_PRA_WEIGHTING_UPDATE_PLAN.md` | Original weighting enhancement plan |
| `LEGAL_SEARCH_COMPREHENSIVE_REPORT.md` | Previous comprehensive study (March 2026) |
| `LEGAL_SEARCH_CLIENT_REQUIREMENTS.md` | Client requirements from Kunle chat |
| `database/migrations/2026_03_21_..._arrangements_table.php` | Arrangement table migration |
| `routes/app3.php` | All active legal search routes |
