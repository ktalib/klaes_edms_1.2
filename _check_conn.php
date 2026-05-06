<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cfg = config('database.connections.sqlsrv');
echo "Host: " . ($cfg['host'] ?? '?') . "\n";
echo "Port: " . ($cfg['port'] ?? '?') . "\n";
echo "Database: " . ($cfg['database'] ?? '?') . "\n";
echo "Username: " . ($cfg['username'] ?? '?') . "\n";
echo "APP_ENV: " . config('app.env') . "\n";

$row = DB::connection('sqlsrv')->selectOne("SELECT @@SERVERNAME AS server_name, DB_NAME() AS db_name, SUSER_SNAME() AS login_name");
echo "Server: {$row->server_name}\nDB: {$row->db_name}\nLogin: {$row->login_name}\n";
