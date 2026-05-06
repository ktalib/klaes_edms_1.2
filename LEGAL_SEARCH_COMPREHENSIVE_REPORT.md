# Comprehensive Study: Legal Search System

## Legal Search - Official (for filing purpose) & On-Premise - Pay-per-Search

**Date:** March 19, 2026  
**Scope:** Full backend + frontend architecture analysis  

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Architecture Comparison](#2-architecture-comparison)
3. [Routing & Endpoints](#3-routing--endpoints)
4. [Backend Controllers](#4-backend-controllers)
5. [Search Algorithm Deep Dive](#5-search-algorithm-deep-dive)
6. [Database Tables & Data Flow](#6-database-tables--data-flow)
7. [Frontend Architecture](#7-frontend-architecture)
8. [Report Generation](#8-report-generation)
9. [Role-Based Access Control](#9-role-based-access-control)
10. [Related Modules](#10-related-modules)
11. [Code Duplication Analysis](#11-code-duplication-analysis)
12. [Identified Issues & Recommendations](#12-identified-issues--recommendations)

---

## 1. System Overview

The KLAES GIS EDMS system provides **three legal search channels** under the **Search** module:

| Channel | Purpose | Controller | Role Required |
|---------|---------|------------|---------------|
| **Official (for filing purpose)** | Internal staff searches for land record filing | `LegalSearchController` | `Deeds - Official (for filing purpose)` |
| **On-Premise - Pay-per-Search** | Walk-in customer searches (paid service) | `OnPremiseController` | `Deeds - On-Premise (Pay-Per-Search)` |
| **Online** | External web portal (separate application) | N/A (external link) | N/A |

Additionally, a **Legal Search Reports** module provides aggregate reporting:

| Module | Controller | Role Required |
|--------|------------|---------------|
| **Legal Search Reports** | `LegalsearchreportsController` | `Deeds - Legal Search Reports` |

A **Property Records** module (`PropertySearchController`) provides a separate unified search across 4 staging tables but is architecturally independent from the legal search system.

### Key Differences Between Official and On-Premise

| Aspect | Official (for filing purpose) | On-Premise - Pay-per-Search |
|--------|-------------------------------|----------------------------|
| **Purpose** | Internal government staff searches for filing records | Commercial walk-in search service |
| **Audience** | Government employees | External customers (lawyers, property buyers, etc.) |
| **Cost Model** | No charge (internal use) | Pay-per-search (revenue generating) |
| **Report Watermark** | "OFFICIAL SEARCH REPORT FOR FILING PURPOSES" | "FOR OFFICE USE ONLY" |
| **Dashboard Features** | Official Search button, Recent Searches, Online Portal | Pay-per-Search button, Recent Searches, Online Portal |
| **Statistics** | Search trends chart | Search trends chart + revenue data |
| **Backend Logic** | Identical | Identical |
| **Data Sources** | Identical (3 core tables) | Identical (3 core tables) |

---

## 2. Architecture Comparison

### Controller Architecture

```
app/Http/Controllers/
├── LegalSearchController.php          (~1200 lines) — Official search
├── LegalSearchController_backup.php   (backup copy)
├── LegalSearchController_updated.php  (updated variant)
├── OnPremiseController.php            (~1100 lines) — Pay-per-Search
├── LegalsearchreportsController.php   (~15 lines)   — Reporting dashboard
└── PropertySearchController.php       (~350 lines)   — Separate module
```

### View Architecture

```
resources/views/
├── legal_search/                       — Official Search views
│   ├── index.blade.php                 — Main page (~850 lines)
│   ├── js.blade.php                    — Active JavaScript (~1500 lines)
│   ├── js_column_updated.blade.php     — JS variant (legacy)
│   ├── js_final.blade.php             — JS variant (legacy)
│   ├── js_fixed.blade.php            — JS variant (legacy)
│   ├── js_original.blade.php         — JS variant (legacy)
│   ├── js_pattern_fixed.blade.php    — JS variant (legacy)
│   ├── js_updated.blade.php          — JS variant (legacy)
│   ├── js_updated_columns.blade.php  — JS variant (legacy)
│   ├── index_updated.blade.php       — View variant (legacy)
│   ├── legal_search_report.php        — Report template
│   ├── report.blade.php              — Report view
│   └── style.blade.php               — CSS styles
│
├── onpremise/                          — Pay-per-Search views
│   ├── index.blade.php                — Main page
│   ├── js.blade.php                   — JavaScript
│   ├── legal_search_report.php        — Report template
│   ├── report.blade.php              — Report view
│   ├── reports.blade.php             — Reports list (placeholder)
│   └── style.blade.php               — CSS styles
│
├── legalsearchreports/                — Reports dashboard
│   └── index.blade.php               — Reports dashboard view (~426 lines)
│
└── property_search/                   — Property Records (separate module)
    ├── index.blade.php
    └── timeline.blade.php
```

### Sidebar Menu Structure

Located in `resources/views/admin/menu/partials/modules/search.blade.php`:

```
Search Module
├── Property Records              → property-search.index
├── Legal Search (submenu)
│   ├── Official (for filing purpose) → legal_search.index  [requires: Deeds - Official (for filing purpose)]
│   ├── On-Premise - Pay-per-Search   → onpremise.index     [requires: Deeds - On-Premise (Pay-Per-Search)]
│   └── Online                        → /legal-search/online (external link)
└── Legal Search Reports          → legalsearchreports.index  [requires: Deeds - Legal Search Reports]
```

---

## 3. Routing & Endpoints

### Legal Search - Official Routes

**File:** `routes/web.php` (lines ~561-567)

| Method | URI | Action | Route Name |
|--------|-----|--------|------------|
| `GET` | `/legal_search` | `LegalSearchController@index` | `legal_search.index` |
| `POST` | `/legal_search/search` | `LegalSearchController@search` | `legal_search.search` |
| `POST` | `/legal_search/search` | `LegalSearchController@search` | `legalsearch.search` (duplicate) |
| `GET` | `/legal_search/report` | `LegalSearchController@report` | `legal_search.report` |
| `GET` | `/legal_search/legal_search_report` | `LegalSearchController@legal_search_report` | `legal_search.legal_search_report` |

### On-Premise Pay-per-Search Routes

**File:** `routes/apps2.php` (lines ~361-366)

| Method | URI | Action | Route Name |
|--------|-----|--------|------------|
| `GET` | `/onpremise` | `OnPremiseController@index` | `onpremise.index` |
| `POST` | `/onpremise/search` | `OnPremiseController@search` | `onpremise.search` |
| `GET` | `/onpremise/report` | `OnPremiseController@report` | `onpremise.report` |
| `GET` | `/onpremise/reports` | `OnPremiseController@reports` | `onpremise.reports` |
| `GET` | `/onpremise/legal-search-report` | `OnPremiseController@legal_search_report` | `onpremise.legal_search_report` |

### Legal Search Reports Routes

**File:** `routes/apps2.php` (lines ~293-295)

| Method | URI | Action | Route Name |
|--------|-----|--------|------------|
| `GET` | `/legalsearchreports` | `LegalsearchreportsController@index` | `legalsearchreports.index` |

### Property Search Routes (separate module)

**File:** `routes/app3.php` (lines ~629-634)

| Method | URI | Action | Route Name |
|--------|-----|--------|------------|
| `GET` | `/property-search` | `PropertySearchController@index` | `property-search.index` |
| `GET` | `/property-search/stats` | `PropertySearchController@stats` | `property-search.stats` |
| `GET` | `/property-search/data` | `PropertySearchController@data` | `property-search.data` |
| `GET` | `/property-search/timeline` | `PropertySearchController@timeline` | `property-search.timeline` |

---

## 4. Backend Controllers

### 4.1 LegalSearchController

**File:** `app/Http/Controllers/LegalSearchController.php` (~1200 lines)

#### Public Methods

| Method | Type | Purpose |
|--------|------|---------|
| `index()` | GET | Renders dashboard with `PageTitle = 'Legal Search - Official (for filing purpose)'` |
| `search(Request $request)` | POST | Main search dispatcher — routes to specific, primary, or general search |
| `report()` | GET | Renders `legal_search.report` view |
| `legal_search_report()` | GET | Renders `legal_search.legal_search_report` view |

#### Private Helper Methods

| Method | Purpose |
|--------|---------|
| `identifyFileNumberType($fileNo)` | Categorizes file number into: `st`, `parent`, `mls`, `kangis`, `new_kangis`, or `unknown` |
| `extractParentFromSTFile($stFileNo)` | Strips unit suffix from ST file number to get parent (e.g., `ST-COM-2025-01-001` → `ST-COM-2025-01`) |
| `findMlsFileForParent($parentFileNo)` | Finds MLS file number associated with a parent via `registered_instruments` and `CofO` |
| `searchSpecificSTFile(...)` | Selective search for a specific ST unit file |
| `searchPrimaryFile(...)` | Direct search for a primary/parent file number |
| `searchGeneral(...)` | Hierarchical general search for MLS/KANGIS/name-based queries |
| `getParentFileNumbers(...)` | Resolves all parent NP file numbers matching search criteria |
| `getAllRelatedFileNumbers(...)` | Expands parents into all related file numbers (ST, MLS, KANGIS) |
| `searchPropertyRecords(...)` | Searches `property_records` table |
| `searchPropertyRecordsSelective(...)` | Selective search of `property_records` for ST-specific queries |
| `searchRegisteredInstruments(...)` | Searches `registered_instruments` table with joins |
| `searchRegisteredInstrumentsSelective(...)` | Selective search of `registered_instruments` for ST-specific queries |
| `searchCofoRecords(...)` | Searches `CofO` table |
| `searchCofoRecordsSelective(...)` | Selective search of `CofO` for ST-specific queries |

### 4.2 OnPremiseController

**File:** `app/Http/Controllers/OnPremiseController.php` (~1100 lines)

**Near-exact clone of `LegalSearchController`** with these differences:

| Aspect | LegalSearchController | OnPremiseController |
|--------|----------------------|---------------------|
| Page Title | `'Legal Search - Official (for filing purpose)'` | `'On-Premise - Pay-per-Search'` |
| View prefix | `legal_search.*` | `onpremise.*` |
| Extra method | — | `reports()` → renders `onpremise.reports` |
| Search logic | Identical | Identical |
| Helper methods | Identical | Identical |
| Database queries | Identical | Identical |

### 4.3 LegalsearchreportsController

**File:** `app/Http/Controllers/LegalsearchreportsController.php` (~15 lines)

```php
class LegalsearchreportsController extends Controller {
    public function index() {
        $PageTitle = 'Legal Search Reports';
        return view('legalsearchreports.index', compact('PageTitle', 'PageDescription'));
    }
}
```

Minimal controller — all reporting logic is handled client-side in the Blade view.

---

## 5. Search Algorithm Deep Dive

### 5.1 Search Input Parameters

Both controllers accept 10 search parameters via POST:

| Parameter | Field Name | Description |
|-----------|-----------|-------------|
| File Number | `query` | Any file number format (ST, MLS, KANGIS, New KANGIS) |
| Guarantor Name | `guarantorName` | Name of granting party |
| Guarantee Name | `guaranteeName` | Name of receiving party |
| LGA | `lga` | Local Government Area |
| District | `district` | District name |
| Location | `location` | Street/address/description |
| Plot Number | `plotNumber` | Plot number |
| Plan Number | `planNumber` | Plan number |
| Size | `size` | Property size |
| Caveat | `caveat` | Caveat status (Yes/No) |

### 5.2 File Number Type Detection

The `identifyFileNumberType()` method uses regex patterns:

```
File Type     | Pattern                                      | Example
------------- | -------------------------------------------- | -----------------------
ST Unit       | /^ST-(RES|COM|IND|AG)-\d{4}-\d+-\d+$/i      | ST-RES-2024-01-001
Parent (NP)   | /^ST-(RES|COM|IND|AG)-\d{4}-\d+$/i           | ST-RES-2024-01
MLS           | /^(COM|RES|IND|AG|CON-*)-\d{4}-\d+$/i        | COM-2022-572
KANGIS        | /^[A-Z]{4}\s?\d{5}$/i                        | KNML 00001
New KANGIS    | /^KN\d{4}$/i                                  | KN1586
Unknown       | (everything else)                             | Any name/text search
```

### 5.3 Search Flow Diagram

```
User submits search
        │
        ▼
  identifyFileNumberType(query)
        │
        ├── type = 'st'     ──→ searchSpecificSTFile()
        │                          │
        │                          ├── 1. Find subapplication by fileno
        │                          ├── 2. Find mother_application via main_application_id
        │                          ├── 3. Extract parent NP fileno, mother fileno
        │                          ├── 4. Find MLS file via findMlsFileForParent()
        │                          ├── 5. Build selective file numbers list [ST, Parent, MLS]
        │                          └── 6. Search 3 tables with SELECTIVE logic
        │                                 ├── searchPropertyRecordsSelective()
        │                                 ├── searchRegisteredInstrumentsSelective()
        │                                 └── searchCofoRecordsSelective()
        │
        ├── type = 'parent' ──→ searchPrimaryFile()
        │                          │
        │                          ├── 1. Start with [primaryFileNo]
        │                          ├── 2. Find associated MLS from registered_instruments
        │                          ├── 3. Find associated MLS from CofO
        │                          ├── 4. Find associated ST files
        │                          └── 5. Search 3 tables with ALL related file numbers
        │                                 ├── searchPropertyRecords()
        │                                 ├── searchRegisteredInstruments()
        │                                 └── searchCofoRecords()
        │
        └── type = other    ──→ searchGeneral()
                                   │
                                   ├── 1. getParentFileNumbers()
                                   │      ├── Search mother_applications
                                   │      ├── Search subapplications → mother_applications
                                   │      └── Search registered_instruments
                                   │
                                   ├── 2. getAllRelatedFileNumbers()
                                   │      ├── Child ST files
                                   │      ├── MLS files from registered_instruments
                                   │      ├── MLS files from CofO
                                   │      ├── KANGIS files from CofO
                                   │      └── New KANGIS files from CofO
                                   │
                                   └── 3. Search 3 tables with ALL expanded file numbers
                                          ├── searchPropertyRecords()
                                          ├── searchRegisteredInstruments()
                                          └── searchCofoRecords()
```

### 5.4 Selective vs General Search

**General Search** (`searchPropertyRecords`, `searchRegisteredInstruments`, `searchCofoRecords`):
- Receives a flat array of ALL related file numbers
- Iterates through all file numbers using `LIKE` matching against relevant columns
- Returns ALL matching records regardless of which specific file number matched

**Selective Search** (`*Selective` methods) — used only for ST unit file searches:
- `searchPropertyRecordsSelective`: Only searches using the parent file number (not the ST unit file) since property_records contains legacy MLS/KANGIS numbers
- `searchRegisteredInstrumentsSelective`: Complex OR logic that includes:
  - Records where `StFileNo = $stFileNo` exactly (all instrument types)
  - ST Fragmentation records where `StFileNo = $parentFileNo`
  - ST Fragmentation records where `parent_fileNo = $parentFileNo`
  - ST Fragmentation records via `mother_applications.fileno` join
  - ST Fragmentation records using the `$motherFileNo` parameter directly
- `searchCofoRecordsSelective`: Only matches `np_fileno = $parentFileNo` exactly

### 5.5 Table Fallback Logic

Both controllers include a resilience pattern for the `registered_instruments` table:

```php
$tableName = 'registered_instruments';
try {
    DB::connection('sqlsrv')->table($tableName)->limit(1)->get();
} catch (\Exception $e) {
    $tableName = 'registered_instructions';
}
```

This tests for table existence and falls back to `registered_instructions` if the primary table doesn't exist (likely a legacy migration concern).

---

## 6. Database Tables & Data Flow

### 6.1 Core Search Tables

Both Legal Search Official and On-Premise search across **3 primary tables**:

#### Table 1: `property_records`

| Column Searched | Purpose |
|----------------|---------|
| `mlsFNo` | MLS File Number |
| `kangisFileNo` | KANGIS File Number |
| `NewKANGISFileno` | New KANGIS File Number |
| `Assignor`, `Grantor`, `Mortgagor`, `Lessor`, `Surrenderor` | Guarantor party names |
| `Assignee`, `Grantee`, `Mortgagee`, `Lessee`, `Surrenderee` | Guarantee party names |
| `lgsaOrCity` | LGA |
| `location` | District/Location |
| `property_description` | Property description |
| `plot_no` | Plot Number |
| `size` | Size |
| `caveat` | Caveat status |
| `transaction_date` | Ordered by (ASC) |

**Output tag:** `record_type = 'property_records'`

#### Table 2: `registered_instruments`

Searched with JOINs to `subapplications`, `mother_applications`, and `users`:

| Column Searched | Purpose |
|----------------|---------|
| `ri.StFileNo` | ST File Number |
| `ri.parent_fileNo` | Parent File Number |
| `ri.MLSFileNo` | MLS File Number |
| `ri.KAGISFileNO` | KANGIS File Number |
| `ri.NewKANGISFileNo` | New KANGIS File Number |
| `m.np_fileno` | Mother NP File Number (via join) |
| `m.fileno` | Mother File Number (via join) |
| `ri.Grantor`, `ri.mortgagor`, `ri.assignor`, `ri.lessor`, `ri.surrenderor` | Guarantor names |
| `m.first_name`, `m.corporate_name`, `m.multiple_owners_names` | Mother application entities |
| `ri.Grantee`, `ri.mortgagee`, `ri.assignee`, `ri.lessee`, `ri.surrenderee` | Guarantee names |
| `s.first_name`, `s.corporate_name`, `s.multiple_owners_names` | Sub-application entities |
| `ri.lga` | LGA |
| `ri.district` | District |
| `ri.propertyAddress`, `ri.propertyDescription` | Location |
| `ri.plotNumber`, `ri.plotNo` | Plot Number |
| `ri.size` | Size |
| `ri.deeds_date` | Ordered by (ASC) |

**Computed columns returned:**
- `STFileNo` = `COALESCE(ri.StFileNo, s.fileno)`
- `ParentFileNo` = `COALESCE(ri.parent_fileNo, m.np_fileno)`
- `registered_by_name` = computed from `users` table via `created_by`/`updated_by`

**Output tag:** `record_type = 'registered_instruments'`

#### Table 3: `CofO`

| Column Searched | Purpose |
|----------------|---------|
| `np_fileno` | NP File Number |
| `mlsFNo` | MLS File Number |
| `kangisFileNo` | KANGIS File Number |
| `NewKANGISFileno` | New KANGIS File Number |
| `Assignor`, `Grantor`, `Mortgagor`, `Lessor`, `Surrenderor` | Guarantor names |
| `Assignee`, `Grantee`, `Mortgagee`, `Lessee`, `Surrenderee` | Guarantee names |
| `lgsaOrCity` | LGA |
| `location` | Location |
| `property_description` | Description |
| `plot_no` | Plot Number |
| `size` | Size |
| `caveat` | Caveat status |
| `transaction_date` | Ordered by (ASC) |

**Output tag:** `record_type = 'CofO'`

### 6.2 Relationship Resolution Tables

| Table | Purpose in Search |
|-------|-------------------|
| `mother_applications` | Resolves parent NP file numbers, applicant names, property details |
| `subapplications` | Links ST unit files to mother applications via `main_application_id` |
| `users` | Provides `registered_by_name` via `created_by`/`updated_by` joins |

### 6.3 Data Flow Diagram

```
Search Input
    │
    ▼
┌─────────────────────────────────────────────────┐
│  File Number Resolution                         │
│                                                 │
│  mother_applications ──→ np_fileno (parent)     │
│  subapplications     ──→ fileno (ST unit)       │
│  registered_instruments ──→ related file numbers│
│  CofO                ──→ MLS/KANGIS numbers     │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  Search Execution (parallel across 3 tables)    │
│                                                 │
│  property_records ──→ property_records[]         │
│  registered_instruments ──→ registered_instruments[] │
│  CofO ──→ cofo[]                                │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
            JSON Response:
            {
              property_records: [...],
              registered_instruments: [...],
              cofo: [...]
            }
```

---

## 7. Frontend Architecture

### 7.1 Page Structure (Both Modules)

Both the Official and On-Premise modules share an identical multi-view SPA-like architecture:

```
Page Load
    │
    ▼
Dashboard View (#dashboard-view)
    │
    ├── [Search Records] button → Opens Search Modal
    │
    ▼
Search Modal (#search-modal) — overlay
    │
    ├── Filter inputs (File Number, Guarantor, Guarantee, + dynamic filters)
    ├── Real-time AJAX search on input change
    ├── Results in Table View or Card View
    │
    ├── [View Records] on a result → Closes modal, shows File History
    │
    ▼
File History View (#file-history-view)
    │
    ├── Left panel: File Information summary
    ├── Right panel: Transaction History with 3 tabs
    │   ├── Property History (property_records)
    │   ├── Instrument Registration (registered_instruments)
    │   └── CofO
    │
    ├── [View Detailed Records] → Shows Legal Search Report
    │
    ▼
Legal Search Report View (#legal-search-report-view)
    │
    ├── Official report header with logos
    ├── Property Details section
    ├── Transaction History table
    ├── Footer with QR code, disclaimer, generated-by
    └── [Print Report] → Landscape A4 print
```

### 7.2 JavaScript Logic (js.blade.php)

Both modules include nearly identical `js.blade.php` files (~1261 lines for On-Premise, ~1500 lines for Official). Key functions:

| Function | Purpose |
|----------|---------|
| `performSearch()` | Collects all filter values, sends AJAX POST, processes results |
| `identifyFileNumberType(value)` | Client-side file number categorization (mirrors backend logic) |
| `extractFileNumbers(file)` | Categorizes all file number fields from a record into {st, parent, mls, kangis, new_kangis} |
| `cleanNumericValue(value)` | Removes `.0` suffix from numeric strings |
| `toProperCase(text)` | Converts text to title case |
| `getMappedValue(item, fieldType)` | Maps record fields to display values using comprehensive field mappings |
| `renderTableResults()` | Renders search results as table rows |
| `renderCardResults()` | Renders search results as card elements |
| `renderFileHistory()` | Populates the File Information panel and transaction tables |
| `renderTransactionTables()` | Separates results by `record_type` and renders into 3 tab tables |
| `getRelatedTransactions(file)` | Returns all search results (relies on backend hierarchical filtering) |
| `renderLegalSearchReport()` | Compiles all transactions into the printable report format |
| `printLandscapeReport()` | Opens print dialog with landscape A4 styling |
| `switchTab(tabName)` | Handles tab switching between Property History, Instruments, CofO |
| `initializeChart()` | Renders Chart.js line chart for search trends |

### 7.3 Search Results Table Columns

| # | Column | Data Source |
|---|--------|-------------|
| 1 | ST Unit FileNo | Extracted via pattern matching |
| 2 | New FileNo (NP) | Extracted via pattern matching |
| 3 | MLS File No | Extracted via pattern matching |
| 4 | KANGIS File No | Extracted via pattern matching |
| 5 | New KANGIS | Extracted via pattern matching |
| 6 | Guarantor | Mapped from multiple grantor fields |
| 7 | Guarantee | Mapped from multiple grantee fields |
| 8 | LGA | `property_lga`, `lga`, `lgsaOrCity` |
| 9 | Location | Concatenated address fields |
| 10 | Plot No | `plot_no`, `plotNo`, `plotNumber` |
| 11 | Transaction Type | `transaction_type`, `instrument_type` |
| 12 | Size | `size`, `plot_size` |
| 13 | Caveat | `caveat` |
| 14 | Actions | "View Records" button |

### 7.4 File Number Extraction Logic (Frontend)

The client-side `extractFileNumbers()` function collects all possible file number values from a record and categorizes them by pattern:

```javascript
// Looks at all these fields:
STFileNo, StFileNo, st_file_no, sub_fileno,
ParentFileNo, parent_fileNo, np_fileno, mother_np_fileno,
MLSFileNo, mlsFNo, fileNo, fileno, mother_fileno,
KANGISFileNo, kangisFileNo, KAGISFileNO,
NewKANGISFileNo, NewKANGISFileno, new_kangis_file_no

// Categorizes each by regex pattern matching
// Priority: first match wins per category
```

### 7.5 Transaction History Tabs

**Property History Tab** — Displays `property_records`:
- Columns: Date, Transaction Type, Grantor/Authority, Grantee/Recipient, Registration Particulars (Serial/Page/Volume), Size, Caveat, Comments, Actions

**Instrument Registration Tab** — Displays `registered_instruments`:
- Columns: Registration Date (+ Time), Instrument Type, Registration Particulars, Parties ("X to Y"), Registered By, Actions
- Special styling: ST Fragmentation records highlighted with yellow background and left border

**CofO Tab** — Displays `CofO` records:
- Columns: Registration Particulars, Issue Date, Holder Name, Land Use, Term, Actions

---

## 8. Report Generation

### 8.1 Legal Search Report (In-Page)

Both modules generate a printable report view within the same page:

**Header:**
- Kano State Logo + GIS Logo
- "KANO STATE GEOGRAPHIC INFORMATION SYSTEM"
- "MINISTRY OF LAND AND PHYSICAL PLANNING"
- "LEGAL SEARCH REPORT"
- Timestamp: Date and Time of generation

**Property Details Section:**
- File Numbers: NP FileNo | Unit FileNo (conditionally shown) | MLS File No | KANGIS File No | New KANGIS
- Schedule: "Kano" (hardcoded)
- Plot Number, Plan Number, Plot Description

**Transaction History Table:**
- S/N, Grantor, Grantee, Transaction Type, Date/Time, Registration Particulars, Size, Caveat, Comments
- All transactions sorted chronologically (oldest first)

**Footer:**
- Generated by: `{{ auth()->user()->first_name }}`
- QR Code (using external `api.qrserver.com` — encodes file number info)
- Disclaimer: "This Search Report does not represent consent to any transaction..."
- Contact information

### 8.2 Static Report Template

Both modules also have a static report template (`report.blade.php`) that renders as a standalone page:

**On-Premise Report:**
- Title: "OFFICIAL SEARCH REPORT FOR FILING PURPOSES"
- Contains static sample data (hardcoded transactions)
- Landscape A4 layout with print styles
- Watermark, signature block

### 8.3 Print Configuration

Both modules use **A4 Landscape** printing:
- Page size: 297mm × 210mm
- Margins: 8mm top/bottom, 12mm left/right
- Table font: 8pt with collapsed borders
- Print.js library included for enhanced printing
- `@media print` CSS hides non-report elements

### 8.4 Smart Unit FileNo Hiding in Reports

The report generation includes intelligent logic to hide Unit FileNo when a primary file number search was performed:

```javascript
const isPrimaryFileSearch = searchQuery && (
    identifyFileNumberType(searchQuery) === 'parent' ||
    identifyFileNumberType(searchQuery) === 'mls' ||
    identifyFileNumberType(searchQuery) === 'kangis' ||
    identifyFileNumberType(searchQuery) === 'new_kangis'
);

// Only show Unit Filno if:
// 1. It's a valid ST file number (subapplication), AND
// 2. The search was NOT made with primary file numbers
```

---

## 9. Role-Based Access Control

### 9.1 Permissions (Spatie)

Defined in `database/sql/klaes_db_inserts.sql` and `resources/views/admin/user_roles.sql`:

| Permission Name | Department | Priority | Category |
|-----------------|-----------|----------|----------|
| `Deeds - Official (for filing purpose)` | Deeds | High | Operations |
| `Deeds - On-Premise (Pay-Per-Search)` | Deeds | High | Operations |
| `Deeds - Legal Search Reports` | Deeds | High | Operations |

### 9.2 Menu Visibility

In `search.blade.php`, visibility is controlled by checking `$hasRole()`:

```blade
@if($hasRole('Deeds - Official (for filing purpose)') || $hasRole('Deeds - On-Premise (Pay-Per-Search)'))
    {{-- Show Legal Search submenu --}}
@endif

@if($hasRole('Deeds - Official (for filing purpose)'))
    {{-- Show Official link --}}
@endif

@if($hasRole('Deeds - On-Premise (Pay-Per-Search)'))
    {{-- Show On-Premise link --}}
@endif

@if($hasRole('Deeds - Legal Search Reports'))
    {{-- Show Reports link --}}
@endif
```

### 9.3 Access Pattern

The system uses `$hasRole()` (checking `assign_role` comma-separated list) rather than `@can` permission checks. This is consistent with the broader KLAES pattern documented in the project instructions.

**Note:** The controllers themselves do NOT have middleware-level permission checks — access control is handled only at the menu visibility level.

---

## 10. Related Modules

### 10.1 Property Records (PropertySearchController)

A separate module that provides unified search across 4 **staging** tables:
- `file_history_staging`
- `CofO_staging`
- `pra`
- `deed_registrations`

Key differences from Legal Search:
- Uses server-side DataTables pagination
- Searches staging tables (not live tables)
- Has a timeline view for chronological transaction history
- No report generation capability
- No file number type detection or hierarchical search
- Different column structure and field mappings

### 10.2 Legal Search Reports (LegalsearchreportsController)

A dashboard-style reporting module with:
- 3 view tabs: Detailed View, Summary View, Charts View
- Filter system: Date range, Report Period, Payment Status, Search Type
- Transaction table: S/N, Date, Search Parameter, Search Value, Result, LGA, Receipt No., Staff
- Export options: Print, PDF, Excel (buttons present, functionality may be client-side)
- Summary cards for search counts and revenue metrics
- Chart.js visualizations

Currently appears to use mostly client-side mock/sample data rather than live database queries.

---

## 11. Code Duplication Analysis

### Critical Finding: Near-Complete Code Duplication

`LegalSearchController` and `OnPremiseController` are **near-identical clones** (~1100-1200 lines each). The only differences are:

1. **Page title string** (`'Legal Search - Official...'` vs `'On-Premise - Pay-per-Search'`)
2. **View directory references** (`legal_search.*` vs `onpremise.*`)
3. **One extra method** in `OnPremiseController`: `reports()`

### Duplication Inventory

| Component | Official | On-Premise | Lines Duplicated |
|-----------|----------|------------|-----------------|
| Controller | `LegalSearchController.php` | `OnPremiseController.php` | ~1100 lines |
| Main View | `legal_search/index.blade.php` | `onpremise/index.blade.php` | ~750 lines |
| JavaScript | `legal_search/js.blade.php` | `onpremise/js.blade.php` | ~1261 lines |
| Styles | `legal_search/style.blade.php` | `onpremise/style.blade.php` | ~300+ lines |
| Report View | `legal_search/report.blade.php` | `onpremise/report.blade.php` | ~200+ lines |
| Report Template | `legal_search/legal_search_report.php` | `onpremise/legal_search_report.php` | ~150+ lines |

**Total estimated duplicated code: ~3,700+ lines**

Additionally, `LegalSearchController_backup.php` and `LegalSearchController_updated.php` are further copies of the same code.

### Frontend JS Duplication

The JavaScript file also duplicates the file number type detection logic that exists in the backend controller, creating a maintenance burden where changes must be synchronized:

```
Backend:  LegalSearchController::identifyFileNumberType()
Backend:  OnPremiseController::identifyFileNumberType()
Frontend: legal_search/js.blade.php::identifyFileNumberType()
Frontend: onpremise/js.blade.php::identifyFileNumberType()
```

**4 copies of the same regex-based type detection logic.**

---

## 12. Identified Issues & Recommendations

### 12.1 Issues

| # | Category | Issue | Severity |
|---|----------|-------|----------|
| 1 | **Code Duplication** | ~3,700 lines duplicated between Official and On-Premise | High |
| 2 | **No Controller Auth** | Neither controller has middleware-level permission checks | Medium |
| 3 | **Route Duplication** | `legal_search.search` and `legalsearch.search` are duplicate route names for the same endpoint | Low |
| 4 | **Legacy Files** | 7 legacy JS variants in `legal_search/` folder (`js_original`, `js_fixed`, etc.) plus 2 backup controllers | Low |
| 5 | **Table Existence Check** | Every search method tests if `registered_instruments` exists via a query — this runs on every API call | Medium |
| 6 | **No Input Validation** | Search parameters are used directly in SQL LIKE patterns without Input validation/sanitization beyond Laravel's parameter binding | Low (mitigated by parameterized queries) |
| 7 | **Hardcoded Statistics** | Dashboard statistics and recent activity data are hardcoded strings, not live data | Medium |
| 8 | **External QR Code API** | Report QR codes use `api.qrserver.com` external service — SSRF-adjacent concern and dependency | Low |
| 9 | **Reports Module** | LegalsearchreportsController has no actual database queries — appears incomplete | Medium |
| 10 | **Mixed File Extensions** | `legal_search_report.php` uses `.php` extension instead of `.blade.php` | Low |
| 11 | **No Search Logging** | Neither module logs search activity for audit purposes — critical for pay-per-search billing | High |
| 12 | **No Payment Integration** | On-Premise "Pay-per-Search" has no payment verification or receipt tracking | High |

### 12.2 Recommendations

#### High Priority

1. **Extract shared search logic into a Service class** (e.g., `LegalSearchService`) and have both controllers delegate to it. This eliminates ~1100 lines of duplicated backend code.

2. **Add middleware-level auth/permission checks** to both route groups:
   ```php
   Route::middleware(['can:Deeds - Official (for filing purpose)'])->group(...)
   Route::middleware(['can:Deeds - On-Premise (Pay-Per-Search)'])->group(...)
   ```

3. **Implement search activity logging** via `AuditService::logAction()` — essential for pay-per-search billing reconciliation and audit trails.

4. **Implement payment verification** for On-Premise searches — currently there's no check that payment was made before allowing searches.

#### Medium Priority

5. **Cache the table existence check** for `registered_instruments` vs `registered_instructions` — currently runs on every search API call.

6. **Replace hardcoded dashboard statistics** with live queries from the `user_activity_logs` or a new `search_activity_log` table.

7. **Complete the Legal Search Reports module** with actual database-backed queries and export functionality.

8. **Consolidate frontend JS** into a shared partial that both modules include, parameterized by module name.

#### Low Priority

9. **Clean up legacy files** — remove `LegalSearchController_backup.php`, `LegalSearchController_updated.php`, and the 7 legacy JS variants.

10. **Fix the duplicate route** — remove the `legalsearch.search` alias.

11. **Rename `.php` files to `.blade.php`** for consistency.

12. **Replace external QR code API** with a local QR generation library (e.g., `simplesoftwareio/simple-qrcode`).

---

## Appendix A: File Inventory

### Controllers

| File | Lines | Status |
|------|-------|--------|
| `app/Http/Controllers/LegalSearchController.php` | ~1200 | Active |
| `app/Http/Controllers/LegalSearchController_backup.php` | ~1200 | Legacy backup |
| `app/Http/Controllers/LegalSearchController_updated.php` | ~600 | Legacy variant |
| `app/Http/Controllers/OnPremiseController.php` | ~1100 | Active |
| `app/Http/Controllers/LegalsearchreportsController.php` | ~15 | Active |
| `app/Http/Controllers/PropertySearchController.php` | ~350 | Active (separate module) |

### Views — Legal Search Official

| File | Lines | Status |
|------|-------|--------|
| `resources/views/legal_search/index.blade.php` | ~850 | Active |
| `resources/views/legal_search/index_updated.blade.php` | ~800 | Legacy |
| `resources/views/legal_search/js.blade.php` | ~1500 | Active |
| `resources/views/legal_search/js_column_updated.blade.php` | ~1200 | Legacy |
| `resources/views/legal_search/js_final.blade.php` | ~1200 | Legacy |
| `resources/views/legal_search/js_fixed.blade.php` | ~1200 | Legacy |
| `resources/views/legal_search/js_original.blade.php` | ~1000 | Legacy |
| `resources/views/legal_search/js_pattern_fixed.blade.php` | ~1200 | Legacy |
| `resources/views/legal_search/js_updated.blade.php` | ~1200 | Legacy |
| `resources/views/legal_search/js_updated_columns.blade.php` | ~1200 | Legacy |
| `resources/views/legal_search/style.blade.php` | ~300 | Active |
| `resources/views/legal_search/report.blade.php` | ~300 | Active |
| `resources/views/legal_search/legal_search_report.php` | ~150 | Active |

### Views — On-Premise Pay-per-Search

| File | Lines | Status |
|------|-------|--------|
| `resources/views/onpremise/index.blade.php` | ~750 | Active |
| `resources/views/onpremise/js.blade.php` | ~1261 | Active |
| `resources/views/onpremise/style.blade.php` | ~300 | Active |
| `resources/views/onpremise/report.blade.php` | ~300 | Active |
| `resources/views/onpremise/reports.blade.php` | ~40 | Active (placeholder) |
| `resources/views/onpremise/legal_search_report.php` | ~150 | Active |

### Views — Reports & Search Menu

| File | Lines | Status |
|------|-------|--------|
| `resources/views/legalsearchreports/index.blade.php` | ~426 | Active |
| `resources/views/admin/menu/partials/modules/search.blade.php` | ~60 | Active |

---

## Appendix B: API Response Schema

### POST `/legal_search/search` and POST `/onpremise/search`

**Request Body:**
```json
{
    "_token": "csrf_token",
    "query": "COM-2022-572",
    "guarantorName": "",
    "guaranteeName": "",
    "lga": "",
    "district": "",
    "location": "",
    "plotNumber": "",
    "planNumber": "",
    "size": "",
    "caveat": ""
}
```

**Response:**
```json
{
    "property_records": [
        {
            "mlsFNo": "COM-2022-572",
            "kangisFileNo": "KNML 00123",
            "NewKANGISFileno": "KN0001",
            "transaction_type": "Transfer of Title",
            "transaction_date": "2022-01-15",
            "Assignor": "Musa Ibrahim",
            "Assignee": "Amina Sani",
            "lgsaOrCity": "Nassarawa",
            "location": "Niger Street",
            "plot_no": "GP 1067",
            "size": "0.0192",
            "caveat": "No",
            "serialNo": "1",
            "pageNo": "1",
            "volumeNo": "1",
            "record_type": "property_records"
        }
    ],
    "registered_instruments": [
        {
            "StFileNo": "ST-COM-2022-01-001",
            "ParentFileNo": "ST-COM-2022-01",
            "MLSFileNo": "COM-2022-572",
            "KANGISFileNo": "",
            "NewKANGISFileNo": "",
            "instrument_type": "ST Assignment (Transfer of Title)",
            "deeds_date": "2022-06-20",
            "deeds_time": "10:30 AM",
            "Grantor": "Amina Sani",
            "Grantee": "Yakubu Bashir",
            "registered_by_name": "Hadiza Admin",
            "np_fileno": "ST-COM-2022-01",
            "record_type": "registered_instruments"
        }
    ],
    "cofo": [
        {
            "np_fileno": "ST-COM-2022-01",
            "mlsFNo": "COM-2022-572",
            "kangisFileNo": "KNML 00123",
            "NewKANGISFileno": "KN0001",
            "transaction_type": "Certificate of Occupancy",
            "transaction_date": "2020-03-10",
            "record_type": "CofO"
        }
    ]
}
```
