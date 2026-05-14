# Legal Search, Weighting Method & Property Timeline — Comprehensive Study

**Date:** May 13, 2026  
**Scope:** In-depth analysis of the Legal Search module, Weighting algorithms (Deduplication), and the Property Timeline subsystem within the KLAES EDMS.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Legal Search System Overview](#2-legal-search-system-overview)
3. [The Weighting Methods — Deep Dive](#3-the-weighting-methods--deep-dive)
    - [3.1 Legal Search Weighting (Authoritative)](#31-legal-search-weighting-authoritative)
    - [3.2 Timeline Weighting (Richness-based)](#32-timeline-weighting-richness-based)
4. [Property Timeline Architecture](#4-property-timeline-architecture)
    - [4.1 Cross-Table Aggregation](#41-cross-table-aggregation)
    - [4.2 Manual Arrangement System](#42-manual-arrangement-system)
5. [Cleanup & Data Integrity](#5-cleanup--data-integrity)
6. [Search Reporting & Accountability](#6-search-reporting--accountability)
7. [Technical File Map](#7-technical-file-map)
8. [Conclusion & Recommendations](#8-conclusion--recommendations)

---

## 1. Executive Summary

The KLAES Legal Search and Timeline systems are designed to provide a "single source of truth" for property history in Kano State. Because land records are often fragmented across multiple legacy and staging tables (**File History**, **PRA**, **CofO**, and **Deed Registrations**), the system employs sophisticated **Deduplication Weighting** to identify the most accurate and "canonical" version of any given transaction.

This study explores the dual-weighting logic used by the system and how the **Property Timeline** integrates these records into a cohesive, interactive history across all administrative modules.

---

## 2. Legal Search System Overview

### 2.1 Purpose
Legal Search is a comprehensive investigation tool used to produce official search reports. It searches across four primary staging tables using `prop_id` as the anchor.

### 2.2 The Four Pillars (Data Sources)
| Source Table | Label | Description |
|---|---|---|
| `file_history_staging` | **FH** | Legacy records scanned/typed from physical file jackets. |
| `pra` | **PRA** | Property Record Archive — historically corrected records. |
| `CofO_staging` | **CofO** | Current Certificate of Occupancy records and NPs. |
| `deed_registrations` | **Deeds** | Modern registrations (Assignments, Mortgages, etc.). |

### 2.3 Prop ID Expansion
The system uses a **Dual-Path Search**:
1. Initial search by **File Number** (MLS, ST, KANGIS) across all tables.
2. **Expansion**: The system collects all unique `prop_id` values from the initial results and performs a secondary query across all four tables to ensure related records (e.g., a mortgage under a different file number) are pulled in.

---

## 3. The Weighting Methods — Deep Dive

KLAES uses two distinct weighting algorithms depending on the context:

### 3.1 Legal Search Weighting (Authoritative)
Used by `LegalSearchService` for search result tabs and official report generation. This method prioritizes "canonical" sources and the presence of registration particulars.

**Scoring Formula:** `Total Score = Base Score + Reg Particulars Bonus`

| Source | Base Score | Reg Bonus (+2) | Max Score |
|---|---|---|---|
| CofO Staging | 4 | Yes | 6 |
| File History | 3 | Yes | 5 |
| PRA | 2 | Yes | 4 |
| Deeds | 1 | Yes | 3 |

*Note: CofO and Deed records typically bypass deduplication as they are considered independent legal events.*

### 3.2 Timeline Weighting (Richness-based)
Used by `TimelineWeightingService` for the Property Timeline and Global Property Search. This is a more modern, granular algorithm that rewards data richness.

**Scoring Components:**
1. **Source Base Score**:
   - Occupancy Permit (OP): **10.0**
   - Transfer of Title (ToT): **9.5**
   - RofO: **9.0**
   - CofO: **8.0**
   - PRA/Deed: **5.0**
   - File History: **2.5**

2. **Richness Bonus**:
   - Primary Parties (Grantor/Grantee): **+2.0 each**
   - Registration Parts (S/P/V): **+1.5 each**
   - Transaction Dates: **+3.0**
   - Location/District: **+1.0**
   - Comments/Descriptions: **+1.0**
   - Land Use: **+0.5**

**Fingerprinting**:
To detect duplicates, the system generates a normalized string of `TransType | Party1 | Party2 | Date`. If two records share the same fingerprint, the one with the higher **Richness Score** is marked as `Preferred`, and the other as `Duplicate`.

---

## 4. Property Timeline Architecture

### 4.1 Cross-Table Aggregation
The timeline is a unified vertical list. It uses **UNION ALL** queries across the four staging tables, wrapped in a ranking function that picks the "Best" record for each logical event based on the Weighting Service.

### 4.2 Manual Arrangement System
Since chronological dates are sometimes missing or entered incorrectly in legacy data, KLAES provides a **Manual Arrangement** feature.
- **Table**: `legal_search_timeline_arrangements`
- **Function**: Allows users to "Arrange" records in a custom order.
- **Persistence**: Once saved, any user viewing the timeline for that `prop_id` will see the manually arranged order instead of the default chronological sort.

---

## 5. Cleanup & Data Integrity

The Legal Search interface includes a **Cleanup Mode** that allows authorized staff to sanitize data in real-time:
- **Match**: Link an orphan record to a `prop_id`.
- **Drop**: Unlink a record that was incorrectly associated with a property.
- **Remove**: Soft-delete duplicates or erroneous entries.
- **Update**: Edit fields (dates, names, reg particulars) directly from the search interface.

---

## 6. Search Reporting & Accountability

The `LegalSearchLog` system tracks all search activities:
- **Who**: The staff member who performed the search.
- **Criteria**: The exact parameters used.
- **Results**: Whether a record was found and how many transactions were returned.
- **Printing**: Tracks if a search report was actually printed.
- **Direct Link**: Stores a deep link to re-execute the exact search for audit purposes.

---

## 7. Technical File Map

| Component | Key Files |
|---|---|
| **Weighting Service** | `app/Services/TimelineWeightingService.php` |
| **Legal Search Service** | `app/Services/LegalSearchService.php` |
| **Controllers** | `LegalSearchController.php`, `PropertySearchController.php`, `LegalsearchreportsController.php` |
| **Frontend JS** | `resources/views/legal_search/js.blade.php`, `public/js/property-timeline-modal.js` |
| **Database** | `file_history_staging`, `pra`, `CofO_staging`, `deed_registrations`, `legal_search_timeline_arrangements` |

---

## 8. Conclusion & Recommendations

1. **Standardization**: Consider migrating the older `LegalSearchService` weighting to the richer `TimelineWeightingService` logic for better consistency across the app.
2. **Audit Logging**: While search activity is logged, the **Cleanup Mode** (Match/Drop/Update) should also be integrated into the global `AuditService` to track data modifications.
3. **Performance**: As staging tables grow, the `prop_id` columns must be strictly indexed across all four tables to maintain sub-second response times for the cross-table expansion logic.

---
*Report compiled by Antigravity (AI Assistant) for KLAES Project.*
