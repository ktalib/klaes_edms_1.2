# Study: KANGIS Indexing and Tracking System

This report summarizes the implementation and usage of KANGIS-specific indexing fields and the tracking ID system within the KLAES GIS EDMS project.

## 1. KANGIS Indexing Workflow
KANGIS (Kano Geographical Information System) indexing handles property files belonging to the KANGIS registry. It is distinguished from regular lands indexing by its specific file numbering patterns and mandatory metadata.

### Key File Number Patterns
The system automatically detects the KANGIS registry if the file number starts with:
- `KNML`
- `MLKN`
- `MNKL`
- `KNGP`

## 2. Tracking ID (`tracking_id`)
The `tracking_id` is a primary identifier used for physical file tracking.

- **Format**: `TRK-{8_CHAR_ALPHA}-{5_CHAR_ALPHA}` (e.g., `TRK-49F86DA-8EC2C`).
- **Generation**: 
    - **Frontend**: Generated in `FileIndexDialog_js.blade.php` using `generateRandomAlphanumeric`.
    - **Purpose**: Acts as a "temp" or "internal" identifier that remains constant even if the file number is changed or corrected during the indexing/scanning lifecycle.
- **UI Display**: Shown in a dedicated field (often red) to ensure staff can link physical barcodes/labels to the digital record accurately.

## 3. KANGIS File Number Placeholder (`kangis_fileno_placeholder`)
The placeholder field is used to store the user-provided KANGIS file number before formal resolution.

- **Assembly**: Assembled in the frontend from a **Prefix** select (KNML, MLKN, etc.) and a **Serial** input (e.g., 0001).
- **Persistence**: Saved to the `file_indexings` table.
- **Validation**: In `FileIndexingController`, this field is **mandatory** if the `general_registry` is set to "KANGIS Registry".
- **Purpose**: Captures the physical label's text exactly as it appears on the legacy folder.

## 4. KANGIS File Number Resolved (`kangis_fileno_resolved`)
The resolved field represents the "Canonical" or "Final" KANGIS file number.

- **Logic**: 
    - When a record is saved, the system checks the `kangis_fileno_placeholder` against existing legacy records.
    - If a match is confirmed, the confirmed number is saved as `kangis_fileno_resolved`.
    - If no legacy match exists, the placeholder value may be promoted to resolved status once verified by a supervisor.
- **Usage**: Used for cross-referencing between the new EDMS (`file_indexings`) and the legacy KANGIS data tables (`fileNumber`).

## 5. New KANGIS Files (KN Series)
A special "New KANGIS File" mode exists for the `KN` series (e.g., `KN 1234`).
- **`is_new_kangis_file`**: A flag indicating this is a fresh entry in the KN series rather than a legacy lookup.
- **`new_kangis_file_no`**: Stores the new KN sequence number.
- **Integration**: In `new_kn` mode, the system bypasses placeholder/resolved logic and treats the KN number as the primary `file_number`.

## 6. Database Schema Summary
The following table shows the primary locations of these fields:

| Field | Table | Type | Purpose |
| :--- | :--- | :--- | :--- |
| `tracking_id` | `file_indexings`, `fileNumber` | `string` | Unique tracking identifier |
| `kangis_fileno_placeholder` | `file_indexings`, `fileNumber` | `string` | Raw input from physical folder |
| `kangis_fileno_resolved` | `file_indexings`, `fileNumber` | `string` | Final validated file number |
| `kangis_file_type` | `file_indexings` | `string` | Specific KANGIS category |
| `new_kangis_file_no` | `file_indexings` | `string` | New KN series number |

---
*Report generated on May 2, 2026*
