# Client Chat Update (Interpreted and Consolidated)

Date interpreted: 2026-03-16
Source: transcribe.md (voice-to-text transcript, with possible word errors)

## 1. Interpretation notes
This summary normalizes likely transcription errors.

Likely corrections:
- Pratt -> PRA
- particles -> parties
- open -> OP
- orange holder -> current owner
- capture register -> capture existing flow/card

## 2. Placement and UI requirement
The new workflow card should be on the Change of Name page.

The card must have two types/options:
- Existing
- New

## 3. Type definitions (as clarified)

### Existing
Use this for newly commissioned file numbers.

### New
Use this for old file numbers not commissioned by this system, for example RES-1991-1.
This path should use the Capture Existing flow/card as part of processing.

## 4. Common save behavior
- Maintain the same property chain and timeline history.
- Do not overwrite only the currently opened row.
- Add a new row in PRA timeline for latest transaction state.
- MLS should be updated for ownership/name change context.

Client clarification from call:
- PRA: new row
- MLS: update

## 5. Party and holder mapping
- Party 1 auto-fill source: OP-specific holder.
- Party 2 should be entered/updated for the new holder context.
- OP type selection should be mandatory before capture in existing flow.

## 6. Client scenarios (authoritative)

### Scenario 1: Only two transactions and commissioned by manual method
Step 1:
- Capture OP between Party 1 (KSG) and Original Party 2 using Temp FileNo.

Step 2:
- Capture transaction between Original Party 2 and the 2nd Party 2 using MLSFileNo used by manual method before KLAES.

Step 3:
- Capture last transaction between 2nd Party 2 and 3rd (new) Party 2 while maintaining the same MLSFileNo.

### Scenario 2: File number commissioned by Rayyanu
Step 1:
- Capture OP between Party 1 (KSG) and Original Party 2 using Temp FileNo.

Step 2:
- Capture transaction between Original Party 2 and the 2nd Party 2 using the MLSFileNo.

Step 3:
- Capture last transaction between 2nd Party 2 and 3rd (new) Party 2 while maintaining the same MLSFileNo.

## 7. Downstream data targets
Client stated these fields should carry the current and original holder context:

- Customer module:
	- customer_name

- Indexing module:
	- file_title
	- current_holder
	- original_holder

## 8. Implementation direction
Implement two explicit Change of Name flows (Existing and New) with a shared transaction service that:
- resolves OP-specific Party 1 source,
- enforces mandatory OP type selection for capture,
- writes append-only PRA timeline rows,
- updates MLS holder context,
- syncs customer_name, file_title, current_holder, original_holder consistently.
