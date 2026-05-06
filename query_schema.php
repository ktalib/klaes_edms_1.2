<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$indexes = Illuminate\Support\Facades\DB::connection('sqlsrv')->select("EXEC sp_helpindex 'PropID_Master'");
echo json_encode($indexes, JSON_PRETTY_PRINT);
