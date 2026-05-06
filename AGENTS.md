# KLAES Project Agent Instructions

Use this file as the canonical project guidance for any automated changes.

## Project Context
- Framework: Laravel 9 monolith.
- Primary database: SQL Server (`sqlsrv`). MySQL is legacy/read-only.
- Prop ID (12-digit) is the cross-table unique identifier; use `PropertyIdAllocationService` for consistency.

## Data & Model Conventions
- Every Eloquent model must declare `protected $connection = 'sqlsrv';`.
- Do not use `MAX(id) + 1` for new IDs; use the project services that generate sequential IDs safely.

## Application Types
- PUA: `is_sua_unit = 0`, linked to mother applications. Can inherit planning recommendation if mother is approved.
- SUA: `is_sua_unit = 1`, processed independently.

## Entity/Customer Handling
- Entity types: `Individual`, `Corporate`, `Multiple`.
- Store `Multiple` in the database and display as `Multiple Owners` in UI.
- Always check for similar entities before creation to avoid duplicates.

## Registration Workflow
- RDS must be generated before CoR.
- ST Assignment (Transfer of Title) RDS must exist before Sectional Titling CofO RDS can be generated.

## Caveat System
- Registration number format: `REG/YYYY/P{page_number}`.
- Caveat number format: `CAV/YYYY/{sequential}`.
- Always link to `prop_id` so history and legal searches work.

## File Numbering & Normalization
Normalize file numbers before query/save:
1. Trim and uppercase.
2. Replace `O-variants` with `O`; `/`, `=`, `_` with `-`.
3. Split concatenated file numbers.
4. Normalize prefixes: `CN` -> `CON`, `C0M` -> `COM`, `R3S` -> `RES`.
5. Normalize years: expand 2-digit years, fix `18XX` to `19XX`.
6. Clean serial digits: `O` -> `0`, `I/l` -> `1` in numeric positions.
7. Classify pattern (ST, MLS, KANGIS).
8. Validate pattern integrity.

## EDMS & Registry
- Registries are defined in the `registries` table; do not hardcode registry lists.
- Use `Registry::where('is_active', true)->get()` for dropdowns.
- EDMS flow: Blind Scan -> Scan Upload -> Page Typing.
- Folder roots under `storage/app/public/EDMS/` with registry names using underscores.

## Routing
- Routes are organized in `routes/app3.php`, `routes/apps.php`, `routes/apps2.php`.
- Place AJAX/JSON endpoints above wildcard routes to avoid capture.

## Logging & Cache
- Log CRU actions via `AuditService::logAction()`.
- Cache clearing order: `php artisan config:clear` then `php artisan cache:clear`.

## Placeholder Data
- Use native Northern Nigeria names for placeholders.
- First name placeholder should be `Musa`.
- Examples: `Amina`, `Sani`, `Zainab`, `Yakubu`, `Hauwa`, `Bashir`, `Hadiza`.
