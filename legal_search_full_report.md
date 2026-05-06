# KLAES Legal Search: Comprehensive System Report

This report provides a detailed overview of the Legal Search (LS) module within the KLAES project, covering the technical implementation of search types, record deduplication, weighting methodologies, and timeline generation.

## 1. System Overview
The Legal Search module is designed to provide a definitive history of property transactions and legal status. It is implemented through several variations:

| Type | Controller | Purpose | New UI Features |
| :--- | :--- | :--- | :--- |
| **Official Search** | `LegalSearchController` | Internal use for filing. | Table/Record Weights split. |
| **On-Premise (Pay-per-Search)** | `OnPremiseController` | Commercial/Public search at registry. | Table/Record Weights split. |
| **Online Search** | `LegalSearchController` | Web-based search for verification. | - |

## 2. Record Normalization & Deduplication
To ensure a clean timeline, the system processes raw data from multiple staging sources (PRA, File History, CofO, Deed Registration).

### 2.1 Instrument Normalization
Instrument types are canonicalized to prevent duplicates caused by spelling variations:
- **Right of Occupancy**: Normalizes "R of O", "ROFO", "Statutory/Customary R of O".
- **Occupancy Permit**: Normalizes "OP", "Occupancy Permit".
- **Deed of Mortgage**: Collapses "Tripartite", "Legal", and "Equitable" mortgages.
- **Power of Attorney**: Collapses "POA", "IPOA", etc.

### 2.2 Deduplication Keys
The system identifies duplicates using two layers of logic:
1.  **Primary (Reg Particulars)**: Matches on `Instrument Type + Serial No / Page No / Volume No`.
2.  **Fallback (Party/Date)**: Matches on `Instrument Type + Grantor + Grantee + Date` (used when reg particulars are missing).

## 3. Weighting Methodologies (Rule A)
The system uses weights to decide which record "wins" when a duplicate is found across different sources.

### 3.1 Source (Table) Weighting
Determines the authority of the data source:
- **PRA / Deed Registration / CofO**: `5.0`
- **File History**: `2.5`
- **Others**: `1.0`

### 3.2 Richness (Record) Weighting
Calculates a "Richness Score" (up to 10 points) based on data completeness. Each attribute present adds 2 points (consistent with the client's "weight of 8" for 4 attributes example). 

**New UI Update**: The "Detailed Tabs" (MSDAM) now display two separate columns:
- **TW (Table Weight)**: The authority score of the source (5.0, 2.5, etc.).
- **RW (Record Weight)**: The richness score based on field completeness (0 to 10).

Attributes for Richness Calculation:
1.  **Parties**: Grantor/Grantee information.
2.  **Reg Particulars**: Serial/Page/Volume numbers.
3.  **Transaction Date**: The date the deed was signed.
4.  **Registration Date**: The date it was recorded in the registry.
5.  **Registration Time**: The specific timestamp of registration.

> [!IMPORTANT]
> **Implementation Note / Discrepancy**: 
> Per the latest client voice note (`weighing method_chat.md`), the **Record Weight (Richness)** should be the primary determinant. 
> *   **Client's Rule**: A record with 4 attributes (8 points) from a 2.5 weight table should BEAT a record with 2 attributes (4 points) from a 5.0 weight table.
> *   **Current Code Status**: Currently, the code in `LegalSearchController.php` checks Table Weight first, and only uses Richness as a tie-breaker. This should likely be updated to prioritize the richness score as the primary sorting/deduplication factor.


## 4. Timeline Sorting (Rule B)
Once records are deduplicated, they are ordered for the final report.

### 4.1 Priority Weights by Instrument
Certain documents are pinned to the top of the timeline to ensure logical flow:
- **Occupancy Permit**: `10`
- **Transfer of Title**: `9`
- **Right of Occupancy**: `8`
- **All Others (including CofO)**: `5`

### 4.2 Chronological Sorting
Within the same priority group, records are sorted by the "best available timestamp" in this order:
`Reg Date/Time` > `Deeds Date/Time` > `Transaction Date/Time` > `CofO Date` > `Approval Date`.

## 5. Report Header Logic
The system dynamically determines key property details for the report header:

### 5.1 Property Size Weighting
To pick the definitive plot size, it uses a separate hierarchy:
`CofO (4)` > `File History (3)` > `PRA (2)` > `Deed (1)`.

### 5.2 Legal Status & Caveats
The system performs real-time legal checks:
- **Mortgage Check**: If a "Deed of Mortgage" exists without a subsequent "Deed of Surrender and Release," the system automatically flags the property as under an **Active Mortgage**.
- **Caveat Check**: Cross-references the `caveats` table for active blocks.
- **CofO Presence**: If no CofO is found in the history, the system appends a note stating the title is currently at "Letter of Grant" stage.

---
*Report Generated: 2026-05-02*
*Based on Code Analysis and Client Voice Note Transcription.*
