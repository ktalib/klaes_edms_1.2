# PropID Master Implementation Plan

## Goals
- Stop cross-table prop_id drift (same file number with different prop_id, or NULLs).
- Make a single authority (`PropID_Master`) for resolving/allocating prop_id across PRA, PIC, File History, and CofO staging.
- Keep temp file numbers visible but **do not allocate prop_id** until an official file number exists.
- Ensure every UI that posts transactions shows the non-editable **Property ID** value so users see what will be written.

## Scope & Touch Points
- Tables: `PropID_Master` (new), `file_history_staging`, `pra`, `pic`, `CofO_staging`.
- Services/controllers to align: `PropertyIdAllocationService`, `PropertyRecordController` (store + storeFromIndexing), `PropertyIndexCardController` (PIC), `PropertyRecordAssistant`/PRA flows, File History modal, CofO staging upserts, and any importers that write to those tables.
- UI: add a read-only "Property ID" display in PRA, PIC, File History, CofO staging, and file indexing modals; hide for purely temporary numbers.

## Approach (best-practice path)
1) **Create canonical registry**: build `PropID_Master` (see `database_scripts/prop_id_master_table.sql`). It stores one canonical prop_id per property plus all known file-number variants. Computed normalized columns + unique indexes prevent the same file number from mapping to multiple prop_ids.
2) **Backfill from authoritative sources**: union distinct file-number/prop_id pairs from `file_history_staging` (authoritative), then `pra`, `pic`, `CofO_staging`. Prefer the most recent non-null prop_id when conflicts appear; flag disagreements using the conflict view from the SQL script before enforcing FKs.
3) **Service-first allocation**: extend `PropertyIdAllocationService` to (a) look in `PropID_Master` first, (b) insert a new row there when generating a fresh prop_id, and (c) sync the chosen prop_id back to `file_history_staging` and the caller table in one transaction.
4) **Write-path enforcement**: update PRA/PIC/CofO/File History saves to require a prop_id from the service. Reject writes that only carry `temp_fileno` (store the temp number for reference, but keep prop_id NULL until a real file number is present).
5) **Read-only UI exposure**: surface `prop_id` as "Property ID" (disabled input or plain text) in PRA, PIC, File History modal, CofO staging form, and file indexing detail views. When only a temp file number exists, show "Property ID pending" or leave blank.
6) **Multiple transactions per file number**: each transaction must reuse the prop_id resolved for its file number. Wrap save + `PropID_Master` upsert + `file_history_staging` sync in a single SQL Server transaction to avoid races.
7) **Validation & monitoring**: keep the validation queries from `PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md` plus the new conflict view (`vw_prop_id_conflicts`) to ensure no duplicate mappings appear after rollout.



Here’s where `prop_id` is already wired and actively used, based on the current code and docs: (cheekc allnthe forms and ui make everything correct)

- **File History (file_history_staging)** — canonical pivot; all indexing/property-record saves sync here, and APIs/views partition by `prop_id`.
- **Property Records (modern)** — `PropertyRecordController::storeFromIndexing()` allocates/reuses `prop_id` and writes it to property_records plus file_history_staging.
- **File Indexing**  
  - **File history sync:** `FileIndexingController::update()` calls `updateFileHistoryPropId()` to push `prop_id` into `file_history_staging` after an indexing edit.
  - **CofO staging sync:** `updateCofORecord()` upserts CofO staging with the posted `prop_id` (when provided).
- **PRA (Property Records Assistant, legacy)** — table has `prop_id` and is read by the allocation service; existing rows carry prop_id when present in imports/edits.
- **PIC (Property Index Cards, legacy)** — table has `prop_id`; UI is read-only but stored values are used for lookups and allocation reuse.
- **CofO_staging** — column present; when indexing provides `prop_id`, it is written on upsert.

Places where `prop_id` is still missing or inconsistent:
- PropertyRecordController::store() (main form) does not allocate `prop_id`.
- Caveat flows do not allocate or sync `prop_id`.
- CofO staging only gets `prop_id` when the caller supplies it; allocation isn’t enforced.
- Temp file numbers are intentionally left without `prop_id` (policy).

If you need a focused map of UI entry points:
- Indexing main edit page and its “/edit” variant both trigger file history and CofO sync with `prop_id` when present.
- PRA/PIC UIs display/read stored `prop_id` but don’t enforce allocation.
- File History modal shows the `prop_id` already stored in `file_history_staging`.


## Deliverables in this change
- SQL: `database_scripts/prop_id_master_table.sql` (creates the master table, indexes, and conflict view).
- Rollout steps: see `docs/PROP_ID_MASTER_DB_TASKS.md` for the ordered execution plan and verification queries.

## Non-goals / guardrails
- Do **not** reintroduce `prop_id` into workflow helper tables like `file_indexings`—link via file number + master table instead.
- No schema changes to `fileNumber` ledger; keep joins by file number as today.
- Leave temp file numbers without prop_id; allocation only happens once an MLS/KANGIS/ST/official file number is present.
