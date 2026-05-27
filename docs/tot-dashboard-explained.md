# ToT Dashboard — Owner Name Discrepancy Explained

**Audience:** Anyone working on KLAES who needs to understand why the ToT Dashboard exists and what problem it solves.

---

## Background: What Are These Records?

Before understanding the problem, you need to know what three record types are involved:

| Record | Full Name | What it represents | Where stored |
|---|---|---|---|
| **OP** | Occupancy Permit | The government grants a plot of land to a person (the allottee). This is the original title. | `instrument_capture` table |
| **ToT** | Transfer of Title | The allottee later transfers (sells/gifts) their OP to someone else. Also called Change of Name (CoN). | `pra` table |
| **R of O** | Right of Occupancy | Another name for the same right — the registered ownership on a property. | `pra` table |

These three records are linked by a **`prop_id`** — a number that represents a single physical plot of land. Records sharing the same `prop_id` appear together as "1 property with N transactions" in the system.

---

## The Correct Flow (Happy Path)

Here is what a correct OP → ToT chain looks like:

```
PLOT: Block 5, Kano Municipal, Plot 22
prop_id = 12345678

Step 1 — Government issues Occupancy Permit
─────────────────────────────────────────────
instrument_capture (OP):
  temp_fileno  = TEMP-10001
  party_1_name = Kano State Government   ← grantor (always the govt)
  party_2_name = Musa Garba              ← allottee (the person given the land)
  prop_id      = 12345678

Step 2 — Musa Garba transfers the plot to Fatima Usman
─────────────────────────────────────────────────────────
pra (ToT / Change of Name):
  mlsFNo       = RES-2026-0055
  temp_fileno  = TEMP-10001             ← inherited from the OP
  party_1      = Musa Garba             ← original holder (must match OP party_2)
  party_2      = Fatima Usman           ← new holder
  prop_id      = 12345678               ← same property
```

**The key rule:**
> `OP.party_2_name` (the allottee) must equal `ToT.party_1` (the original holder transferring the title).

If those two names match, the chain is valid. The system knows: Musa Garba held the OP, Musa Garba transferred it to Fatima Usman.

---

## The Mismatch — What Goes Wrong

Now here is what happened in the two confirmed problem cases in production:

### Confirmed Case 1

```
prop_id = 69579486

The OP on record:
  instrument_capture.id = 15614
  temp_fileno           = TEMP-50177
  party_2_name          = B80 Srtucture        ← the actual allottee on the OP

The ToT created:
  pra.id    = 149417
  mlsFNo    = RES-2026-2220
  party_1   = AISHA ABUBAKAR SALISU            ← but this doesn't match B80 Srtucture!
  party_2   = [new holder]
  prop_id   = 69579486                         ← same prop_id, so they're linked
```

The system now shows **prop_id 69579486** as "1 property with 2 transactions" — but the OP belongs to **B80 Srtucture** and the ToT belongs to **Aisha Abubakar Salisu**. These are two different people. The property was never actually transferred from B80 to Aisha. The link is wrong.

### Confirmed Case 2

```
prop_id = 69574082

The OP on record:
  instrument_capture.id = 5162
  temp_fileno           = TEMP-50114
  party_2_name          = Gali Rabiu            ← the actual allottee

The ToT created:
  pra.id    = 149403
  mlsFNo    = RES-2026-2219
  party_1   = AISHA ABUBAKAR SALISU            ← again, doesn't match Gali Rabiu!
  prop_id   = 69574082
```

Same pattern — Aisha's ToT was linked to Gali Rabiu's OP.

---

## How Did This Happen? Step by Step

When a user processes a Change of Name (CoN) application, the system runs a function called `saveFfrChangeOfName`. Here is what it does:

```
User processing CoN for: Aisha Abubakar Salisu
User types source file:   TEMP-50177   ← user selected B80's file by mistake

Step 1 — System searches for TEMP-50177 in the database
         Finds: instrument_capture row for B80 Srtucture (prop_id = 69579486)

Step 2 — System picks the "best" OP from the search results
         (picks the oldest record — no check on whose name it is)

Step 3 — System takes prop_id = 69579486 from that OP

Step 4 — System creates a new ToT row in pra:
         party_1 = Aisha Abubakar Salisu
         prop_id = 69579486   ← B80's prop_id, now given to Aisha's ToT

Step 5 — No warning raised. No error returned. Data is corrupted.
```

