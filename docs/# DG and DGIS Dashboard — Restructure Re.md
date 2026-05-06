# DG and DGIS Dashboard — Restructure Request

## Goal
Refactor the DG and DGIS views so they have exactly **3 tabs** (no more, no less), and remove the file-creation/tracking entry points that should only belong to regular users.

## Required Tabs (in this order)

1. **File Log Manager** — displays the existing **Track Existing File** logs/content.
2. **Track New File** — displays the **New KANGIS — 8-Step Tracking Workflow**.
3. **Request Land File** — displays the **Cross-Registry Request** view.

## Functional Requirements

- Both **DG** and **DGIS** must be able to submit a Cross-Registry Request, and the **request status must be clearly visible** (e.g., Pending, Approved, Rejected).
- **Bug to fix:** Files requested from Lands via **Cross-Registry Request** are currently appearing under the **File Log Manager (Track Existing File)** tab. They should **only** appear under the new **Request Land File** tab.
- Ensure Cross-Registry Request items are filtered out of File Log Manager and routed exclusively to the Request Land File tab.

## Navigation Changes (DG & DGIS only)

- **Hide** the top-level nav buttons for:
  - `Track Existing File`
  - `Track New File`
- Rationale: those actions belong to regular users. DG and DGIS should only be able to **view files, take action on them, and perform Cross-Registry Requests** — they should not initiate Track Existing/New File flows from the nav.

## Acceptance Criteria

- [ ] DG and DGIS see only the 3 tabs listed above.
- [ ] `Track Existing File` and `Track New File` nav buttons are hidden for DG and DGIS (still visible for regular users).
- [ ] Cross-Registry Request entries no longer appear in File Log Manager.
- [ ] Cross-Registry Request entries appear in the Request Land File tab with clear status indicators.
- [ ] Regular user experience is unchanged.