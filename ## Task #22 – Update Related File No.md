## Task #22 – Update Related File No. Indexing (KANGIS System)

### Objective
Update the "Related File Number" section to backfill details when a file has 
already been indexed in KANGIS.

### Backfill Logic
- When a Related File No. is selected/entered:
  - If the file EXISTS in the index → auto-backfill all detail fields 
    (File Title, Location, Plot No., TP No., LPKN No.) from the existing record
  - If the file has NOT been indexed → leave fields blank, do NOT redirect
- Backfill executes ON SAVE ("Save Details" button click)
- New property ID must NOT propagate to parent property ID
- Multiple related files should STACK (already supported in UI)

### File Title Display Format
In the Related File Number Details modal, File Title should display as:
"File Title (Current Holder | Original Holder & No. of Transactions)"
Include a timeline view for transaction history.

### Audit Rules
- Audits apply only where a file number exists
- Existing indexed file selected = valid/confirmed
- Unindexed file = leave as-is, no forced redirect

### UI Reference
- Main form: File Number & Tracking ID, Customer Type & File Title, 
  Holders (Original + Current), Related File Number section
- Modal: "Related File Number Details" — one card per related file