The user selected the wrong source file, and the system had no check to catch it.

---

## Why Did the System Not Catch It?

There are five reasons this slipped through:

### Reason 1 — No ownership check (the main cause)

The code accepted any source file the user typed. It never compared:

```
OP.party_2_name  (B80 Srtucture)
        vs
Application.party_1  (Aisha Abubakar Salisu)
```

If it had, it would have seen the names don't match and rejected the request.

### Reason 2 — "Oldest wins" tie-breaking

When multiple OP candidates are found for a file number, the system silently picks the **oldest** one — no matter whose name is on it. No warning is logged. No human ever sees the ambiguity.

### Reason 3 — Over-broad file number search

The search checks **6 different columns** at once (`mlsFNo`, `temp_fileno`, `fileno`, etc.) using OR logic. A single file number search can accidentally return records from completely different properties if they happen to share any file number token.

### Reason 4 — OP and ToT share the same `temp_fileno`

When a ToT is created, it inherits the source OP's `temp_fileno`. This means a search for `TEMP-50177` will find both the OP row AND any ToT rows — making it harder to identify which records truly belong together.

### Reason 5 — No database-level uniqueness

The `pra` table has no constraint stopping two different properties from sharing the same `prop_id`. The only guard is application code — which the bugs above bypass.

---

## What the ToT Dashboard Does About It

The ToT Dashboard (`/maintenance/tot`) is an **after-the-fact repair tool**. It:

1. Scans the database for cases where an OP and a ToT share the same `prop_id` but the owner names **do not match**
2. Stages these as "pending mismatches" in the `pra_tot_staging` table
3. Shows them on a dashboard so an admin can review

### What the admin sees

| Column | Description |
|---|---|
| MLS File No | The file number of the mismatched pair |
| OP Owner (Current) | The name on the OP — the real allottee |
| R of O Owner (Staged) | The name on the ToT — the person incorrectly linked |
| Property ID | The shared `prop_id` tying them together |

### What the admin can do

| Action | What it does |
|---|---|
| **Execute ToT Generation** | Creates a corrected ToT record: `op_name → grantor`, `ro_name → grantee` |
| **Archive Selected** | Marks the mismatch as intentionally ignored (e.g. already fixed manually) |

---

## Visual Summary

```
CORRECT CHAIN:
  OP (party_2 = Musa Garba)
       ↓ same prop_id
  ToT (party_1 = Musa Garba ✓, party_2 = Fatima Usman)
  ──────────────────────────────────────────────────────
  party_2 on OP matches party_1 on ToT → valid chain

BROKEN CHAIN (what the dashboard detects):
  OP (party_2 = B80 Srtucture)
       ↓ same prop_id  ← wrong link
  ToT (party_1 = Aisha Abubakar Salisu ✗)
  ──────────────────────────────────────────────────────
  Names don't match → data integrity violation
  → Appears as a pending mismatch on the ToT Dashboard
```

---

## Long-Term Fixes Planned

The dashboard is a remediation tool, not a permanent solution. The proper fixes are documented in `docs/op-tot-longterm-fix-plan.md` and include:

| Fix | Description |
|---|---|
| F1 | Add an ownership check in `saveFfrChangeOfName` — reject if OP owner ≠ applicant |
| F2 | Log a warning whenever multiple OP candidates are found for a file number |
| F3 | Store explicit `instrument_capture_id` on every ToT row for a hard link |
| F4 | Remove the silent "oldest wins" fallback that picks wrong records |
| F5 | Restrict the file-picker UI to only show OPs belonging to the correct applicant |
| F6 | Add `instrument_capture_id` column to `pra` table (DB-level hard link) |
| F7 | Add a unique constraint so one OP can only ever be the source of one ToT |
