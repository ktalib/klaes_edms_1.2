# OP Capture Existing OP - Deployment File Checklist

Use this file to verify all OP-related changes are uploaded to production.

## Core Frontend Flow
- public/js/instruments-capture.js
- resources/views/generate_fileno/mls_js.blade.php
- resources/views/components/partials/commission-fileno-modal-html.blade.php

## OP Details UI (Change of Name page)
- resources/views/lands_one_stop_shop/applications.blade.php

## Backend Controllers
- app/Http/Controllers/MlsFileNoController.php
- app/Http/Controllers/LandsOneStopShop/OpResettlementApplicationController.php

## PRA Layer
- app/Services/Pra/PraRecordService.php
- app/Services/Pra/Repositories/PraRecordRepository.php

## Model
- app/Models/MlsFileNo.php

## Database Migrations
- database/migrations/2026_03_11_120000_add_sub_source_to_mls_file_no_table.php
- database/migrations/2026_03_11_190000_add_source_pra_id_to_mls_file_no_table.php

## What each group covers
- instruments-capture.js: Capture Existing OP submit flow, PRA pre-save, post-commission PRA write.
- mls_js.blade.php: generate flow, source requirement bypass for pre-synced PRA context, PRA post-submit hook.
- MlsFileNoController.php: source_pra_id handling, OP details fallback (instrument_capture -> PRA), mirror skip for Capture Existing OP fallback path.
- OpResettlementApplicationController.php + applications.blade.php: listing/query payload and OP details modal fallback data wiring.
- PraRecordService.php + PraRecordRepository.php: temp-only allocation support and robust column key mapping.
- migration 190000: adds source_pra_id on mls_file_no.

## Production steps after upload
1. php artisan optimize:clear
2. php artisan migrate --database=sqlsrv --path="database/migrations/2026_03_11_190000_add_source_pra_id_to_mls_file_no_table.php"
3. php artisan permission:cache-reset
4. Restart PHP service/opcache (Apache/PHP-FPM) if applicable
