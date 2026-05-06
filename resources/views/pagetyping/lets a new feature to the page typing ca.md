# Page Typing & Document Upload: Definition Code Feature

## Overview
Add a **Definition** field to Page Typing and Document Upload to enable file sorting by definition order.

## Requirements

### Page Typing Module
- Add "Definition" dropdown below Serial Number input
- Options: 0–500 (numeric)
- Behavior:
    - User selects file from Quick File Browser
    - User assigns Definition number (e.g., `1`)
    - System creates `definition_code` by prepending Definition to Page Code
        - Example: Page Code `BC-I-DOT-01` → Definition Code `1-BC-I-DOT-01`
    - File is renamed to match `definition_code` for sorting
    - Original Page Code remains unchanged

### Document Upload Module
- Add "Definition" dropdown below file selection
- Options: 0–500 (numeric)
- Behavior:
    - No Page Code available; use File Number instead
    - System creates `definition_code` using Definition + File Number
        - Example: File Number `AG-2026-13` with Definition `1` → `1-AG-2026-13`
    - File is renamed to match `definition_code` for sorting

### Impact Areas
- **Quick File Browser**: Sort files by `definition_code`
- **Document Viewer** (File Digital Archive): Display sorted by `definition_code`
- **File Archive**: Integrate `definition_code` into archive naming/retrieval

## Database & Schema
- Add `definition` (int, 0–500) column to relevant tables
- Add `definition_code` (varchar) column to store formatted code