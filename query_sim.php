<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$sql = "SELECT id, prop_id, mlsFNo, temp_fileno, merger_group_id FROM pra WHERE mlsFNo IN ('RES-2026-2099', 'RES-2000-4828')";
echo json_encode(Illuminate\Support\Facades\DB::connection('sqlsrv')->select($sql), JSON_PRETTY_PRINT);
