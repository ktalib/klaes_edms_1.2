# Title Status — Feature Notes

## Overview

Title Status is a flagging/archiving workflow for land files that have undergone one of six legal/administrative actions. It is **not** a file commissioning flow. Files are **never deleted** — they are flagged in place, copied to archive tables, and permanently marked.

---

## The 6 Title Status Types

| # | Type | Applies To | Decommissions? |
|---|------|-----------|----------------|
| 1 | Withdrawal (Application) | New Files | Yes |
| 2 | Cancellation (RofO) | Existing or New Files | Yes |
| 3 | Revoke (CofO) | Existing Files | Yes |
| 4 | Litigation | Existing or New Files | Yes |
| 5 | Amendment/Reconsideration (Application/RofO/CofO) | Any | Yes |
| 6 | Surrender | Any (voluntary relinquishment) | Yes |

> **Plot Separation is separate** — it goes through File Commissioning. These six types do NOT.

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

## Initiated By (replaces Authority in the form)

The Create/Edit form now captures **Initiated By** instead of Authority. The available options depend on the title status type — single-option types lock the dropdown:

| Type | Initiated By options | Locked? |
|---|---|---|
| Withdrawal (Application) | Applicant | locked |
| Cancellation (RofO) | Ministry, Allottee | no |
| Revoke (CofO) | Court Order | locked |
| Litigation | Ministry, Allottee | no |
| Amendment/Reconsideration | Ministry, Allottee | no |
| Surrender | Applicant | locked |

When **Applicant** or **Allottee** is selected, the remark substitutes the actual file holder's name (`applicant_name` → `file_title` fallback). **Ministry** and **Court Order** render literally.

The per-type map lives in [`TitleStatusApplication::INITIATED_BY_BY_TYPE`](app/Models/TitleStatusApplication.php) and is exposed to JS via the `TS_INITIATED_BY_BY_TYPE` global, consumed by `tsApplyInitiatedByOptions()` in [public/js/title_status.js](public/js/title_status.js).

> The `authority` and `authority_reference` columns remain on `title_status_applications` for legacy records but are no longer captured from the UI. New entries populate `initiated_by` and `reason`.

---

## Unified Auto-Comment Template

All 6 types share the same remark template:

> `[Status type] was initiated by [Ministry/Allottee] on [Time/Date] due to [Reason]`

Status type verbs used:

| Type constant | Verb in remark |
|---|---|
| Withdrawal (Application) | Withdrawal |
| Cancellation (RofO) | Cancellation |
| Revoke (CofO) | Revocation |
| Litigation | Litigation |
| Amendment/Reconsideration (Application/RofO/CofO) | Amendment/Reconsideration |
| Surrender | Surrender |

Examples:
- `Surrender was initiated by Stacy Moore on 01/06/2026 14:32 due to relocation to another state` (Applicant → name substituted)
- `Cancellation was initiated by Ministry on 01/06/2026 09:15 due to incomplete documentation`
- `Revocation was initiated by Court Order on 01/06/2026 11:00 due to suit no. KN/HC/CV/123/2025`

---

## Schema Additions (2026-05-30)

Migration `2026_05_30_140000_add_initiated_by_reason_to_title_status_applications.php` adds:

| Column | Type | Notes |
|---|---|---|
| `initiated_by` | varchar(50) | Ministry / Allottee |
| `reason` | text | Free text reason |
