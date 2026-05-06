# Change of Name: Implementation Tasks

Source references:
- transcribe_update_interpretation.md
- Client scenario notes (manual-commissioned and Rayyanu-commissioned)

Date: 2026-03-16

## 1. Scope
Implement a new card on the Change of Name page with two modes:
- Existing
- New

Business outcome:
- Preserve transaction timeline in PRA (append rows, no destructive overwrite).
- Update MLS holder context.
- Keep downstream fields consistent (customer_name, file_title, current_holder, original_holder).

## 2. Functional Rules

### 2.1 Mode: Existing
- Used for newly commissioned file numbers.
- OP type selection is mandatory before capture.
- Follow scenario chain with same MLSFileNo continuity.

### 2.2 Mode: New
- Used for older file numbers not commissioned by current system (example: RES-1991-1).
- Must pass through Capture Existing flow/card before continuing.

### 2.3 Party mapping
- Party 1 source must be OP-specific holder context.
- Party 2 represents the new holder in the final transaction row.
- OP starting point remains KSG vs Original Party 2 in the first OP capture row.

### 2.4 Save behavior
- PRA: insert new timeline row(s).
- MLS: update existing holder context.
- Do not overwrite only the currently opened transaction row.

## 3. Client Scenarios to Implement

### Scenario 1: Manual commissioned before KLAES
1. Capture OP between Party 1 (KSG) and Original Party 2 using Temp FileNo.
2. Capture transaction between Original Party 2 and 2nd Party 2 using MLSFileNo from manual method.
3. Capture final transaction between 2nd Party 2 and 3rd (new) Party 2 while maintaining same MLSFileNo.

### Scenario 2: Commissioned by Rayyanu
1. Capture OP between Party 1 (KSG) and Original Party 2 using Temp FileNo.
2. Capture transaction between Original Party 2 and 2nd Party 2 using MLSFileNo.
3. Capture final transaction between 2nd Party 2 and 3rd (new) Party 2 while maintaining same MLSFileNo.

## 4. UI Tasks

1. Add Change of Name card with mode switch:
- Existing
- New

2. Add mandatory OP Type selector in Existing mode:
- OP Direct Allocation
- OP Resettlement

3. Keep card behavior mode-aware:
- Existing mode starts from commissioned file context.
- New mode starts from capture-existing flow for legacy/non-commissioned files.(it will open the Capture Existing File modal )

4. Add validation messages:
- Missing OP type.
- Missing file number.
- Missing Party 2/new holder details.

5. Add summary/confirmation preview before commit:
- MLSFileNo used.
- Party transitions (Original -> 2nd -> 3rd).
- Affected modules (PRA row insert + MLS update).

## 5. Backend/API Tasks

1. Add/extend endpoint(s) for Change of Name save:
- Accept mode (Existing/New).
- Accept OP type (required in Existing mode).
- Accept source file number and party chain payload.

2. PRA service logic:
- Insert append-only rows for each required step.
- Preserve same prop_id timeline linkage.
- Ensure transaction ordering and correct party values.

3. MLS service logic:
- Update holder context (not duplicate wrong holder state).
- Keep MLSFileNo continuity across steps.

4. Capture-existing integration (New mode):
- Ensure legacy file can be normalized into chain workflow.

5. Idempotency and duplicate guard:
- Prevent accidental double submissions creating duplicate timeline rows.

## 6. Data Mapping Tasks

1. Customer module:
- Update customer_name to latest holder (as approved by business flow).

2. Indexing module:
- file_title
- current_holder
- original_holder

3. Ensure source-of-truth policy:
- Timeline history remains in PRA rows.
- Latest holder is resolvable deterministically.

## 7. Technical Checks

1. DB transaction boundary:
- Wrap multi-step scenario save in transaction.
- Roll back if any step fails.

2. Audit/logging:
- Log mode, file number, op type, prop_id, inserted PRA ids, updated MLS id.

3. Permission checks:
- Restrict Change of Name actions to authorized roles.

## 8. Test Cases (Must Pass)

### A. Existing mode
1. OP type not selected -> blocked.
2. Valid payload -> PRA rows inserted in proper sequence.
3. MLS updated, not overwritten with wrong holder chain.

### B. New mode
1. Legacy file number (not commissioned) can be captured and processed.
2. Same MLSFileNo maintained through scenario chain.

### C. Data consistency
1. PRA timeline shows full history (OP -> intermediate -> final).
2. customer_name matches expected latest holder.
3. indexing fields file_title/current_holder/original_holder match expected mapping.

### D. Negative/edge
1. Duplicate submit click does not create duplicate chain rows.
2. Missing intermediate party context fails with clear message.
3. Invalid file number returns actionable error.

## 9. Open Decisions (Confirm Before Dev Freeze)

1. Exact rule for final holder in customer_name when there are pending approvals.
2. Whether scenario always inserts all 3 rows even when one already exists.
3. UI display of historical parties: compact vs full timeline panel.

## 10. Suggested Delivery Sequence

1. Implement backend chain service first (with tests and logging).
2. Add Change of Name card UI modes and validation.
3. Integrate capture-existing path for New mode.
4. Wire downstream updates for customer/indexing fields.
5. UAT with both Scenario 1 and Scenario 2 using real sample files.
