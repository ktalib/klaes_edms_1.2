# KLAES GIS EDMS · AI Agent Playbook

Concise, project-specific guidance for AI helpers working in this Laravel 9 monolith.

## System map
- **Architecture**: Laravel 9 monolith orchestrating land administration workflows end‑to‑end (applications, billing, GIS, scanning, caveats, activity logs).
- **Back end**: Business logic in `app/Http/Controllers`, grouped by module (Primary, Sub, GIS, Scanning, Caveat, Activity Logs). Prefer adding new endpoints to the most recent route file (`routes/app3.php`) unless extending an existing module.
- **Frontend**: Blade + Tailwind + Alpine.js + jQuery. Views live under `resources/views/{module}` with partials in `resources/views/{module}/partials`; modals are loaded via AJAX using `data-url`/`data-size` attributes.
- **Assets**: Module‑specific JS/CSS under `public/js` and `public/css`; global bootstrap in `resources/js/app.js` and `resources/css` via Laravel Mix (`npm run dev`, `npm run prod`).
- **Data**: Primary DB is SQL Server (`sqlsrv`); MySQL is legacy/read‑only. Stored procedures and views are documented in `database_scripts/**`.

## Database & models
- Every Eloquent model must declare `protected $connection = 'sqlsrv';` and usually `protected $table`/`$primaryKey` (table names rarely follow Laravel conventions).
- Complex reads/writes typically use `DB::connection('sqlsrv')` with explicit table names (see `PrimaryActionsController`, `FileIndexController`, `CaveatController`, `ActivityLogService`).
- Before changing schema, search `database_scripts/**` and any `*_IMPLEMENTATION.md` docs for the feature to keep migrations in sync with existing SQL scripts and views.

## Auth, roles & permissions
- Auth is Laravel + Spatie Permission. Users also have a comma‑separated `assign_role` list that gates UI visibility.
- Follow the pattern: allow all when `auth()->user()->type == 'super admin'`; otherwise gate by `Auth::user()->can('permission-name')` (or `@can` in Blade).
- When adding role‑aware UI (menus, buttons, filters), mirror existing checks in controllers and Blade (see `resources/views/admin/menu.blade.php`).

## File number systems
- **Legacy + ST formats**: File numbers appear as KANGIS, MLSF, NewKANGIS, and new ST format `ST-{LAND_USE}-{YEAR}-{SERIAL}`. Always normalize/parse using helpers in `app/Helper/helper.php` instead of ad‑hoc string logic.
- **Reservation safety**: `FileNumberReservationService` (and, for ST, `STFileNumberService`) handle atomic allocation with gap‑filling to avoid duplicates under load. Never generate file numbers with plain `MAX()+1` logic.
- **ST API**: `app/Http/Controllers/STFileNumberController.php` exposes reservation/confirm/release/search endpoints; UI integration lives in `public/js/commission_new_st/**` and `resources/views/commission_new_st/index.blade.php`.
- When touching Primary/Sub/Planning flows, connect to the ST file number APIs instead of creating new numbering paths.

## Draft autosave and forms
- Draft behaviour for Primary (and related) forms is centralized in `public/js/draft-autosave.js` plus `PrimaryFormDraftController`.
- Adding/changing form fields that participate in autosave requires updating `mapFormFieldsToDatabaseColumns()` and any draft JSON mappings, otherwise conflicts or missing fields will occur.
- AJAX form endpoints should return the standardized shape: `['success' => bool, 'message' => string, 'data' => array]`, matching existing autosave and action handlers.

## Routing & domain flows
- `routes/web.php`: auth/account/settings and generic pages.
- `routes/apps.php`: Sectional Titling end‑to‑end (Primary → Sub → Survey → Planning → Approvals → Certificate).
- `routes/apps2.php`: supporting subsystems (GIS, file intake, scanning, indexing, archiving, tracking, SLTR, labels, registration).
- `routes/app3.php`: newest features and ongoing work; prefer this for new modules.
- `routes/caveat.php` + specialised files (`file_numbers.php`, `file_decommissioning.php`, `bills_api.php`, etc.) host focused APIs—extend them rather than creating duplicates.
- Keep specific routes (AJAX/JSON) above wildcard `{id}` routes, mirroring existing files.

## Shared utilities & cross‑cutting helpers
- Global helpers live in `app/Helper/helper.php` (settings, IDs, notifications, file‑number utilities). Reuse these instead of re‑implementing common patterns.
- Custom middleware `XSS` is applied to most authenticated routes; new routes that accept form input should sit inside the same middleware groups.
- Activity logging cross‑cuts many modules; see `ActivityLogService` and `UserActivityLog` for patterns when adding new user‑visible actions.

## Data & document workflows
- Core ST entities: `mother_applications` and `subapplications` in SQL Server; documents and scanning metadata live in `file_indexings`, `pagetypings`, and related batch tables.
- File intake pipeline: `FileIndexing` → `Scanning` → `PageTyping` → `PTQ` → `FileArchive`; controllers and Blade views for each step live under matching module folders.
- When adding document fields or changing JSON structures (e.g., `documents` on applications), update both the SQL Server persistence layer and the Blade/UI components that render previews and downloads.

## Frontend patterns
- Blade views usually start from templates in `resources/views/template/**` and are decomposed into partials (especially for large forms and action sheets).
- Modals/partials typically live under `resources/views/{module}/partials/` and load via AJAX; triggers carry `data-url` and optional `data-size` attributes.
- For advanced components (e.g., carousels, dashboards, commission tabs), follow existing JS modules: `public/js/commission_new_st/**`, `public/js/sub-application/**`, `public/js/gap-filling-notification.js`.

## Testing & debugging
- There is no formal PHPUnit suite; feature testing relies on `test_*.html`, `test_*.php`, and `/debug` endpoints in controllers—search for your module name + `test`/`debug` before adding new diagnostics.
- Implementation docs (`*_IMPLEMENTATION.md`, `*_COMPLETE.md`, `SYSTEM_ARCHITECTURE_DOCUMENTATION.md`, `*_SUMMARY.md`) contain per‑feature setup, test flows, and gotchas—consult and update them when changing behaviour.
- Many flows have `?bypass=true` or debug routes to enable partial testing without full prerequisites; prefer these over hard‑coding test shortcuts.

## Developer workflows & commands
- Run migrations against SQL Server: `php artisan migrate --database=sqlsrv`; use `php artisan migrate:status --database=sqlsrv` when diagnosing issues.
- Clear caches after config/permission changes: `php artisan config:clear; php artisan cache:clear` (in that order).
- Custom artisan commands in `routes/console.php` (e.g., `test:reservation-fix`, `check:reservations`, `test:migration-complete`) are the canonical way to validate file‑number and reservation systems.
- For large refactors or new modules, update or create the relevant `*_IMPLEMENTATION.md`/`*_COMPLETE.md` file describing routes, DB changes, and manual test steps.

## Gotchas & best practices
- Many Blade views assume certain variables are pre‑bound from controllers (`departments`, `roles`, `land_use_options`, etc.); when adding new views, mirror the `compact()` patterns from sibling controllers.
- Foreign key constraints are active in SQL Server—when testing features that link to `mother_applications`/`subapplications` or `st_file_numbers`, use NULL or valid IDs rather than arbitrary values.
- Print templates live under `resources/views/*/print/` and contain watermark/original‑vs‑copy logic; reuse these patterns for new printable outputs.
- When introducing new cross‑cutting behaviour (logging, file numbers, batching), check for existing helpers/services first to avoid divergent implementations.

Review these notes before coding and extend them as you discover new patterns so future agents stay aligned.