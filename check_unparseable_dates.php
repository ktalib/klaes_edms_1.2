<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = Illuminate\Support\Facades\DB::connection('sqlsrv')
    ->table('pra')
    ->select('id', 'mlsFNo', 'created_at')
    ->whereNotNull('created_at')
    ->orderBy('id', 'desc')
    ->limit(10000)
    ->get();

$bad = [];

foreach ($rows as $row) {
    $iso = App\Support\DateFormatter::toISO($row->created_at);
    if ($iso === null) {
        $bad[] = $row;
        if (count($bad) >= 100) {
            break;
        }
    }
}

echo 'checked=' . count($rows) . PHP_EOL;
echo 'unparseable=' . count($bad) . PHP_EOL;

foreach ($bad as $item) {
    echo $item->id . ' | ' . ($item->mlsFNo ?? '-') . ' | ' . $item->created_at . PHP_EOL;
}
