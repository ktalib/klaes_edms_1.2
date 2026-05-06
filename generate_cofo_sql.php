<?php
/**
 * Generate SQL INSERT statements from CofO CSV for production deployment.
 * Outputs to: docs/data/COFO_DATA/cofo_insert.sql
 */

$csvPath = __DIR__ . '/docs/data/COFO_DATA/COFOREADY.csv';

$raw = file_get_contents($csvPath);
if ($raw === false) {
    echo "Failed to read CSV file.\n";
    exit(1);
}
$raw = str_replace(["\r\n", "\r"], "\n", $raw);
$lines = array_filter(explode("\n", $raw), fn($l) => trim($l) !== '');

$sections = [
    'cofo_ready'     => [],
    'cofo_collected' => [],
];

$currentSection = null;

foreach ($lines as $line) {
    $trimmed = trim($line);

    if (stripos($trimmed, 'C OF O READY FOR COLLECTION') !== false) {
        $currentSection = 'cofo_ready';
        continue;
    }
    if (stripos($trimmed, 'C OF O ALREADY COLLECTED') !== false) {
        $currentSection = 'cofo_collected';
        continue;
    }
    if ($currentSection !== null && stripos($trimmed, 'S/N') === 0) {
        continue;
    }
    if ($currentSection === null) {
        continue;
    }

    $fields = str_getcsv($trimmed);
    if (count($fields) < 3) {
        continue;
    }

    $fileNumber = trim($fields[1] ?? '');
    $fileName   = trim($fields[2] ?? '');

    if ($fileNumber === '') {
        continue;
    }

    $sections[$currentSection][] = [
        'file_number' => strtoupper($fileNumber),
        'file_title'  => $fileName,
    ];
}

/** Escape a value for SQL Server string literal */
function sqlEscape(string $val): string {
    return str_replace("'", "''", $val);
}

$outputPath = __DIR__ . '/docs/data/COFO_DATA/cofo_insert.sql';
$handle = fopen($outputPath, 'w');

fwrite($handle, "-- =============================================================\n");
fwrite($handle, "-- CofO Ready for Collection & CofO Already Collected\n");
fwrite($handle, "-- Insert into [duplicate_fileno] table\n");
fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
fwrite($handle, "-- Total rows: " . (count($sections['cofo_ready']) + count($sections['cofo_collected'])) . "\n");
fwrite($handle, "-- =============================================================\n\n");

fwrite($handle, "SET NOCOUNT ON;\n");
fwrite($handle, "BEGIN TRANSACTION;\n\n");

$chunkSize = 100;

foreach ($sections as $type => $rows) {
    $prefix = '[' . strtoupper($type) . ']';
    $label = $type === 'cofo_ready' ? 'CofO Ready for Collection' : 'CofO Already Collected';

    fwrite($handle, "-- -------------------------------------------------------------\n");
    fwrite($handle, "-- $label  ($prefix)  —  " . count($rows) . " rows\n");
    fwrite($handle, "-- -------------------------------------------------------------\n\n");

    $chunks = array_chunk($rows, $chunkSize);

    foreach ($chunks as $chunkIndex => $chunk) {
        fwrite($handle, "INSERT INTO [duplicate_fileno]\n");
        fwrite($handle, "    ([file_number], [file_title], [registry], [comment], [category], [source], [created_by], [created_at], [updated_at])\n");
        fwrite($handle, "VALUES\n");

        $valueSets = [];
        foreach ($chunk as $row) {
            $fn = sqlEscape($row['file_number']);
            $ft = sqlEscape($row['file_title']);
            $valueSets[] = "    (N'$fn', N'$ft', N'KANGIS Registry', N'$prefix', N'$prefix', N'csv_import', 1, GETDATE(), GETDATE())";
        }

        fwrite($handle, implode(",\n", $valueSets) . ";\n\n");
    }
}

fwrite($handle, "COMMIT TRANSACTION;\n");
fwrite($handle, "SET NOCOUNT OFF;\n\n");

fwrite($handle, "-- Verify counts\n");
fwrite($handle, "SELECT\n");
fwrite($handle, "    SUM(CASE WHEN [comment] LIKE '[COFO_READY]%' THEN 1 ELSE 0 END) AS [cofo_ready_count],\n");
fwrite($handle, "    SUM(CASE WHEN [comment] LIKE '[COFO_COLLECTED]%' THEN 1 ELSE 0 END) AS [cofo_collected_count]\n");
fwrite($handle, "FROM [duplicate_fileno]\n");
fwrite($handle, "WHERE [comment] LIKE '[COFO_%';\n");

fclose($handle);

echo "SQL file generated: $outputPath\n";
echo "CofO Ready rows: " . count($sections['cofo_ready']) . "\n";
echo "CofO Collected rows: " . count($sections['cofo_collected']) . "\n";
