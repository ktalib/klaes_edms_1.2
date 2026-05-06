# FH-over-PRA Weighting Update Plan

## Current Behavior
- Fingerprint = transaction type + party 1-4 + date + reg particulars
- FH (score 3) always beats PRA (score 2) when fingerprints match
- Dedup only applies to Timeline + Report views
- Print template shows all records (no dedup)
- Source tabs show all raw records with no visual indicator

## New Requirements

### 1. Enhanced Scoring: Prefer Records WITH Registration Particulars
**Problem:** Two records may be the "same" transaction but one has Registration Particulars (e.g., `628/15/11`) and the other doesn't. Currently the FH version always wins, even if it's missing reg particulars while the PRA version has them.

**Solution:** Add a **reg particulars bonus** to the scoring:
- Base scores remain: FH = 3, PRA = 2
- If a record HAS non-empty reg particulars → +2 bonus
- Result: FH with reg = 5, FH without reg = 3, PRA with reg = 4, PRA without reg = 2
- **Effect:** PRA with reg particulars (4) beats FH without reg particulars (3)
- **Effect:** FH with reg particulars (5) still beats PRA with reg particulars (4)

Also need to **loosen the fingerprint** — currently reg particulars is part of the fingerprint key, so if one record has it and the other doesn't, they don't match at all. Fix: exclude reg particulars from the fingerprint (use only transaction type + parties + date), then use reg particulars as a scoring factor instead.

### 2. Colored Dot Indicators on Source Tabs
**Where:** File History tab, Property Record tab (and Aggregate if applicable)

**Visual:**
- 🟢 Green dot = **Preferred** record (this record won the dedup and will appear in Timeline/Report/Print)
- 🟡 Yellow/Orange dot = **Duplicate** record (a better version exists from another source, this one is suppressed in Timeline/Report/Print)
- No dot = **Unique** record (exists in only one source, no dedup needed)

**Implementation:**
- After running dedup, tag each raw record with a `_dedup_status` field: `'preferred'`, `'duplicate'`, or `'unique'`
- In the table rendering functions for each source tab, render the dot before the S/N column or in an ACTIONS column
- Tooltip on dot: "Preferred over PRA duplicate" / "Duplicate — FH version preferred"

### 3. Print Report Shows Only Preferred Records
**Current:** `reportTemplateData()` in `LegalSearchController.php` has server-side dedup, but may not fully align with the enhanced scoring.

**Change:** Update the PHP dedup to use the same enhanced scoring (reg particulars bonus + loosened fingerprint). The print template already consumes the deduped `$transactions` array, so once the PHP logic is updated, only preferred records will print.

### 4. Weighting Score Tab (Next to Timeline)
**Where:** New tab called "Weighting" placed next to the Timeline tab at the bottom of the page.

**Purpose:** Transparency — shows exactly how the system scored and decided which records to prefer.

**Table Columns:**
| Fingerprint | Source | Base Score | Reg Bonus | Total Score | Status | Record Summary |
|---|---|---|---|---|---|---|
| Mortgage / Musa→Safiya / Jan 2024 | FH | 3 | +2 | 5 | ✅ Preferred | Mortgage, 628/15/11 |
| Mortgage / Musa→Safiya / Jan 2024 | PRA | 2 | +0 | 2 | ❌ Duplicate | Mortgage, - |
| Power Of Attorney / Musa→Bashir / Apr 2024 | FH | 3 | +0 | 3 | ✅ Unique | Power Of Attorney, 456/6/8 |

**Columns:**
- **Fingerprint** — The normalized matching key (transaction type / parties / date)
- **Source** — Which table: FH, PRA, CofO, Deed
- **Base Score** — Source weight (FH=3, PRA=2, CofO=4, Deed=1)
- **Reg Bonus** — +2 if record has registration particulars, +0 if not
- **Total Score** — Base + Bonus
- **Status** — Preferred ✅ / Duplicate ❌ / Unique (no competition)
- **Record Summary** — Instrument type + reg particulars preview

**Color coding rows:**
- Green background for Preferred rows
- Red/light-red background for Duplicate rows
- No highlight for Unique rows

**Implementation:**
- Build the weighting data from the `_dedup_status` and scoring info already computed during dedup
- Store in `window._weightingData` array alongside `_preferredRelatedTransactions`
- Render via a new `renderWeightingTable()` function triggered when the tab is clicked

---

## Implementation Steps

### Step 1: Update JS Fingerprint & Scoring (js.blade.php)
- In `dedupeTransactionsForTimelineAndReport()`:
  - Remove reg particulars from fingerprint key
  - Add reg particulars bonus (+2) to source score
  - Tag every record in `_allRelatedTransactions` with `_dedup_status`

### Step 2: Update PHP Fingerprint & Scoring (LegalSearchController.php)
- In `reportTemplateData()`:
  - Mirror the same fingerprint change (exclude reg particulars)
  - Add reg particulars bonus to `$sourceScore` closure
  - Ensure only preferred records go into `$rows`

### Step 3: Add Colored Dots to Source Tab Tables (js.blade.php)
- In the table rendering functions for FH, PRA, CofO, Deed, and Aggregate tabs:
  - Check each record's `_dedup_status`
  - Render a colored dot (green/yellow/none) in the row

### Step 4: Add Weighting Tab (file-history.blade.php + js.blade.php)
- Add a new "Weighting" tab button next to "Timeline" in the bottom section
- Create `renderWeightingTable()` function that builds the scoring transparency table
- Store `_weightingData` during dedup (fingerprint, source, base, bonus, total, status, summary)
- Tab shows empty state "Run a search to see weighting scores" until data exists

### Step 5: Test Scenarios
- Record in both FH and PRA, both have reg particulars → FH wins (green on FH, yellow on PRA)
- Record in both FH and PRA, only PRA has reg particulars → PRA wins (yellow on FH, green on PRA)
- Record in both FH and PRA, neither has reg particulars → FH wins (green on FH, yellow on PRA)
- Record only in FH → no dot (unique)
- Record only in PRA → no dot (unique)
- Print template shows only green/unique records

---

## Files to Modify
1. `resources/views/legal_search/js.blade.php` — JS dedup logic + dot rendering + weighting table
2. `resources/views/legal_search/partials/file-history.blade.php` — Add Weighting tab button next to Timeline
3. `app/Http/Controllers/LegalSearchController.php` — PHP dedup logic for print
4. `resources/views/legal_search/templates/PAY-PER-SEARCH.html` — (no change needed, consumes server data)
5. `resources/views/legal_search/templates/ONLINE.html` — (no change needed)
6. `resources/views/legal_search/templates/OFFICIAL SEARCH REPORT.html` — (no change needed)
