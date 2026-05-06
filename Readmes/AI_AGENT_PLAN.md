# AI Agent Fix-It Plan — Upgrade KLAES EDMS

You are GitHub Copilot working inside VS Code.  
Your job: guide code updates in this Laravel repo to implement the new EDMS workflow.

## Core Changes Required
- Implement new directory conventions: Blind Scans → Scan Upload → Pagetyping → QC → Archive (Doc-WARE).
- Add “Upload More” and “PageType More” actions with `is_updated` flag logic.
- Clone pagetyping UI into a **QC interface** with **Override button** and audit trail.
- Support **Property Records Assistant (PRA)** capture at stage 6b (post-scan upload) and 7b (post-pagetyping).
- Add batching (100 files), barcode/QR generation, and tracking sheet PDF creation.
- Enforce file status flow: indexed → uploaded → pagetyped → qc_passed → archived. Integrate with file tracker table.
- On archive, replicate combined PDF to Doc-WARE location.

## Database / Models
- Extend `file_indexings` with `is_updated`, `batch_id`, `has_qc_issues`.
- Link `file_trackings` to `file_indexings` (FK).
- Add `batches`, `page_typings`, `property_records`, `barcodes` tables/models.
- Extend audit logging to cover new actions.

## UI
- Don’t overwrite existing custom UI (e.g., Unindexed Files). Extend via new components, tabs, or action menus.
- Ensure Upload More, PageType More, and QC Override are intuitive and logged.

## Considerations
- Use queues for heavy PDF/scan operations.
- Add role checks: only QC staff can override, only Admin/Archivist can archive.
- Handle duplicate scans gracefully.
- Add migration + seeder files.
- Update README/CHANGELOG.

 