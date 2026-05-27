# Title Status — Feature Notes

## Overview

Title Status is a flagging/archiving workflow for land files that have undergone one of five legal/administrative actions. It is **not** a file commissioning flow. Files are **never deleted** — they are flagged in place, copied to archive tables, and permanently marked.

---

## The 5 Title Status Types

| # | Type | Applies To | Decommissions? |
|---|------|-----------|----------------|
| 1 | Withdrawal (Application) | New Files | Yes |
| 2 | Cancellation (RofO) | Existing or New Files | Yes |
| 3 | Revoke (CofO) | Existing Files | Yes |
| 4 | Litigation | Existing or New Files | Yes |
| 5 | Amendment/Reconsideration (Application/RofO/CofO) | Any | Yes |

> **Plot Separation is separate** — it goes through File Commissioning. These five types do NOT.

---

## User Flow

1. User clicks **Create Title Status** → selects one of the 5 types.
2. A search card appears (similar to legacy Parcel Update card) — user searches for a file number.
3. File number can be newly commissioned or long-existing.
4. Fields auto-populate from the selected file record.
5. An **auto-generated editable comment** appears (same pattern as Caveat).
6. User selects an **Authority** from a dropdown (e.g. Governor's Directive, Court Order No. 123).
7. User edits the comment if needed, then clicks **Save**.

---

## What Happens on Save

1. **Update the source file record** (in whichever table it lives) with:
   - `title_status = 1`
   - `title_status_type = 'withdrawn' | 'cancelled' | 'revoked' | 'litigation' | 'amendment'`
   - `title_status_remark = <editable comment>`

2. **Set `is_decommissioned = 1`** on the original file record.

3. **Copy record** to both:
   - `deprecated_records`
   - `decommissioned_files`

4. **Original record stays** in its source table — never deleted.
   - All transactions against it carry the comment.

5. **Log entry** created in `title_status_applications` (front-end visible table).

---

## Tables That Need `title_status` Fields Added (Migration)

All source file tables must receive these three new columns:

| Column | Type | Notes |
|--------|------|-------|
| `title_status` | `tinyint` | 0 = active, 1 = flagged |
| `title_status_type` | `varchar(50)` | withdrawn / cancelled / revoked / litigation / amendment |
| `title_status_remark` | `text` | Editable auto-comment |

**Tables:**
- `file_indexings`
- `pra`
- `fileNumber`
- `CofO_staging`
- `mls_file_no`
- `customers_staging`
- `entities_staging`
- `instrument_capture`
- `deed_registrations`
- `file_history_staging`

---

## Destination / Tracking Tables

| Table | Role |
|-------|------|
| `title_status_applications` | Main log — front-end visible. **Needs redesign.** |
| `deprecated_records` | Existing table — record gets copied here |
| `decommissioned_files` | Existing table — record gets copied here |

---

## `title_status_applications` — Redesigned Fields

The current table is not properly structured. Proper fields:

| Field | Type | Notes |
|-------|------|-------|
| `id` | bigint PK | |
| `url` | varchar(50) | land / deeds / cadastral / dciv / pp |
| `title_type` | varchar(100) | one of the 5 types |
| `source_table` | varchar(100) | which table the file came from |
| `source_id` | bigint | ID of record in source table |
| `file_no` | varchar(255) | copied from source |
| `file_title` | varchar(500) | copied from source |
| `applicant_name` | varchar(255) | copied from source |
| `plot_no` | varchar(100) | copied from source |
| `location` | varchar(2000) | copied from source |
| `title_no` | varchar(255) | C of O / RofO number |
| `date_of_issue` | date | |
| `date_of_expiry` | date | |
| `authority` | varchar(255) | from dropdown |
| `authority_reference` | varchar(500) | e.g. "Court Order No. 123" |
| `remark` | text | auto-generated, editable |
| `status` | varchar(30) | pending / approved / rejected |
| `captured_by` | bigint | |
| `updated_by` | bigint | |
| `approved_by` | bigint | |
| `approved_at` | timestamp | |
| `is_deleted` | tinyint | |
| `deleted_by` | bigint | |
| `deleted_at` | timestamp | |
| `created_at` / `updated_at` | timestamps | |

---

## Authority Dropdown Options

To be confirmed — suggested list:
- Governor's Directive
- Court Order
- Ministerial Directive
- Administrative Decision
- Legal Notice
- Other

---

## Auto-Comment Templates (per type)

- **Withdrawal:** *"File [file_no] — [file_title] has been Withdrawn on [date]. Authority: [authority]. [reference]."*
- **Cancellation:** *"File [file_no] — [file_title] RofO has been Cancelled on [date]. Authority: [authority]. [reference]."*
- **Revoke:** *"File [file_no] — [file_title] C of O has been Revoked on [date]. Authority: [authority]. [reference]."*
- **Litigation:** *"File [file_no] — [file_title] is under Litigation as of [date]. Authority: [authority]. [reference]."*
- **Amendment:** *"File [file_no] — [file_title] Application/RofO/CofO has been Amended/Reconsidered on [date]. Authority: [authority]. [reference]."*
