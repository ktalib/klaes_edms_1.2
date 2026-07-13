# Client Meeting Notes — Timeline, Commissioning & Lineage Requirements

**Source:** Voice-transcribed conversation with the client (cleaned up; the raw transcript
was garbled — "password updates" is read as **parcel updates**, "major" as **merger**,
"Congress 2010" as an example **CON-…-2010** file number).
**Area affected:** Legal Search timeline ([`app/Services/LegalSearchService.php`](../../../app/Services/LegalSearchService.php)),
File Commissioning rows, lineage display.
**Date recorded:** 2026-07-10

---

## 1. Issues discussed

### 1.1 Legacy vs. current (2026) records — date display

- **Legacy records** (files digitized/imported into KLAES, transactions that predate the
  system): must **NOT** display a "date created". The stored `created_at` is only the
  migration/digitization timestamp, not a real event date — showing it misleads.
  Where the true date is unknown, the date field should be **blank** (not the import date).
- **2026+ records** (files commissioned through KLAES itself): should be **complete** —
  real transaction date, date created, audit trail. These can be trusted and should
  "naturally follow" in the timeline.

> ⚠️ **Implementation note / conflict to resolve:** `resolveCommissioningInfo()` was
> recently changed to fall back to `file_indexings.created_at` when no genuine
> commissioning date exists. For files indexed in 2026 through KLAES that is correct;
> for **legacy digitized files** that value is exactly the digitization timestamp the
> client says must stay blank. The fallback likely needs to be gated (e.g. only when the
> file-number year matches the indexing year, or only for files whose `mls_file_no` row
> exists) — confirm with the client.

### 1.2 Commissioning is the start of every file's lifecycle

- No transaction may appear on the timeline **before** the commissioning row of the file
  it belongs to. Commissioning is the anchor; everything else follows chronologically:

  ```
  File Commissioning → Assignment → Recertification → CofO → later transactions
  ```

### 1.3 Change of Purpose (rename — same parcel, new file number)

- **Old file's timeline:** `File Commissioning (old)` → historical transactions →
  `Change of Purpose`.
- **New file's timeline:** starts with its own `File Commissioning (new)` → then its
  transactions.
- The old file's history stays intact; the new file begins a fresh timeline from its own
  commissioning. When the full lineage is viewed on one timeline the sequence is:
  old commissioning → old transactions → CoP → **new file commissioning** → new transactions.

### 1.4 Subdivision (split — mother + children)

Slightly different because **two commissionings** are involved:

- **Viewing the mother:** `Mother File Commissioning` → historical transactions →
  `Subdivision` → child file commissioning(s).
- **Viewing a child:** `Mother File Commissioning` → `Subdivision` →
  `Child File Commissioning` → child's own transactions.

The lineage from the original parcel to the subdivided unit must remain readable.

### 1.5 Same principle for all parcel-update types

The commissioning/lineage ordering applies equally to: **Merger, Extension, Separation,
Parcel Update (legacy), Loss of Document, Temporary File, Title Status Update.**
Each must preserve the previous-file → current-file relationship.

### 1.6 Backend data correction (wrong years)

- Concrete example raised: a transaction row displays **2026** but the true historical
  date is **2020** (an assignment collected from a predecessor file into the current
  mother). The date is currently not showing / showing wrongly.
- Client asked whether this can be corrected **directly in the backend/database** rather
  than through the UI. Answer discussed: yes — these are data fixes, to be applied
  directly so the timeline reflects true historical dates.

### 1.7 Open question — cloud users without edit rights

- Raised but not resolved: the platform will run **on-premise and in the cloud**, and
  cloud users will **not have edit rights**. How do they handle records that need
  editing/rejecting (e.g. the Cleanup actions)? Needs a decision — likely a
  request/approval flow or restricting corrections to on-premise staff.

---

## 2. Action items

| # | Item | Type | Status |
|---|------|------|--------|
| 1 | Blank out "date created" for legacy imported records; never surface migration `created_at` as a date | Code | ✅ Done 2026-07-10 — manual-linkage PRA rows now save `transaction_date = NULL` (no Approval Date field); commissioning-date fallback gated (see #4) |
| 2 | Ensure commissioning row(s) always precede a file's transactions, including mother + child commissioning rows for subdivision/merger lineage | Code | ✅ Done 2026-07-10 — `search()` lineage now carries per-file commissioning info; on-screen timeline + print report render mother commissioning → history → parcel-update row → child commissioning → child transactions (successor commissioning after the retiring transaction when viewing the mother) |
| 3 | Verify Change of Purpose timelines show old commissioning → history → CoP → new commissioning → new history | Code / verify | ✅ Same placement logic; verified live with subdivision child RES-2026-2804 (mother RES-RC-1986-57) |
| 4 | Re-examine the new `file_indexings.created_at` commissioning-date fallback against the "legacy dates blank" rule (§1.1) | Code review | ✅ Done — fallback only applies when the year embedded in the file number matches the indexing year; legacy digitized files stay blank/year-only |
| 5 | Correct the mis-dated legacy rows (2026 → 2020 example) directly in the database | Data fix | ⏳ Open — needs a reviewed one-off script against production |
| 6 | Decide the editing model for cloud users without edit rights | Decision needed | ⏳ Open |
