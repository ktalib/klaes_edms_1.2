# Registry Digital Sources

Folder-based digital copies for the **SLTR**, **Cadastral**, **KANGIS**, and
**Physical Planning** registries. When a user selects a *Registry (Origin)* in
File Search (mobile dashboard or `/create-file-tracker/quick-search`), the system
looks up the file folder inside that registry's directory and shows the scanned
image(s)/document(s) if present.

> The existing **Land** registries keep using the FileIndexing-based digital
> library (page-typings / scannings). This feature only covers registries whose
> scans are stored as raw folders on disk.

## On-disk layout

```
{base_path}/{registry_folder}/{file_number}/{category}/{image…}
```

Example:

```
F:\storage\app\public\EDMS\UPLOAD\SLTR_Registry\SLTR-220944\A4\scan-001.jpg
```

- **registry_folder** — `SLTR_Registry`, `Cadastral_Registry`, `KANGIS_Registry`, `Physical_Planning_Registry`
- **file_number** — the folder name, equal to the file number (e.g. `SLTR-220944`)
- **category** — first sub-folder, e.g. `A4` (optional; deeper nesting is also scanned)

## Database schema (`sqlsrv` connection)

| Table | Purpose |
|-------|---------|
| `registry_sources` | Lookup of the 4 registries (`name`, `code`, `folder`, `is_active`, `last_synced_at`). |
| `registry_file_folders` | One row per file folder (`registry_source_id`, `file_number`, `relative_path`, `document_count`). Unique on `(registry_source_id, file_number)`. |
| `registry_file_documents` | One row per scanned file (`registry_file_folder_id`, `category`, `filename`, `relative_path`, `extension`, `file_size`). Unique on `(registry_file_folder_id, relative_path)`. |

`relative_path` is stored relative to the **public** storage disk root
(`EDMS/UPLOAD/…`), so browser URLs resolve via `Storage::disk('public')->url()`
regardless of the absolute base path on the server.

## Configuration — `config/registry_sources.php`

| Key | Env override | Default |
|-----|--------------|---------|
| `base_path` | `REGISTRY_SOURCE_BASE_PATH` | `storage_path('app/public/EDMS/UPLOAD')` |
| `public_prefix` | `REGISTRY_SOURCE_PUBLIC_PREFIX` | `EDMS/UPLOAD` |
| `allowed_extensions` | — | jpg, jpeg, png, gif, webp, bmp, tif, tiff, pdf |
| `image_extensions` | — | (image types only) |
| `registries` | — | the 4 registry definitions (name / code / folder / aliases) |

On **production**, if the upload root is not under the app's `storage/app/public`,
set the absolute path in `.env`:

```env
REGISTRY_SOURCE_BASE_PATH="F:\storage\app\public\EDMS\UPLOAD"
```

Leave `REGISTRY_SOURCE_PUBLIC_PREFIX=EDMS/UPLOAD` so URLs still resolve to
`/storage/EDMS/UPLOAD/…` (requires the usual `php artisan storage:link`).

## First-time setup (deploy)

```bash
# 1. Create the tables
php artisan migrate

# 2. Seed lookups + backfill existing folders/documents (idempotent)
php artisan db:seed --class="Database\Seeders\RegistrySourceSeeder"
```

`RegistrySourceSeeder` seeds the 4 lookup rows and runs a full folder scan.
Re-running it updates rows in place — it never creates duplicates.

## Synchronisation command

Run whenever new scans are dropped into the registry folders. Safe to re-run.

```bash
# Scan all active registries
php artisan registry:sync

# Scan a single registry by code (SLTR | CAD | KANGIS | PP)
php artisan registry:sync --registry=SLTR

# Only refresh the lookup table (no folder scan)
php artisan registry:sync --lookups
```

Output reports registries scanned, folders processed, documents found, and new
rows per registry. Results are also written to the application log.

### Idempotency / production safety

- Lookups are matched on the stable `code` (`updateOrCreate`).
- Folders are matched on `(registry_source_id, file_number)`.
- Documents are matched on `(registry_file_folder_id, relative_path)`.

Re-running adds only genuinely new folders/documents; nothing is duplicated and
nothing existing is deleted. `document_count` on each folder is recalculated on
every run.

### Scheduling (optional)

Add to `app/Console/Kernel.php` `schedule()` to keep the DB in sync automatically:

```php
$schedule->command('registry:sync')->hourly()->withoutOverlapping();
```

## Runtime lookup

- **Endpoint:** `POST /digital-request/registry-files` (auth) — name
  `digital-request.registry-files`.
- **Body:** `{ "file_no": "SLTR-220944", "registry": "SLTR Registry" }`
  (`registry` accepts the dropdown name or a registry code; omit to search all).
- **Response:** `{ available, count, registry, file_number, files: [{ name, category, ext, is_image, url }] }`.

`RegistrySourceService::resolveSource()` maps the selected registry (name/code,
plus the config `aliases`) to a folder-based source, then matches the file
folder by file number (case-insensitive). If the folder is absent or empty,
`available` is `false` and the UI shows a "no digital copy" notice.

## Files

- `config/registry_sources.php`
- `database/migrations/2026_06_24_000200_create_registry_source_tables.php`
- `app/Models/RegistrySource.php`, `RegistryFileFolder.php`, `RegistryFileDocument.php`
- `app/Services/RegistrySourceService.php`
- `app/Console/Commands/SyncRegistrySources.php` (`registry:sync`)
- `database/seeders/RegistrySourceSeeder.php`
- `DigitalFileRequestController::registryFiles()` + route in `routes/web.php`
- UI: `resources/views/mobile/dashboard.blade.php`,
  `resources/views/create_file_tracker_page/quick_search.blade.php`
