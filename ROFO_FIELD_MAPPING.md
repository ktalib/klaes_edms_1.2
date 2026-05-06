# File Indexing — Complete Field Mapping

> Generated: 2026-03-27  
> Controller: `FileIndexingController.php`  
> JS: `public/js/fileindexing/create-indexing-dialog.js`  
> Form submitted as JSON via AJAX (`formData` object in JS)

---

## Table of Contents

1. [Main Record → `file_indexings`](#1-main-record--file_indexings-table)
2. [CofO Section → `CofO_staging`](#2-cofo-section--cofo_staging-table)
3. [RoFO Section → `pra`](#3-rofo-section--pra-table)
4. [Entity → `entities`](#4-entity--entities-table)
5. [Customer → `customers`](#5-customer--customers-table)
6. [Related Files → `file_indexing_links`](#6-related-files--file_indexing_links-table)
7. [Fields Not Persisted](#7-fields-not-persisted-to-any-table)
8. [Read-Back Paths (Edit Mode)](#8-read-back-paths-edit-mode)
9. [Migrations](#9-migrations)
10. [Files Involved](#10-files-involved)

---

## 1. Main Record → `file_indexings` Table

Saved via `FileIndexing::create($persistableData)` where `$persistableData = Arr::only($validated, FileIndexing::columnWhitelist())`.

### File Identification

| # | Form Label | HTML Element ID | JS `formData` Key | Validated Key | `file_indexings` Column | Notes |
|---|---|---|---|---|---|---|
| 1 | File Number | `fileno` | `file_number` | `file_number` | `file_number` | Required |
| 2 | File Title | `file-title` / `file_title[]` | `file_title` | `file_title` | `file_title` | Array in Block mode → first value used for main record |
| 3 | Current Holder | `.current-holder-input` | `current_holder` | `current_holder` | `current_holder` | Array in Block mode |
| 4 | Original Holder | `original_holder[]` | `original_holder` | `original_holder` | `original_holder` | Falls back to current_holder |
| 5 | Related File Nos | `related_fileno[]` | `related_fileno` | `related_fileno` | `related_fileno` | Stored as JSON string |
| 6 | File Type | `file_type` / `file_type[]` | `file_type` | `file_type` | `file_type` | — |
| 7 | Indexing Type | `indexing-type` | `indexing_type` | `indexing_type` | `indexing_type` | `Regular` or `Block` |

### Property Details

| # | Form Label | HTML Element ID | JS `formData` Key | Validated Key | `file_indexings` Column | Notes |
|---|---|---|---|---|---|---|
| 8 | Land Use | `land-use-type` / `land_use_type[]` | `land_use_type` | `land_use_type` | `land_use_type` | — |
| 9 | Plot Number | `plot-number` / `plot_number[]` | `plot_number` | `plot_number` | `plot_number` | — |
| 10 | TP Number | `tp-number` / `tp_number[]` | `tp_no` | `tp_no` | `tp_no` | — |
| 11 | LPKN Number | `lpkn-no` / `lpkn_no[]` | `lpkn_no` | `lpkn_no` | `lpkn_no` | — |
| 12 | Location | `location` / `location[]` | `location` | `location` | `location` | Auto-generated from plot+street+district+LGA |
| 13 | Street Name | via district resolver | `street_name` | `street_name` | `street_name` | Resolved from custom street choice |
| 14 | District | `district-select` | `district` | `district` | `district` | Resolved from custom district choice |
| 15 | LGA | `lga-city` | `lga` | `lga` | `lga` | Required for Regular indexing |

### Contact Information

| # | Form Label | HTML Element ID | JS `formData` Key | Validated Key | `file_indexings` Column | Notes |
|---|---|---|---|---|---|---|
| 16 | Date of Birth | `dob` / `dob[]` | `dob` | `dob` | `dob` | — |
| 17 | NIN | `nin` / `nin[]` | `nin` | `nin` | `nin` | — |
| 18 | TIN | `tin` / `tin[]` | `tin` | `tin` | `tin` | — |
| 19 | RC Number | `rc-no` / `rc_no[]` | `rc_no` | `rc_no` | `rc_no` | Corporate only |
| 20 | Country Code | `country-code` / `country_code[]` | `country_code` | `country_code` | `country_code` | Default: +234 |
| 21 | Phone | `phone` / `phone[]` | `phone` | `phone` | `phone` | — |
| 22 | Residence Address | `residence_address` / `residence_address[]` | `residence_address` | `residence_address` | `residence_address` | — |

### Archive / Digital Details

| # | Form Label | HTML Element ID | JS `formData` Key | Validated Key | `file_indexings` Column | Notes |
|---|---|---|---|---|---|---|
| 23 | Registry | n/a (derived) | `registry` | `registry` | `registry` | Derived from grouping |
| 24 | Physical Registry | `physical-registry` | `physical_registry` | `physical_registry` | `physical_registry` | Required |
| 25 | General Registry | `general-registry` | `general_registry` | `general_registry` | `general_registry` | Required |
| 26 | Registry Batch No | `registry-batch-no` | `registry_batch_no` | `registry_batch_no` | `registry_batch_no` | — |
| 27 | Group No | `registry-batch-no` | `group_no` | `group_no` | `group_no` | Aliased from same input as registry_batch_no; overridden from grouping |
| 28 | Serial No | `serial-no` | `serial_no` | `serial_no` | `serial_no` | — |
| 29 | Batch No | `batch-no` | `batch_no` | `batch_no` | `batch_no` | May be overridden by grouping `mdc_batch_no` |
| 30 | Shelf Location | `shelf-location` | `shelf_location` | `shelf_location` | `shelf_location` | May be overridden by grouping `shelf_rack` |
| 31 | Shelf Label ID | `shelf_label_id` | `shelf_label_id` | `shelf_label_id` | `shelf_label_id` | — |
| 32 | SYS Batch No | `sys-batch-no` | `sys_batch_no` | `sys_batch_no` | `sys_batch_no` | From grouping record |
| 33 | Shelf/Rack No | `shelf-rack-no` | `shelf_rack_no` | (extracted) | → `shelf_location` | Resolves into `shelf_location` from grouping |
| 34 | Tracking ID | `tracking-id` | `tracking_id` | `tracking_id` | `tracking_id` | From grouping record |
| 35 | Test Control | hidden input | `test_control` | `test_control` | `test_control` | System value (e.g. `PRO1.2`) |

### Flags (Checkboxes)

| # | Form Label | HTML Element ID | JS `formData` Key | Validated Key | `file_indexings` Column | Notes |
|---|---|---|---|---|---|---|
| 36 | Has CofO | `has-cofo-toggle` / `has-cofo` | `has_cofo` | `has_cofo` | `has_cofo` | Gates CofO sync |
| 37 | Has RoFO | `has-rofo-toggle` | `has_rofo` | `has_rofo` | `has_rofo` | Gates RoFO sync |
| 38 | Has Transaction | `has-transaction` | `has_transaction` | `has_transaction` | `has_transaction` | — |
| 39 | Is Problematic | `is-problematic` | `is_problematic` | `is_problematic` | `is_problematic` | — |
| 40 | Is Co-Owned Plot | `co-owned-plot` | `is_co_owned_plot` | `is_co_owned_plot` | `is_co_owned_plot` | — |
| 41 | Is Merged Plot | `merged-plot` | `is_merged` | `is_merged` | `is_merged` | — |

### Other

| # | Form Label | HTML Element ID | JS `formData` Key | Validated Key | `file_indexings` Column | Notes |
|---|---|---|---|---|---|---|
| 42 | DCIV Reason | `dciv-reason` | `dciv_reason` | `dciv_reason` | `dciv_reason` | DCIV Registry only |
| 43 | Application ID | `application_id` | `main_application_id` | `main_application_id` | `main_application_id` | — |
| 44 | Sub-Application ID | `sub_application_id` | `subapplication_id` | `subapplication_id` | `subapplication_id` | — |
| 45 | Prop ID | `prop-id-field` | `prop_id` | (handled separately) | — | Used for cross-table linking, not stored directly on file_indexings |

### System-Set Fields (not from form)

| `file_indexings` Column | Source | Notes |
|---|---|---|
| `created_by` | `$currentUserName` | Set in controller |
| `updated_by` | `$currentUserName` | Set in controller |
| `workflow_status` | `'indexed'` | Default if not set |

---

## 2. CofO Section → `CofO_staging` Table

Saved via `syncCofORecord()` / `updateCofORecord()`. Only runs when `has_cofo = true`.

| # | Form Label | HTML Element ID | JS `formData` Key | `extractCofOInput()` Key | `CofO_staging` Column | Notes |
|---|---|---|---|---|---|---|
| 1 | Instrument Type | `cofo-instrument-type` | `cofo_instrument_type` | `instrument_type` | `instrument_type`, `transaction_type`, `cofo_type` | Stored in 3 columns |
| 2 | CofO Date | `cofo-date` | `cofo_date` | `cofo_date` | `cofo_date`, `transaction_date` | Stored in 2 columns |
| 3 | CofO Number | `cofo-number` | `cofo_no` | `cofo_no` | `cofo_no` | Match key when present |
| 4 | Land Use | `cofo-land-use` | `cofo_land_use` | `land_use` | `land_use` | Falls back to `file_indexings.land_use_type` |
| 5 | Serial No | `cofo-serial-no` | `cofo_serial_no` | `serial_no` | `serialNo` | Also used in `regNo` composition |
| 6 | Page No | `cofo-page-no` | `cofo_page_no` | `page_no` | `pageNo` | Falls back to `serialNo`; readonly in UI |
| 7 | Volume No | `cofo-vol-no` | `cofo_vol_no` | `volume_no` | `volumeNo` | Used in `regNo` composition |
| 8 | Lease Period | `cofo-period` | `cofo_period` | `period` | `period` | — |
| 9 | Period Unit | `cofo-period-unit` | `cofo_period_unit` | `period_unit` | `period_unit` | Default: `Years` |
| 10 | Deeds Time | `cofo-deeds-time` | `cofo_deeds_time` | `deeds_time` | `transaction_time` | — |
| 11 | Deeds Date | `cofo-deeds-date` | `cofo_deeds_date` | `deeds_date` | (fallback for `transaction_date`) | Used if `cofo_date` is empty |
| 12 | Grantor | `cofo-first-party` | `cofo_first_party` | `first_party` | `Grantor` | — |
| 13 | Grantee | `cofo-second-party` | `cofo_second_party` | `second_party` | `Grantee` | — |

### CofO Auto-Populated Fields

| `CofO_staging` Column | Source | Notes |
|---|---|---|
| `mlsFNo` | `file_indexings.file_number` | Always set |
| `regNo` | Composed: `serialNo/pageNo/volumeNo` | Built by `formatRegistrationNumber()` |
| `property_description` | `file_indexings.location` or `district` | Fallback chain |
| `location` | `file_indexings.location` or `district` | Fallback chain |
| `plot_no` | `file_indexings.plot_number` | — |
| `lgsaOrCity` | `file_indexings.lga` | — |
| `test_control` | System value | Only if provided |
| `prop_id` | Allocated property ID | Only if provided |
| `created_by` / `created_at` | `Auth::id()` / `now()` | Insert only |
| `updated_by` / `updated_at` | `Auth::id()` / `now()` | Insert and update |

### CofO Match/Upsert Logic

1. **Primary match key**: `cofo_no` — if the form has a CofO number, match by that.
2. **Fallback match key**: `mlsFNo` — if no CofO number, match by file number.
3. If match exists → **UPDATE**; otherwise → **INSERT**.

### CofO Read-Back

CofO data is loaded via a **separate AJAX call** (`/api/cofo-record/{fileNumber}`) triggered by `autoFillCofODetailsFromAPI()` in JS. It is NOT enriched on the controller's `edit()` response (the `prepareCofODetailsForEdit()` method result is currently unused in the edit view).

---

## 3. RoFO Section → `pra` Table

Saved via `syncRofoRecord()`. Only runs when `has_rofo = true`.

| # | Form Label | HTML Element ID | JS `formData` Key | Controller Input | `pra` Column | Notes |
|---|---|---|---|---|---|---|
| 1 | **Has RoFO** (checkbox) | `has-rofo-toggle` | `has_rofo` | `has_rofo` | — (`file_indexings.has_rofo`) | Gates whether RoFO section is saved |
| 2 | Instrument Type | `rofo-instrument-type` | `rofo_instrument_type` | `rofo_instrument_type` | `instrument_type` | Also into `transaction_type` |
| 3 | RoFO Date | `rofo-date` | `rofo_date` | `rofo_date` | `transaction_date` | — |
| 4 | RoFO Number | `rofo-number` | `rofo_number` | `rofo_number` | `rofo_number` | Custom column (migration). Match key. |
| 5 | File Number | `rofo-file-number` | `rofo_file_number` | `rofo_file_number` | `mlsFNo` | Readonly, auto-populated. Fallback match key. |
| 6 | Land Use | `rofo-land-use` | `rofo_land_use` | `rofo_land_use` | `land_use` | Falls back to `file_indexings.land_use_type` |
| 7 | Grantor | `rofo-grantor` | `rofo_grantor` | `rofo_grantor` | `party_1` | Default: "KANO STATE GOVERNMENT" (readonly) |
| 8 | Grantee | `rofo-grantee` | `rofo_grantee` | `rofo_grantee` | `party_2` | Free-text |

### RoFO Auto-Populated Fields

| `pra` Column | Source | Notes |
|---|---|---|
| `transaction_type` | Same as `instrument_type` | Duplicated |
| `property_description` | `file_indexings.location` or `district` | Fallback chain |
| `location` | `file_indexings.location` or `district` | Fallback chain |
| `plot_no` | `file_indexings.plot_number` | — |
| `lgsaOrCity` | `file_indexings.lga` | — |
| `test_control` | System value | Only if provided |
| `prop_id` | Allocated property ID | Only if provided |
| `created_by` / `created_at` | `Auth::id()` / `now()` | Insert only |
| `updated_by` / `updated_at` | `Auth::id()` / `now()` | Insert and update |

### RoFO Match/Upsert Logic

1. **Primary match key**: `rofo_number` — if form has a RoFO number, match by that.
2. **Fallback match key**: `mlsFNo` — if no RoFO number, match by file number.
3. If match exists → **UPDATE**; otherwise → **INSERT**.

### RoFO Read-Back

Enriched on the record in **two** scenarios via `prepareRofoDetailsForEdit()`:

| Scenario | Method | Trigger |
|---|---|---|
| Edit page load | `edit()` → `prepareRofoDetailsForEdit()` | `$record->has_rofo`; added to `$record` → Blade `@json` |
| AJAX indexed check | `checkIndexed()` → `prepareRofoDetailsForEdit()` | `$record->has_rofo`; merged into JSON response |

| `pra` Column Read | Returned As | JS Element ID |
|---|---|---|
| `instrument_type` | `rofo_instrument_type` | `rofo-instrument-type` |
| `transaction_date` | `rofo_date` | `rofo-date` |
| `rofo_number` | `rofo_number` | `rofo-number` |
| `mlsFNo` | `rofo_file_number` | `rofo-file-number` |
| `land_use` | `rofo_land_use` | `rofo-land-use` |
| `party_1` | `rofo_grantor` | `rofo-grantor` |
| `party_2` | `rofo_grantee` | `rofo-grantee` |

---

## 4. Entity → `entities` Table

Saved via `syncEntityAndCustomer()` (store) / `updateEntityAndCustomerRecords()` (update).  
Entity data is collected by `collectEntityDetailsForSubmit()` in JS and sent as `entity_details` object.

| # | Form Label | HTML Element ID | JS Key (in `entity_details`) | Controller Key | `entities` Column | Notes |
|---|---|---|---|---|---|---|
| 1 | Entity ID | `entity-id` | `entity_id` | `entity_id` | `id` (lookup) | Used for finding existing entity |
| 2 | Entity Type | radio `entity_type` | `entity_type` | `entity_type` | `entity_type` | Default: `Individual` |
| 3 | Entity Name | `entity-name` | `entity_name` | `entity_name` | `entity_name` | Falls back to `file_title` |
| 4 | Physical Address | `entity-physical-address` | `physical_address` | (not persisted directly) | — | Collected but not saved to `entities` table |

### Entity Auto-Set Fields

| `entities` Column | Source | Notes |
|---|---|---|
| `file_number` | `file_indexings.file_number` | Always set |
| `test_control` | System value | Only if provided |

### Entity Match Logic

1. If `entity_id` present → find by ID.
2. Else → `firstOrNew` by `file_number`.

---

## 5. Customer → `customers` Table

Saved via `syncEntityAndCustomer()` (store) / `updateEntityAndCustomerRecords()` (update).  
Customer data is collected by `collectCustomerDetailsForSubmit()` in JS and sent as `customer_details` object.

| # | Form Label | HTML Element ID | JS Key (in `customer_details`) | Controller Key | `customers` Column | Notes |
|---|---|---|---|---|---|---|
| 1 | Customer ID | `customer-id` | `customer_id` | `customer_id` | `id` (lookup) | Used for finding existing customer |
| 2 | Customer Type | radio `customer_type` | `customer_type` | `customer_type` | `customer_type` | Default: `Individual` |
| 3 | Customer Name | `customer-name` | `customer_name` | `customer_name` | `customer_name` | Falls back to entity name → file title |
| 4 | Account No | `customer-account-no` | `account_no` | `account_no` | `account_no` | — |
| 5 | Customer Code | `customer-code` | `customer_code` | `customer_code` | `customer_code` | — |
| 6 | Email | `customer-email` | `email` | `email` | `email` | — |
| 7 | Phone | `customer-phone` | `phone` | `phone` | `phone` | — |
| 8 | Property Address | `customer-property-address` | `property_address` | `property_address` | `property_address` | — |
| 9 | Status | `customer-status` | `status` | `status` | `status` | Default: `Active` |
| 10 | Retired By | `customer-retired-by` | `retired_by` | `retired_by` | `retired_by` | — |
| 11 | Reason Retired | `customer-reason-retired` | `reason_retired` | `reason_retired` | `reason_retired` | — |

### Customer Auto-Set Fields

| `customers` Column | Source | Notes |
|---|---|---|
| `file_number` | `file_indexings.file_number` | Always set |
| `entity_id` | Linked entity ID | From entity sync above |
| `test_control` | System value | Only if provided |
| `created_by` | `Auth::id()` | Insert only |
| `updated_by` | `Auth::id()` | Insert and update |

### Customer Match Logic

1. If `customer_id` present → find by ID.
2. Else → `firstOrNew` by `file_number` + `customer_name`.

---

## 6. Related Files → `file_indexing_links` Table

In **Block** indexing mode, links are created inside the DB transaction.  
In **Regular** mode, links are synced via `syncRelatedFileLinks()` after the transaction.

### Main File Links (Block mode, `mfile = 1`)

| `file_indexing_links` Column | Source |
|---|---|
| `file_indexing_id` | Parent `file_indexings.id` |
| `file_number` | Main file number |
| `file_title` | From `file_title[]` array per index |
| `mfile` | `1` |
| `indexing_type` | `Block` |
| `location`, `land_use_type`, `district`, `lga`, `plot_number`, `plot_size`, `tp_no`, `lpkn_no` | From form arrays per index |
| `dob`, `nin`, `tin`, `rc_no`, `phone`, `email`, `residence_address`, `country_code` | From form arrays per index |
| `created_by`, `updated_by` | `Auth::id()` |

### Related File Links (Block mode, `mfile = 0`)

| `file_indexing_links` Column | Source |
|---|---|
| `file_indexing_id` | Parent `file_indexings.id` |
| `file_number` | From related detail modal |
| `file_title` | From related detail modal |
| `mfile` | `0` |
| `indexing_type` | `Block` |
| `location`, `plot_number`, `plot_size`, `tp_no`, `lpkn_no` | From related detail modal |
| `district`, `lga`, `land_use_type` | From modal or falls back to validated main values |
| `dob`, `nin`, `tin`, `rc_no`, `phone`, `email`, `residence_address`, `country_code` | From modal or validated |
| `created_by`, `updated_by` | `Auth::id()` |

---

## 7. Fields Not Persisted to Any Table

These fields are sent in the JS `formData` but are **not saved** to any table (they are display-only, intermediary, or consumed without storage):

| JS `formData` Key | HTML Element ID | Purpose |
|---|---|---|
| `plot_size` | `plot-size` / `plot_size[]` | **Not in `file_indexings` fillable/whitelist**. Only used in Block `file_indexing_links`. Not persisted for Regular indexing. |
| `indexed_by` | `indexed-by` | Display-only; set from current user. Not stored. |
| `indexed_date` | `indexed-date` | Display-only; set from current timestamp. Not stored. |
| `archive_file_no` | `archive-file-no` | Intermediary grouping reference. Not stored. |
| `archive_location` | `archive-location` | Intermediary grouping reference. Not stored. |
| `batch_id` | `batch_id` | Sent but not validated or persisted. |
| `mdc_batch_no` | `mdc-batch-no` | Resolved into `batch_no` from grouping. Not stored separately. |
| `lga_id` | n/a | Sent for internal use. Not stored. |
| `district_id` | n/a | Sent for internal use. Not stored. |
| `custom_district` | n/a | Resolved into `district`. Not stored separately. |
| `custom_street_name` | n/a | Resolved into `street_name`. Not stored separately. |
| `skip_entity_customer_updates` | n/a | Controls whether entity/customer sync runs on edit. |
| `source_file_id` | n/a | Extracted and used for file number tracking; not stored on `file_indexings`. |
| `grouping_match_id` | `grouping-id` | Used for grouping lookup; not stored on `file_indexings`. |

--- 
## 8. Read-Back Paths (Edit Mode)

### `file_indexings` Fields
Loaded directly from `DB::table('file_indexings')->where('id', $id)->first()` in `edit()` method. Passed to Blade via `@json($record)`.

### CofO Fields
Loaded via **separate AJAX call** from JS: `autoFillCofODetailsFromAPI()` → `GET /api/cofo-record/{fileNumber}`. Not enriched on the controller edit response.

### RoFO Fields
Enriched on `$record` in the `edit()` method via `prepareRofoDetailsForEdit()` from `pra` table. Also enriched in `checkIndexed()` response.

### Entity & Customer Fields
Stored on `file_indexings` table directly (`entity_type`, `entity_name`, `entity_id`, `customer_type`, `customer_name`, `customer_id`, etc.) and returned in the `checkIndexed()` JSON response.

---

## 9. Validation Rules

### Validated in Both `store()` and `update()`

All `file_indexings` fields and RoFO fields are validated via `$request->validate()`.

### NOT Formally Validated (read via `$request->input()` directly)

CofO fields (`cofo_instrument_type`, `cofo_date`, `cofo_no`, `cofo_serial_no`, `cofo_page_no`, `cofo_vol_no`, `cofo_deeds_time`, `cofo_deeds_date`, `cofo_first_party`, `cofo_second_party`, `cofo_land_use`, `cofo_period`, `cofo_period_unit`) are extracted via `extractCofOInput()` using `$request->input()` without formal validation rules. This is a pre-existing pattern — CofO data goes to `CofO_staging`, not `file_indexings`.

---

## 10. Migrations

| Migration File | Column Added | Table | Type |
|---|---|---|---|
| `2026_03_27_010912_add_has_rofo_to_file_indexings_table.php` | `has_rofo` | `file_indexings` | `boolean, default false` |
| `2026_03_27_020000_add_rofo_number_to_pra_table.php` | `rofo_number` | `pra` | `varchar(255), nullable` |

---

## 11. Files Involved

| File | Role |
|---|---|
| `resources/views/fileindexing/addons/create_indexing.blade.php` | Main form view; includes all partials |
| `resources/views/fileindexing/addons/partials/sections/rofo_details.blade.php` | RoFO form section |
| `resources/views/fileindexing/addons/partials/sections/cofo_details.blade.php` | CofO form section |
| `resources/views/fileindexing/addons/partials/sections/entity_customer.blade.php` | Entity & Customer section |
| `public/js/fileindexing/create-indexing-dialog.js` | Form submission, toggle/clear/edit-mode logic |
| `app/Http/Controllers/FileIndexingController.php` | Validation, store, update, sync methods |
| `app/Models/FileIndexing.php` | Eloquent model with `$fillable`, `columnWhitelist()`, `$casts` |
