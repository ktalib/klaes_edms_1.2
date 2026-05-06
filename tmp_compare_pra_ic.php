<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

$conn = DB::connection('sqlsrv');

$pra = $conn->table('INFORMATION_SCHEMA.COLUMNS')
    ->where('TABLE_NAME', 'pra')
    ->pluck('COLUMN_NAME')
    ->map(fn($c) => strtolower($c))
    ->toArray();

$ic = $conn->table('INFORMATION_SCHEMA.COLUMNS')
    ->where('TABLE_NAME', 'instrument_capture')
    ->pluck('COLUMN_NAME')
    ->map(fn($c) => strtolower($c))
    ->toArray();

$common = array_values(array_intersect($pra, $ic));
sort($common);

echo 'PRA cols: ' . count($pra) . PHP_EOL;
echo 'instrument_capture cols: ' . count($ic) . PHP_EOL;
echo 'Common cols: ' . count($common) . PHP_EOL;

foreach ($common as $col) {
    echo $col . PHP_EOL;
}
