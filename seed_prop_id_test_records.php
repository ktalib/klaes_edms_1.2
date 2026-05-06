<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$now = now();
$runTag = 'PIDTEST-' . date('Ymd-His');

$tableColumns = [];
foreach (['pra', 'file_history_staging', 'CofO_staging', 'instrument_capture'] as $table) {
    $columns = DB::connection('sqlsrv')->select(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?",
        [$table]
    );
    $tableColumns[$table] = array_map(fn ($row) => $row->COLUMN_NAME, $columns);
}

$filterPayload = function (string $table, array $payload) use ($tableColumns): array {
    $allowed = array_flip($tableColumns[$table] ?? []);

    return array_intersect_key($payload, $allowed);
};

$cases = [
    [
        'prop_id' => 1200001,
        'mls' => $runTag . '-MLS-001',
        'kangis' => $runTag . '-KANGIS-001',
        'new_kangis' => $runTag . '-NEW-001',
        'temp' => $runTag . '-TEMP-001',
        'fileno' => $runTag . '-FILE-001',
        'np_fileno' => $runTag . '-NP-001',
        'label' => 'ALL_4_TABLES',
    ],
    [
        'prop_id' => 1200002,
        'mls' => $runTag . '-MLS-002',
        'kangis' => $runTag . '-KANGIS-002',
        'new_kangis' => $runTag . '-NEW-002',
        'temp' => $runTag . '-TEMP-002',
        'fileno' => $runTag . '-FILE-002',
        'np_fileno' => $runTag . '-NP-002',
        'label' => 'PRA_AND_IC',
    ],
    [
        'prop_id' => 1200003,
        'mls' => $runTag . '-MLS-003',
        'kangis' => $runTag . '-KANGIS-003',
        'new_kangis' => $runTag . '-NEW-003',
        'temp' => $runTag . '-TEMP-003',
        'fileno' => $runTag . '-FILE-003',
        'np_fileno' => $runTag . '-NP-003',
        'label' => 'FH_AND_COFO',
    ],
];

$result = DB::connection('sqlsrv')->transaction(function () use ($cases, $now, $runTag, $filterPayload) {
    $inserted = [
        'pra' => [],
        'file_history_staging' => [],
        'CofO_staging' => [],
        'instrument_capture' => [],
    ];

    foreach ($cases as $c) {
        if (in_array($c['label'], ['ALL_4_TABLES', 'PRA_AND_IC'], true)) {
            $praPayload = $filterPayload('pra', [
                'prop_id' => $c['prop_id'],
                'mlsFNo' => $c['mls'],
                'kangisFileNo' => $c['kangis'],
                'NewKANGISFileno' => $c['new_kangis'],
                'temp_fileno' => $c['temp'],
                'fileno' => $c['fileno'],
                'np_fileno' => $c['np_fileno'],
                'transaction_type' => 'TEST INSERT',
                'purpose' => 'PROP_ID TEST',
                'Assignor' => 'Musa Bello',
                'Assignee' => 'Amina Sani',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $praId = DB::connection('sqlsrv')->table('pra')->insertGetId($praPayload);
            $inserted['pra'][] = ['id' => $praId, 'prop_id' => $c['prop_id'], 'mlsFNo' => $c['mls']];
        }

        if (in_array($c['label'], ['ALL_4_TABLES', 'FH_AND_COFO'], true)) {
            $fhPayload = $filterPayload('file_history_staging', [
                'prop_id' => $c['prop_id'],
                'mlsFNo' => $c['mls'],
                'kangisFileNo' => $c['kangis'],
                'NewKANGISFileno' => $c['new_kangis'],
                'temp_fileno' => $c['temp'],
                'fileno' => $c['fileno'],
                'transaction_type' => 'TEST INSERT',
                'party_1' => 'Musa Bello',
                'party_2' => 'Hauwa Yakubu',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $fhId = DB::connection('sqlsrv')->table('file_history_staging')->insertGetId($fhPayload);
            $inserted['file_history_staging'][] = ['id' => $fhId, 'prop_id' => $c['prop_id'], 'mlsFNo' => $c['mls']];
        }

        if (in_array($c['label'], ['ALL_4_TABLES', 'FH_AND_COFO'], true)) {
            $cofoPayload = $filterPayload('CofO_staging', [
                'prop_id' => $c['prop_id'],
                'mlsFNo' => $c['mls'],
                'kangisFileNo' => $c['kangis'],
                'NewKANGISFileno' => $c['new_kangis'],
                'temp_fileno' => $c['temp'],
                'np_fileno' => $c['np_fileno'],
                'transaction_type' => 'TEST INSERT',
                'party_1' => 'Musa Bello',
                'party_2' => 'Zainab Bashir',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $cofoId = DB::connection('sqlsrv')->table('CofO_staging')->insertGetId($cofoPayload);
            $inserted['CofO_staging'][] = ['id' => $cofoId, 'prop_id' => $c['prop_id'], 'mlsFNo' => $c['mls']];
        }

        if (in_array($c['label'], ['ALL_4_TABLES', 'PRA_AND_IC'], true)) {
            $icPayload = $filterPayload('instrument_capture', [
                'instrument_type' => 'Occupancy Permit Test',
                'prop_id' => $c['prop_id'],
                'mlsFNo' => $c['mls'],
                'kangisFileNo' => $c['kangis'],
                'NewKANGISFileno' => $c['new_kangis'],
                'temp_fileno' => $c['temp'],
                'property_description' => 'Prop ID test record ' . $c['label'],
                'property_location' => 'Nassarawa',
                'party_1_name' => 'Musa Bello',
                'party_2_name' => 'Hadiza Sani',
                'reg_date' => $now->toDateString(),
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $icId = DB::connection('sqlsrv')->table('instrument_capture')->insertGetId($icPayload);
            $inserted['instrument_capture'][] = ['id' => $icId, 'prop_id' => $c['prop_id'], 'mlsFNo' => $c['mls']];
        }
    }

    return [
        'run_tag' => $runTag,
        'cases' => $cases,
        'inserted' => $inserted,
    ];
});

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
