# OSS OP (One Stop Shop — Occupancy Permit) System Documentation

> Comprehensive reference for the Lands One Stop Shop OP module: architecture, flows, parties, database tables, routes, and frontend logic.

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Database Tables & Columns](#database-tables--columns)
4. [Party Logic](#party-logic)
5. [Routes](#routes)
6. [Controllers](#controllers)
7. [Views & Blade Templates](#views--blade-templates)
8. [Frontend JavaScript](#frontend-javascript)
9. [Feature Flows](#feature-flows)
10. [Modal Stack & Z-Index Map](#modal-stack--z-index-map)

---

## Overview

The **Lands One Stop Shop (OSS)** is a module within the KLAES GIS EDMS Laravel 9 monolith that handles land administration application workflows, specifically Occupancy Permit (OP) processing. It supports two primary modes:

| Mode | URL Param | Description |
|---|---|---|
| **OP Change of Name** | `?type=change-of-name` | Transfers an existing OP file from one holder to another. PRA is the primary data source via `system_source = 'OSSOPCHANGEOFNAME'`. |
| **General OSS Applications** | No type filter | Covers: OP Resettlement, OP Direct Allocation, Plot Extension, Loss of Document, Change of Purpose. Stored in `oss_applications` table. |

### Key Concepts

- **PRA** (Property Registry Application) — The canonical transaction ledger. Every OP transaction (capture, transfer, commission) writes a row to `pra`.
- **File Number** — A commissioned file identifier (e.g., `RES-2026-0001`). Created via the Commission modal and stored in `fileNumber` + `mls_file_no` tables.
- **Prop ID** — 12-digit cross-table unique identifier linking PRA records, instrument_capture, fileNumber, and oss_applications.
- **Temp File Number** — System-generated temporary ID (e.g., `TEMP-...`) used before official file commissioning.
- **OP Serial Number** — The Occupancy Permit serial (e.g., `771`) used to look up existing OP records.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                          Frontend (Blade)                          │
│                                                                     │
│  applications.blade.php (OP Change of Name page)                   │
│  all_applications.blade.php (General OSS applications page)        │
│                                                                     │
│  ┌──────────────────────┐  ┌───────────────────────┐               │
│  │ Commission New File  │  │ Capture Existing File │               │
│  │ No Modal (z-50)      │  │ No Modal (z-50)       │               │
│  └──────────────────────┘  └───────────────────────┘               │
│  ┌──────────────────────┐  ┌───────────────────────┐               │
│  │ Instrument Capture   │  │ FEFR Modal            │               │
│  │ Dialog (z-1000020)   │  │ (z-1000040)           │               │
│  └──────────────────────┘  └───────────────────────┘               │
│                                                                     │
│  instruments-capture.js    global-fileno-modal.js                  │
│  commission-fileno-modal   mls_js.blade.php                        │
└────────────────────────────────┬────────────────────────────────────┘
                                 │ AJAX / Fetch
                                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      Backend (Laravel 9)                           │
│                                                                     │
│  OpResettlementApplicationController  (OP Change of Name)          │
│  ApplicationController                (General OSS Applications)   │
│  InstrumentController                 (OP Lookup, Deeds Reg)       │
│  PRA API (PraRecordService)          (Create/Update PRA rows)      │
└────────────────────────────────┬────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     SQL Server (sqlsrv)                             │
│                                                                     │
│  pra · fileNumber · mls_file_no · instrument_capture               │
│  oss_applications · customers_staging · entities_staging            │
│  file_indexings · grouping · billing                                │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Database Tables & Columns

### `pra` — Property Registry Application (Transaction Ledger)

The central record for every land transaction. Each row = one transaction event.

| Column | Purpose |
|---|---|
| `id` | Auto-increment PK |
| `prop_id` | 12-digit Property ID — links all tables |
| `mlsFNo` | MLS file number (preferred file reference) |
| `fileno` | Alternate file number |
| `temp_fileno` | Temporary file number before commissioning |
| `system_source` | Source module stamp (e.g., `OSSOPCHANGEOFNAME`) |
| `instrument_type` | Type of instrument (e.g., `Occupancy Permit (OP)`) |
| `transaction_type` | Transaction label (e.g., `Occupancy Permit (OP)`, `Transfer of Title`) |
| `op_type` | OP sub-type: `OP Resettlement` or `OP Direct Allocation` |
| `op_serial_number` | OP serial number (e.g., `771`) |
| `Grantor` / `party_1` | Party 1 name (typically `Kano State Government` for OPs) |
| `Grantee` / `party_2` | Party 2 name (allottee/holder) |
| `regNo` | Registration number |
| `serialNo` / `pageNo` / `volumeNo` | Registration book details |
| `plot_no` / `tp_no` / `lgsaOrCity` | Property location fields |
| `property_description` / `location` | Property description text |
| `land_use` / `purpose` | Land use classification and purpose |
| `transaction_date` | Date the transaction occurred |
| `created_at` / `created_by` | Audit fields |

### `fileNumber` — Commissioned File Numbers

| Column | Purpose |
|---|---|
| `id` | PK |
| `mlsfNo` | The commissioned MLS file number |
| `FileName` | File title / allottee name (UPPERCASE) |
| `tracking_id` | Links to `mls_file_no.tracking_id` |
| `temp_fileno` | Temp fileno before commission |
| `plot_no` / `tp_no` / `lga` / `location` | Property details |
| `SOURCE` | Source module (e.g., `OSS_CHANGE_OF_NAME`) |
| `commissioning_date` | When file was commissioned |
| `is_deleted` | Soft-delete flag |
| `created_by` / `created_at` | Audit |

### `mls_file_no` — File Number Metadata

| Column | Purpose |
|---|---|
| `id` | PK |
| `tracking_id` | Links to `fileNumber.tracking_id` |
| `full_file_number` | Full file number string |
| `source` | Transaction source label |
| `sub_source` | Sub-source (e.g., `OP Change of Name`) |
| `customer_type` | `Individual`, `Corporate`, or `Multiple` |
| `file_name` | Allottee/file name |
| `land_use` | Land use |
| `source_instrument_capture_id` | FK to `instrument_capture.id` |
| `source_pra_id` | FK to `pra.id` |
| `con_commissioned_at` | Commission timestamp |

### `instrument_capture` — Deeds Registration Records

| Column | Purpose |
|---|---|
| `id` | PK |
| `prop_id` | Property ID |
| `op_serial_number` | OP serial number |
| `op_type` | OP sub-type |
| `instrument_type` | Instrument type label |
| `mlsFNo` / `temp_fileno` / `fileno` | File number references |
| `party_1_name` / `party_2_name` | Party names |
| `party_1_phone` / `party_1_address` | Party 1 contact info |
| `party_2_phone` / `party_2_address` | Party 2 contact info |
| `plot_number` / `tp_no` / `survey_plan_no` | Property details |
| `property_description` / `property_location` | Location text |
| `land_use` / `purpose` / `district` / `lga` | Classifications |
| `serial_no` / `page_no` / `volume_no` | Registration book details |
| `registration_number` / `deeds_serial_no` | Registration identifiers |
| `transaction_date` / `entry_date` / `instrument_date` | Date fields |
| `created_at` | Audit |

### `oss_applications` — General OSS Application Forms

| Column | Purpose |
|---|---|
| `id` | PK |
| `application_type` | Type: `op-resettlement`, `op-direct-allocation`, `plot-extension`, `loss-of-document`, `change-of-purpose` |
| `applicant_name` | Applicant full name |
| `file_no` | Associated file number |
| `instrument_capture_id` | FK to `instrument_capture.id` |
| `plot_no` / `plan_no` / `location` | Property details |
| `phone` / `email` | Contact |
| `passport_photo` | Photo path |
| `status` | Application status |
| `captured_by` / `created_at` | Audit |

### Other Referenced Tables

| Table | Purpose |
|---|---|
| `customers_staging` | Customer records indexed by `file_number` |
| `entities_staging` | Entity records indexed by `file_number` |
| `file_indexings` | File indexing records with `file_number` / `full_file_number` |
| `grouping` | File grouping with `tracking_id` and `awaiting_fileno` |
| `billing` | Bills generated for OSS applications |

---

## Party Logic

### Occupancy Permit (OP) Parties

In OP transactions, the parties follow a strict convention:

| Role | Party | Description |
|---|---|---|
| **Party 1 / Grantor** | `Kano State Government` | Always the state government for OPs |
| **Party 2 / Grantee** | Allottee name | The person receiving the occupancy permit |

### Storage Across Tables

Party data is stored redundantly across tables for cross-module compatibility:

```
PRA table:
  ├── Grantor = "KANO STATE GOVERNMENT"
  ├── Grantee = "MUSA YAKUBU"
  ├── party_1 = "KANO STATE GOVERNMENT"
  └── party_2 = "MUSA YAKUBU"

instrument_capture table:
  ├── party_1_name = "KANO STATE GOVERNMENT"
  ├── party_2_name = "MUSA YAKUBU"
  ├── party_1_phone / party_1_address
  └── party_2_phone / party_2_address

fileNumber table:
  └── FileName = "MUSA YAKUBU"  (= Party 2 name, UPPERCASED)

mls_file_no table:
  └── file_name = "MUSA YAKUBU"
```

### Change of Name — Party Flow

When processing a Change of Name (Transfer of Title):

1. **Original OP record** (first PRA row):
   - Party 1 = Kano State Government
   - Party 2 = Original allottee (e.g., `AMINA SANI`)

2. **Transfer of Title record** (second PRA row, same `prop_id`):
   - Party 1 = Original allottee → previous Party 2 (e.g., `AMINA SANI`)
   - Party 2 = New holder (e.g., `BASHIR ZAINAB`)

3. **`FileName`** in `fileNumber` table updates to the **new holder** (Party 2 of the transfer).

### Edit Modal — Per-Transaction Targeting

The Edit modal on the OP Change of Name page shows **transaction cards** — one per PRA row for the same `prop_id`. Each card is clickable and loads its data into editable fields below. The frontend tracks `_opEditSelectedPraId` and sends it as `pra_id` in the save payload, so the backend targets the specific PRA row instead of updating all rows.

---

## Routes

All routes are in `routes/app3.php` under the `lands-one-stop-shop` prefix.

### OP Change of Name Page

| Method | URI | Controller@Method | Name |
|---|---|---|---|
| GET | `/lands-one-stop-shop/applications/op-resettlement` | `OpResettlementApplicationController@index` | `lands-one-stop-shop.applications.index` |
| PUT | `/lands-one-stop-shop/applications/op-resettlement/{id}/update-land-use` | `OpResettlementApplicationController@updateLandUse` | `lands-one-stop-shop.applications.update-land-use` |
| PUT | `/lands-one-stop-shop/applications/op-resettlement/{id}/update-details` | `OpResettlementApplicationController@updateDetails` | `lands-one-stop-shop.applications.update-details` |
| GET | `/lands-one-stop-shop/applications/op-resettlement/pra-transactions` | `OpResettlementApplicationController@praTransactions` | `lands-one-stop-shop.applications.pra-transactions` |

### General OSS Applications Page

| Method | URI | Controller@Method | Name |
|---|---|---|---|
| GET | `/lands-one-stop-shop/applications` | `ApplicationController@index` | `lands-one-stop-shop.all-applications.index` |
| POST | `/lands-one-stop-shop/applications` | `ApplicationController@store` | `lands-one-stop-shop.all-applications.store` |
| GET | `/lands-one-stop-shop/applications/{id}` | `ApplicationController@show` | `lands-one-stop-shop.all-applications.show` |
| PUT | `/lands-one-stop-shop/applications/{id}` | `ApplicationController@update` | `lands-one-stop-shop.all-applications.update` |
| DELETE | `/lands-one-stop-shop/applications/{id}` | `ApplicationController@destroy` | `lands-one-stop-shop.all-applications.destroy` |

### Application Actions

| Method | URI | Controller@Method | Name |
|---|---|---|---|
| POST | `.../applications/{id}/bill` | `ApplicationController@bill` | `...all-applications.bill` |
| GET | `.../applications/{id}/bill-status` | `ApplicationController@billStatus` | `...all-applications.bill-status` |
| GET | `.../applications/{id}/print-acknowledgement` | `ApplicationController@printAcknowledgement` | `...all-applications.print-acknowledgement` |
| GET | `.../applications/{id}/print-verification-view` | `ApplicationController@printVerificationByRecord` | `...all-applications.print-verification-view` |
| POST | `.../applications/save-recommendation` | `ApplicationController@saveRecommendation` | `...all-applications.save-recommendation` |
| POST | `.../applications/save-change-of-ownership` | `ApplicationController@saveChangeOfOwnership` | `...applications.save-change-of-ownership` |
| POST | `.../applications/save-verification` | `ApplicationController@saveVerification` | `...applications.save-verification` |
| GET | `.../applications/verification-status` | `ApplicationController@verificationStatus` | `...all-applications.verification-status` |
| GET | `.../applications/recommendation-status` | `ApplicationController@recommendationStatus` | `...all-applications.recommendation-status` |
| POST | `.../applications/print-recommendation` | `ApplicationController@printRecommendation` | `...all-applications.print-recommendation` |
| POST | `.../applications/print-change-of-ownership` | `ApplicationController@printChangeOfOwnership` | `...applications.print-change-of-ownership` |
| POST | `.../applications/print-verification` | `ApplicationController@printVerification` | `...applications.print-verification` |

### FFR (Fetch Existing File Record) Endpoints

| Method | URI | Controller@Method | Name |
|---|---|---|---|
| POST | `.../applications/save-ffr-change-of-name` | `ApplicationController@saveFfrChangeOfName` | `...applications.save-ffr-change-of-name` |
| POST | `.../applications/capture-ffr-existing` | `ApplicationController@captureFfrExisting` | `...applications.capture-ffr-existing` |
| GET | `.../applications/lookup-temp-fileno` | `ApplicationController@lookupTempFileno` | `...applications.lookup-temp-fileno` |

### Supporting Endpoints

| Method | URI | Controller@Method | Name |
|---|---|---|---|
| GET | `.../applications/instrument-captures` | `ApplicationController@searchInstrumentCaptures` | `...all-applications.instrument-captures` |
| GET | `.../applications/lookup-file-indexing` | `ApplicationController@lookupFileIndexing` | `...all-applications.lookup-file-indexing` |

### Billing

| Method | URI | Controller@Method | Name |
|---|---|---|---|
| GET | `/lands-one-stop-shop/bill` | `OpResettlementBillController@index` | `lands-one-stop-shop.bill.index` |
| GET | `/lands-one-stop-shop/bill/{id}/print` | `OpResettlementBillController@printBill` | `lands-one-stop-shop.bill.print` |

### OP Lookup (Instrument Controller)

| Method | URI | Controller@Method | Purpose |
|---|---|---|---|
| GET | `/api/instruments/lookup-op` | `InstrumentController@lookupByOpSerialNumber` | Searches `instrument_capture` + `pra` by OP serial number |
| POST | `/api/instruments/check-duplicate` | `InstrumentController@checkDuplicate` | Checks for existing instrument before saving |

### PRA API

| Method | URI | Purpose |
|---|---|---|
| POST | `/api/pra/v1/records` | Create a new PRA record |
| GET | `/api/pra/v1/records/all-by-file/{fileNo}` | Get all PRA records matching a file number |

---

## Controllers

### `OpResettlementApplicationController`

Handles the **OP Change of Name** page. Data source is `pra` table filtered by `system_source = 'OSSOPCHANGEOFNAME'`.

| Method | Purpose |
|---|---|
| `index()` | Renders `applications.blade.php` with PRA records, joining `fileNumber`, `mls_file_no`, and `instrument_capture` for full context. Resolves customer type, land use, parties, and source OP details. Card counts by land use. |
| `updateLandUse()` | Updates `land_use` on an `instrument_capture` record (from Bill modal). |
| `praTransactions()` | Returns all PRA rows for a `prop_id` / `mls_fileno` / `temp_fileno` as chronological transaction cards (used by Edit modal). |
| `updateDetails()` | Cross-table update from Edit modal. Updates: `fileNumber`, `mls_file_no`, `instrument_capture`, `pra`, `customers_staging`, `entities_staging`. Targets specific PRA row if `pra_id` is provided. |

### `ApplicationController`

Handles the **General OSS Applications** page. Data source is `oss_applications` table joined with `instrument_capture`.

| Method | Purpose |
|---|---|
| `index()` | Renders `all_applications.blade.php` with OSS application records, PRA fallback subqueries for plot/plan/location. |
| `store()` | Creates a new OSS application. |
| `show()` / `update()` / `destroy()` | Standard CRUD. |
| `bill()` | Generates an application fee bill (stored in `billing` table). |
| `billStatus()` | Returns bill payment status for a record. |
| `printAcknowledgement()` | Print view for acknowledgement receipt. |
| `saveRecommendation()` / `recommendationStatus()` / `printRecommendation()` | Recommendation workflow: save, check status, print. |
| `verificationStatus()` / `saveVerification()` / `printVerification()` / `printVerificationByRecord()` | Verification workflow. |
| `saveChangeOfOwnership()` / `printChangeOfOwnership()` | Change of ownership actions for general OSS. |
| `saveFfrChangeOfName()` | FFR "New" mode — saves Transfer of Title PRA record with new party 2 name. |
| `captureFfrExisting()` | FFR "Existing" mode — captures file number for OP not found in PRA, creates PRA record, syncs local tables. |
| `lookupTempFileno()` | Looks up a tracking_id from `grouping` table by awaiting file number. |
| `searchInstrumentCaptures()` | AJAX search for instrument_capture records. |

### `OpResettlementBillController`

| Method | Purpose |
|---|---|
| `index()` | Lists all OSS OP Application Fee bills from `billing` table. |
| `printBill()` | Print view for a bill receipt with application + capture + file number data. |

### Other Controllers

| Controller | Purpose |
|---|---|
| `PlotExtensionController` | CRUD for Plot Extension applications (type: `plot-extension`). |
| `LossOfDocumentController` | CRUD for Loss of Document applications (type: `loss-of-document`). |
| `ChangeOfPurposeController` | Change of Purpose applications. |

---

## Views & Blade Templates

### Main Pages

| File | Purpose |
|---|---|
| `applications.blade.php` | **OP Change of Name page**. Data table with commissioned OP records. Includes Commission modal, Capture Existing modal, FEFR modal, Instrument Capture dialog, Edit/Bill/Acknowledgement/Print Manager modals. |
| `all_applications.blade.php` | **General OSS Applications page**. Data table for all `oss_applications`. Includes application form modal, bill modal, verification modal, recommendation modal, change-of-ownership modal. |
| `bill.blade.php` | All OSS OP bills listing page. |
| `plot_extension.blade.php` | Plot Extension applications page. |
| `loss_of_document.blade.php` | Loss of Document applications page. |

### Partials (Modal Content)

| File | Purpose |
|---|---|
| `partials/application-form-modal.blade.php` | Create/edit OSS application form |
| `partials/bill-modal.blade.php` | Generate bill dialog |
| `partials/verification-modal.blade.php` | Verification form dialog |
| `partials/recommendation-modal.blade.php` | Recommendation form dialog |
| `partials/change-of-ownership-modal.blade.php` | Change of ownership dialog |
| `partials/acknowledgement-modal.blade.php` | Acknowledgement receipt dialog |
| `partials/print-manager-modal.blade.php` | Print manager with multiple print options |

### Print Templates

| File | Purpose |
|---|---|
| `print/bill_receipt.blade.php` | Printable bill receipt |
| `print/print_verification.blade.php` | Printable verification form |
| `print/print_change.blade.php` | Printable change of ownership form |
| `partials/print_acknowledgement.blade.php` | Printable acknowledgement receipt |
| `partials/print_recommendation.blade.php` | Printable recommendation form |

### Shared Components

| File | Purpose |
|---|---|
| `components/commission-fileno-modal-include.blade.php` | Commission New File Number modal (includes `generate_fileno/mlsfno.blade.php` + `mls_js.blade.php`) |
| `components/partials/capture-existing-fileno-modal-html.blade.php` | Capture Existing File Number modal form |

---

## Frontend JavaScript

### `public/js/instruments-capture.js`

The core JavaScript file managing the Instrument Capture / Deeds Registration dialog and all OP-related interactions.

#### Key Functions

| Function | Purpose |
|---|---|
| `openRegistrationDialog(instrumentType)` | Opens the capture dialog for a given instrument type. Sets z-index `1000020`. Handles form reset when switching types. |
| `closeRegistrationDialog()` | Closes dialog. If opened from Commission (`ossOpContext`) and not submitted, closes Commission too + shows warning. If from FFR (`ffrExistingManualRegistration`), closes Capture + FFR modals. |
| `handleSubmit(e)` | Main form submit handler. Branches by context: <br>• **OSS OP + matched record** → `continueWithFileCommissioningFromLookup()` <br>• **FFR + matched record** → Creates PRA, dispatches `ffr-existing-op-captured` event <br>• **FFR manual** → Creates PRA, dispatches event, populates Capture Existing form <br>• **Normal** → Standard instrument registration |
| `continueWithFileCommissioningFromLookup(record)` | Syncs OP to PRA, sets Commission modal fields (application type, plot, TP, location, LGA, source linkage), opens Commission modal, closes registration dialog. |
| `applyLookupRecordToForm(data)` | Auto-fills form fields after OP serial lookup returns results. Priority: `entryDate` uses `created_at` first, `transactionDate` uses `transaction_date` first. |
| `checkDuplicate()` | Checks if instrument already exists. If `data.exists === true`, locks submit button (`alreadyCapturedLock = true`). |
| `renderOpLookupResultsCards(records)` | Renders summary bar with source badges (Deeds Registration / PRA counts) + "View Results" button. |
| `_openOpLookupResultsModal(records)` | Opens SweetAlert modal with color-coded cards for each lookup result. |
| `_showSelectedRecordInline(row)` | After selecting a record, shows inline card with green "SELECTED" badge. |
| `buildPraPayloadFromExistingOp(record)` | Builds PRA API payload from an existing OP record for pre-commission sync. |
| `applySubmitButtonLabel()` | Sets submit button text based on context. Guards with `alreadyCapturedLock` to prevent re-enabling a disabled button. |
| `updateOpSubmitAvailability()` | Controls submit button enabled/disabled state for OP based on lookup result presence. Guards with `alreadyCapturedLock`. |

#### Key Flags

| Flag | Purpose |
|---|---|
| `window.ossOpContext` | `true` when dialog was opened from Commission modal's OP source flow |
| `window.ffrExistingManualRegistration` | `true` when dialog was opened from FFR Existing Capture flow |
| `window._opCaptureSubmitted` | Set `true` when OP capture completes — prevents `closeRegistrationDialog` from showing "Capture Cancelled" warning |
| `alreadyCapturedLock` | Prevents submit button from being re-enabled after duplicate/already-captured detection |
| `window.requireOpLookupForCommission` | Requires OP serial lookup before commissioning can proceed |
| `window.ossOpSubmitLabel` | Override text for the submit button (e.g., `'Capture Existing OP'`) |
| `opLookupMatchedRecord` | The record selected from OP serial lookup results |

### `applications.blade.php` Inline JS

#### FFR (Fetch Existing File Record) Functions

| Function | Purpose |
|---|---|
| `openFfrModal()` | Shows the FEFR modal (`ffrModal`) |
| `closeFfrModal()` | Hides FEFR modal, resets state |
| `ffrPickSourceFile()` | Opens Global File Number selector. On selection, calls `ffrLoadSourceFromPra()`. |
| `ffrLoadSourceFromPra(fileNo)` | AJAX call to `/api/pra/v1/records/all-by-file/{fileNo}`. Determines if OP record exists. Shows "New" and/or "Existing" mode buttons. |
| `ffrSwitchMode('new')` | Shows the New Card with Party fields. |
| `ffrOpenExistingCapture()` | Hides FFR modal, opens Capture Existing modal, opens Registration Dialog (Occupancy Permit), pre-fills file number components, disables file number fields. Also sets `ffrExistingManualRegistration = true`. |
| `ffrSaveToPra()` | FFR "New" mode submit — saves Transfer of Title to PRA. |
| `openCaptureModal()` | Opens Capture Existing File Number modal form. |
| `closeCaptureModal()` | Hides Capture Existing modal. |
| `submitCaptureForm(event)` | Handles Capture Existing form submission. Two paths: <br>• `ffr_capture_mode = 'existing-not-found'` → POST to `capture-ffr-existing` <br>• Normal → POST to `existing-file-numbers.store` |

#### Commission Functions

| Function | Purpose |
|---|---|
| `openCommissionModalForOP(config)` | Opens Commission modal with OP-specific defaults. Hides Conversion option. Sets application type and allocation source. |
| `launchChangeOfNameMode(mode)` | Entry point for Change of Name: <br>• `'existing'` → opens Commission modal directly <br>• `'new'` → opens Global File selector, then Commission modal with selected file |

#### Event Listeners

| Event | Handler | Purpose |
|---|---|---|
| `ffr-existing-op-captured` | In `DOMContentLoaded` | Populates Capture Existing form with OP data from the Registration Dialog (land use, purpose, plot, TP, location, parties, dates) |

---

## Feature Flows

### Flow 1: FileNo Commissioning (OP Change of Name)

```
User clicks "FileNo Commissioning" button
  → openCommissionModalForOP()
    → Opens Commission New File Number modal (#generateModal)
    → User selects Application Type (new/existing)
    → User selects Allocation Source → "Occupancy Permit (OP)"
      → Opens Registration Dialog (instruments-capture.js)
      → User enters OP Serial Number
      → OP Lookup runs → shows results (Deeds Registration + PRA)
      → User selects a record
      → Form auto-fills (parties, dates, property details)
      → User clicks "Continue with File Commissioning"
        → continueWithFileCommissioningFromLookup()
          → Syncs OP to PRA (creates/updates PRA record)
          → Sets pendingExistingOpPraContext
          → Populates Commission modal fields
          → Opens Commission modal
          → Closes Registration Dialog
      → User fills remaining Commission fields → Generate
        → Creates fileNumber + mls_file_no + Transfer of Title PRA row
```

### Flow 2: Fetch Existing File Record (FEFR)

```
User clicks "Fetch Existing File Record" button
  → openFfrModal()
    → FEFR Modal opens (#ffrModal, z-[1000040])
    → User clicks "Select" → ffrPickSourceFile()
      → Global File Number selector opens
      → User selects a file → callback with fileData
        → ffrLoadSourceFromPra(fileNo)
          → AJAX: GET /api/pra/v1/records/all-by-file/{fileNo}
          → Response Analysis:
            ├── OP record found    → Shows "New" mode button
            └── No OP / No PRA    → Shows "Existing" mode card
```

#### FEFR — New Mode (OP Found)

```
User clicks "New" mode button → ffrSwitchMode('new')
  → Shows party fields (Party 1 = OP Party 2, Party 2 = new holder)
  → User enters new Party 2 name
  → Clicks "Save To PRA" → ffrSaveToPra()
    → POST to save-ffr-change-of-name
    → Creates Transfer of Title PRA record
    → Toast success
    → promptForAnotherTransaction() → Yes reopens FEFR, No reloads page
```

#### FEFR — Existing Mode (No OP in PRA)

```
User clicks "Open Capture Existing File" → ffrOpenExistingCapture()
  → Hides FFR modal
  → Opens Capture Existing File Number modal
  → Opens Registration Dialog (Occupancy Permit)
    → Sets ffrExistingManualRegistration = true
    → Pre-fills file number components (prefix/year/serial) — disabled
    → User enters OP Serial Number
    → OP Lookup:
      ├── Found in Deeds Registration/PRA:
      │     → User selects record
      │     → handleSubmit() detects FFR + matched record
      │     → Creates PRA record from lookup data
      │     → Dispatches 'ffr-existing-op-captured' event
      │     → Closes Registration Dialog
      │     → Event listener populates Capture Existing form
      │     → User fills File Name and submits Capture Existing form
      │       → POST to capture-ffr-existing
      └── Not found:
            → User manually fills OP details
            → handleSubmit() detects FFR manual flow
            → Creates PRA record via POST /api/pra/v1/records
            → Dispatches 'ffr-existing-op-captured' event
            → Same flow as above → Capture Existing form submit
```

### Flow 3: General OSS Application (Non-Change-of-Name)

```
User selects application type (OP Resettlement, OP Direct Allocation, etc.)
  → Opens application form modal
  → Fills applicant details, property info, etc.
  → Submits → POST to all-applications.store
    → Creates oss_applications record
  → Actions available: Bill, Verification, Recommendation, Print
```

### Flow 4: Edit OP Change of Name Record

```
User clicks Edit button on table row
  → Opens Edit modal (SweetAlert with custom HTML)
  → Loads PRA transactions via praTransactions endpoint
  → Renders transaction cards (one per PRA row)
  → User clicks a transaction card
    → _opLoadTxIntoEditFields() populates editable fields
    → _opEditSelectedPraId is set to that PRA row's ID
  → User edits fields (instrument type, parties, property, etc.)
  → Clicks Save → saveOpEditModal()
    → PUT to update-details/{id}
      → Updates fileNumber, mls_file_no, instrument_capture, pra, customers_staging, entities_staging
      → Targets specific PRA row via pra_id parameter
```

---

## Modal Stack & Z-Index Map

Understanding the z-index hierarchy is critical to avoid modals hiding behind each other.

| Modal | Element ID | Z-Index | Purpose |
|---|---|---|---|
| Commission New File Number | `#generateModal` | `z-50` | File commissioning form |
| Capture Existing File Number | `#captureModal` | `z-50` | Capture existing file form |
| Global File Number Selector | `#global-fileno-modal` | `z-[1000100]` (set dynamically) | Reusable file picker |
| Instrument Capture Dialog | `#registration-dialog` | `z-index: 1000020` (inline style) | OP capture / deeds registration |
| FEFR Modal | `#ffrModal` | `z-[1000040]` | Fetch Existing File Record |
| SweetAlert modals | `.swal2-container` | `z-index: 10060` (default) | Confirmations, results |

### Important Rules

1. **FFR hides before opening children**: `ffrOpenExistingCapture()` hides `#ffrModal` before opening `#captureModal` and `#registration-dialog`, since FFR's z-index (1000040) is higher than both.
2. **Commission stays open**: When OP capture is launched from Commission, the Commission modal stays visible behind the Registration Dialog.
3. **Close cascade**: `closeRegistrationDialog()` closes parent modals (Commission or FFR+Capture) if the user cancels without submitting, preventing orphaned records.
4. **`alreadyCapturedLock`**: Prevents submit button from being re-enabled after duplicate detection, across `applySubmitButtonLabel()` and `updateOpSubmitAvailability()`.

---

## OP Lookup Results — Source Labels

When the OP serial number lookup returns results, they are categorized by source:

| Label | Source Table | Badge Color |
|---|---|---|
| **Deeds Registration** | `instrument_capture` | Blue (`#dbeafe` bg, `#1d4ed8` text) |
| **PRA** | `pra` | Green (`#d1fae5` bg, `#047857` text) |

Results are displayed in a summary bar with count badges and a "View Results" button that opens a SweetAlert modal with color-coded cards showing OP serial, parties, file number, registration details, and property information.

---

## Configuration & Environment

- **Database**: SQL Server via `DB::connection('sqlsrv')`
- **PRA System Source**: `'OSSOPCHANGEOFNAME'` for Change of Name records
- **File Source**: `'OSS_CHANGE_OF_NAME'` stamped on `fileNumber.SOURCE`
- **Bill Source**: `'OSS_OP_APPLICATION_FEE'` in `billing.source`
- **Sub-source**: `'OP Change of Name'` in `mls_file_no.sub_source`
- **Cache clear after changes**: `php artisan config:clear; php artisan cache:clear`
