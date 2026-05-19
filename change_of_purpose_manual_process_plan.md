# Implementation Plan — Change of Purpose Manual Processing

This document provides the exact technical blueprint and database change proposals for manually processing the Change of Purpose (COP) conversion from **`CON-RES-2000-158`** (Old File) to **`CON-COM-2026-302`** (New File).

---

## 1. Current Database State (Pre-Change Inspection)

We have verified the exact records in all target tables inside the `sqlsrv` database:

### Active Tables

1. **`fileNumber` (`FN`)**
   * **Old File:** ID `13731` | `mlsfNo = 'CON-RES-2000-158'` | FileName: `ALHAJI GARBA INDABAWA` | `is_decommissioned = 0`
   * **New File:** ID `104257` | `mlsfNo = 'CON-COM-2026-302'` | FileName: `Alhaji Garba Wandawa` | `is_decommissioned = 0`
2. **`customers_staging` (`CS`)**
   * **Old File:** ID `185446` | `file_number = 'CON-RES-2000-158'` | CustomerName: `ALHAJI GARBA INDABAWA`
   * **New File:** ID `264673` | `file_number = 'CON-COM-2026-302'` | CustomerName: `Alhaji Garba Wandawa`
3. **`entities_staging` (`EN`)**
   * **Old File:** ID `172922` | `file_number = 'CON-RES-2000-158'` | EntityName: `ALHAJI GARBA INDABAWA`
   * **New File:** ID `251200` | `file_number = 'CON-COM-2026-302'` | EntityName: `Alhaji Garba Wandawa`
4. **`file_indexings` (`FI`)**
   * **Old File:** ID `43192` | `file_number = 'CON-RES-2000-158'` | Title: `ALHAJI GARBA INDABAWA` | `land_use_type = 'RESIDENTIAL'` | `tp_no = 'TP-UDB-73'` | Location: `11, MAKAFIN DALA, Dala`
   * **New File:** ID `120691` | `file_number = 'CON-COM-2026-302'` | Title: `Alhaji Garba Wandawa` | `land_use_type = 'Commercial'` | Location: `Makafin Dala`

### Audit & Log Tables

1. **`change_of_purpose_applications`**: No record currently exists for either file number.
2. **`pra`**: No record currently exists for either file number.
3. **`decommissioned_files`**: No record currently exists for the old file number.

### Transaction & Legal Status
We have run a dynamic SQL scan across every transaction, deed, mortgage, caveat, and consent table in the database to verify if `CON-RES-2000-158` has any historical transactions:
* **`pra` (Property Registration & Allocation):** 0 matches
* **`deeds_applications` / `deed_registrations`:** 0 matches
* **`consent_applications`:** 0 matches
* **`caveats`:** 0 matches
* **`st_deeds` / `st_cofo` / `st_file_numbers`:** 0 matches
* **Conclusion:** The old file has **zero (0) active transactions**, making it completely safe to decommission without breaking history or foreign key integrity.

---

## 2. Proposed Changes & Action Steps

To securely and cleanly mimic the standard system automation manually, we propose the following sequential steps, wrapped in a single database transaction:

### Step 1: Create Application Record
Create a completed application record in the `change_of_purpose_applications` table matching the historical creation of the new file number (**May 13, 2026**).

```sql
INSERT INTO change_of_purpose_applications (
    file_no, land_use, purpose, plot_no, plan_no, location, 
    applicant_name, status, remarks, captured_by, updated_by, 
    created_at, updated_at, is_deleted
) VALUES (
    'CON-RES-2000-158',
    'Residential',
    'COM',
    '11',
    'TP-UDB-73',
    '11, MAKAFIN DALA, Dala',
    'Alhaji Garba Wandawa',
    'commissioned',
    'Manual Fix: Pre-system Change of Purpose completed on May 13, 2026.',
    2, -- System Admin ID
    2, -- System Admin ID
    '2026-05-13 15:25:34',
    '2026-05-13 15:25:34',
    0
);
```

### Step 2: Archive Indexing Record (`deprecated_records`)
To prevent data loss, the active indexing record of the decommissioned file will be cloned into the `deprecated_records` table, following standard system safety patterns.

```sql
INSERT INTO deprecated_records (
    file_indexing_id, file_number, file_title, land_use_type, plot_number, 
    district, lga, location, tp_no, tracking_id, original_holder, 
    current_holder, workflow_type, decommissioned_by, decommissioned_at, 
    created_by, updated_by, serial_no, batch_no, workflow_status, 
    registry, created_at, updated_at
) VALUES (
    43192,
    'CON-RES-2000-158',
    'ALHAJI GARBA INDABAWA',
    'RESIDENTIAL',
    '11',
    'MAKAFIN DALA',
    'Dala',
    '11, MAKAFIN DALA, Dala',
    'TP-UDB-73',
    'TRK-44HQ1JBH-CRJXQ',
    'ALHAJI GARBA INDABAWA',
    'ALHAJI GARBA INDABAWA',
    'Change of Purpose to CON-COM-2026-302 (Manual Fix)',
    'System Admin',
    '2026-05-13 15:25:34',
    'NURADDEEN ALHASSAN',
    'NURADDEEN ALHASSAN',
    '3790158',
    '669',
    'indexed',
    '3',
    '2025-12-09 09:45:40.303',
    '2025-12-09 09:45:40.303'
);
```

