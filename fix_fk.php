<?php
require __DIR__ . '/vendor/autoload.php';
\ = require_once __DIR__ . '/bootstrap/app.php';
\->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

DB::connection('sqlsrv')->statement('ALTER TABLE decommissioned_files DROP CONSTRAINT FK_decommissioned_files_fileNumber');
echo " Dropped constraint.\\n\;

DB::connection('sqlsrv')->statement('ALTER TABLE decommissioned_files ADD CONSTRAINT FK_decommissioned_files_fileNumber FOREIGN KEY (file_number_id) REFERENCES fileNumber(id)');
echo \Added proper constraint.\\n\;
