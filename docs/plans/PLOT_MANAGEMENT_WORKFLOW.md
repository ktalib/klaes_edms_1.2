# **Plot Management Technical Workflow**

This document defines the system logic for handling changes to plot structures (Subdivision, Merger, Extension). The primary goals are to maintain historical integrity via the **Property ID (PropID)** system and prevent data duplication in active registry tables.

---

## **1. Core Principles**

1.  **Zero Duplication**: Active tables (`FileNumber`, `File Indexing`, `Customers Staging`, `Entities Staging`) must never contain more than one record for a physical land entity at any given time.
2.  **Hard Deletion on Move**: When a record is "retired" due to a plot modification, it is **permanently deleted** from the active tables after being archived.
3.  **Decommissioned Archive**: All retired/deleted records must be moved to the `decommissioned_files` table to preserve a historical audit trail of the registry state.
4.  **PropID as the Thread**: All historical transactions (Deeds, CofO, PRA, File History) are linked via `prop_id`. Plot modifications must manage these links to ensure searchability.

---

## **2. Plot Subdivision (1 to Many)**
*Example: Dividing Mother Plot `A` into new units `B` and `C`.*

### **Process**
1.  **Archive & Delete**: Move the Mother record (`A`) to the `decommissioned_files` table and **delete** it from `FileNumber`, `File Indexing`, `Customers Staging`, and `Entities Staging`.
2.  **Generate New Units**: Create standalone records for units `B` and `C` in all core tables.
3.  **Cross-Reference**:
    *   Store Mother File No. in the `Related File No` and `Decommissioned Records` fields of the new units.
    *   Log the action in `PAA` (Property Application Archive) with transaction type **"Subdivision"**.
4.  **PropID Management**:
    *   Units `B` and `C` each receive a **new, unique PropID**.
    *   **Supporting Field**: A `parent_prop_id` (or similar) field is added to the new records to link them to Mother Plot `A`.
    *   **Search Logic**: When unit `B` is searched, the system uses the link to also display transactions from Mother Plot `A` (pre-subdivision history).

---

## **3. Plot Merger (Many to 1)**
*Example: Combining Source Plots `D` and `E` into a new Plot `F`.*

### **Process**
1.  **Archive & Delete**: Move all source records (`D`, `E`) to the `decommissioned_files` table and **delete** them from all active registry tables.
2.  **Generate New Entity**: Create one new record for plot `F` in the core tables.
3.  **Logging**: Create a single record in `PAA` with the instrument type **"Merger"**.
4.  **PropID Management**:
    *   Plot `F` receives a **new, unique PropID**.
    *   **Global History Update**: The system must perform a cascading update in `File History`, `PRA`, `COfO`, and `Deed Registrations`. Every transaction record that previously pointed to the PropIDs of `D` or `E` must now be updated to point to the new PropID of `F`.

---

## **4. Plot Extension & Exclusion**
*Adjusting boundaries (effectively a 1-to-1 replacement with updated data).*

### **Process**
1.  Follow the **Merger** logic exactly.
2.  Retire the original record to `decommissioned_files`.
3.  Delete from active tables.
4.  Create the new extended record with a new PropID.
5.  **Global History Update**: Update all existing transactions in the system to point to the new PropID to ensure a continuous historical timeline.

---

## **5. Technical Requirements Summary**

*   **Table Migrations**: Add `parent_prop_id` to `PropertyRecord` and `file_indexings`.
*   **Decommissioning Hook**: Ensure a transactional process that copies to `decommissioned_files` and then deletes from the source in a single block.
*   **History Resolver**: Update the search/reporting engines to follow the `parent_prop_id` link for Subdivisions.
*   **Batch Update Engine**: Implement a safe, audited method for bulk updating PropIDs across multiple tables during Mergers and Extensions.