### Step 3: Decommission Old File (`decommissioned_files`)
Record the decommission audit trail for the old file number.

```sql
INSERT INTO decommissioned_files (
    file_number_id, file_no, mls_file_no, file_name, 
    decommissioning_date, decommissioning_reason, decommissioned_by, 
    created_at, updated_at
) VALUES (
    13731,
    'CON-RES-2000-158',
    'CON-RES-2000-158',
    'ALHAJI GARBA INDABAWA',
    '2026-05-13 15:25:34',
    'Change of Purpose to CON-COM-2026-302 (Manual Fix)',
    'System Admin',
    '2026-05-13 15:25:34',
    '2026-05-13 15:25:34'
);
```

### Step 4: Decommission (Hard Delete) from Active Tables
Remove the decommissioned file's details from all active lists to prevent duplicates and route indexing correctly.

```sql
DELETE FROM fileNumber WHERE mlsfNo = 'CON-RES-2000-158';
DELETE FROM customers_staging WHERE file_number = 'CON-RES-2000-158';
DELETE FROM entities_staging WHERE file_number = 'CON-RES-2000-158';
DELETE FROM file_indexings WHERE file_number = 'CON-RES-2000-158';
```

### Step 5: Link New File Indexing (`related_fileno`)
Update the active indexing record for the new file number to point to its historical predecessor in a JSON-encoded array.

```sql
UPDATE file_indexings 
SET related_fileno = '["CON-RES-2000-158"]' 
WHERE file_number = 'CON-COM-2026-302';
```

### Step 6: Create Property Registration & Allocation Record (`pra`)
Create the official historical registry transaction record inside the `pra` table linking both file numbers.

```sql
INSERT INTO pra (
    mlsFNo, fileno, resolved_fileno, temp_fileno, title_type, 
    transaction_type, transaction_date, Assignor, Assignee, 
    property_description, plot_no, location, lgsaOrCity, tp_no, 
    land_use, comments, related_file_number, created_by, updated_by, 
    created_at, updated_at
) VALUES (
    'CON-COM-2026-302',
    NULL,
    NULL,
    NULL,
    'Change Of Purpose',
    'Change Of Purpose',
    '2026-05-13 15:25:34',
    'ALHAJI GARBA INDABAWA',
    'Alhaji Garba Wandawa',
    'Makafin Dala',
    'Piece Of Land',
    'Makafin Dala',
    'Dala',
    'TP-UDB-73',
    'Commercial',
    'Old File Number: CON-RES-2000-158
New File Number: CON-COM-2026-302',
    'CON-RES-2000-158',
    2, -- System Admin ID
    2, -- System Admin ID
    '2026-05-13 15:25:34',
    '2026-05-13 15:25:34'
);
```

---

## 3. Expected Results After Execution

1. **Clean Registry:** Search queries for `CON-RES-2000-158` will correctly direct to the decommissioned archives rather than displaying duplicate active entries.
2. **Seamless Navigation:** Opening `CON-COM-2026-302` in the digital file viewer will show its relation to the legacy file number `CON-RES-2000-158` via the JSON linkage.
3. **Full Audit Compliance:** The `change_of_purpose_applications` and `pra` tables will contain clear, timestamp-aligned records of the manual conversion completed on **May 13, 2026**.
4. **Safety Retention:** All details of the old indexing record `43192` will be preserved in the `deprecated_records` history table.

---

## 4. Transaction Execution Log (Completed)

The manual Change of Purpose process was executed flawlessly within a single database transaction on **May 18, 2026 at 18:03:48**:

* **Step 1 (Application):** Inserted completed application record into `change_of_purpose_applications` &rarr; **Generated ID: `2`**
* **Step 2 (Archiving):** Cloned active indexing record `43192` into `deprecated_records` table &rarr; **Success**
* **Step 3 (Audit Decommission):** Recorded decommissioning record in `decommissioned_files` &rarr; **Generated ID: `1010`**
* **Step 4 (Active Removal):** Hard deleted the decommissioned file from active tables:
  * `fileNumber` (1 row deleted)
  * `customers_staging` (1 row deleted)
  * `entities_staging` (1 row deleted)
  * `file_indexings` (1 row deleted)
* **Step 5 (JSON Linkage):** Updated the active indexing record `120691` for `CON-COM-2026-302` setting `related_fileno = '["CON-RES-2000-158"]'` &rarr; **Success**
* **Step 6 (Registry Log):** Recorded the transaction inside the `pra` table, inserting the new file number exclusively into the `mlsFNo` column as directed &rarr; **Success**
* **Cache Clean:** Refreshed configuration and cache sequences successfully.
