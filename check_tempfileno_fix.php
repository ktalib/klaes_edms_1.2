<?php
/**
 * check_tempfileno_fix.php
 * Diagnose temp_fileno column type, all dependent indexes on pra and instrument_capture,
 * and generate the complete DROP → ALTER → RECREATE fix script.
 */

$host     = '10.50.1.1';
$port     = '1433';
$database = 'klas';
$username = 'klaesDb';
$password = 'KlasdbStrongPassword123';

$dsn = "sqlsrv:Server={$host},{$port};Database={$database};TrustServerCertificate=1";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Connected to production SQL Server OK\n\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// ── 1. Column type info for temp_fileno in pra and instrument_capture ──────────
echo "=== 1. Column type: temp_fileno ===\n";
$colSql = "
SELECT
    o.name            AS table_name,
    c.name            AS column_name,
    t.name            AS data_type,
    c.max_length,
    c.is_nullable
FROM sys.columns c
JOIN sys.types   t ON c.user_type_id = t.user_type_id
JOIN sys.objects o ON c.object_id    = o.object_id
WHERE o.type = 'U'
  AND o.name IN ('pra', 'instrument_capture')
  AND c.name = 'temp_fileno'
ORDER BY o.name;
";
foreach ($pdo->query($colSql) as $row) {
    $maxLen = $row['max_length'] == -1 ? 'MAX (-1)' : $row['max_length'];
    echo "  [{$row['table_name']}].[{$row['column_name']}]  type={$row['data_type']}  max_length={$maxLen}  nullable={$row['is_nullable']}\n";
}

// ── 2. Max actual character length used in each table ─────────────────────────
echo "\n=== 2. Max actual data length in temp_fileno ===\n";
foreach (['pra', 'instrument_capture'] as $tbl) {
    try {
        $stmt = $pdo->query("SELECT MAX(LEN(CAST(temp_fileno AS NVARCHAR(MAX)))) AS max_used FROM dbo.[{$tbl}] WHERE temp_fileno IS NOT NULL;");
        $r = $stmt->fetch();
        echo "  [{$tbl}] max chars used = " . ($r['max_used'] ?? 'NULL (no rows or all NULL)') . "\n";
    } catch (PDOException $e) {
        echo "  [{$tbl}] error: " . $e->getMessage() . "\n";
    }
}

// ── 3. All indexes that reference temp_fileno on pra and instrument_capture ────
echo "\n=== 3. All indexes referencing temp_fileno ===\n";
$idxSql = "
SELECT
    o.name  AS table_name,
    i.name  AS index_name,
    i.type_desc,
    i.is_unique,
    i.is_primary_key,
    COL_NAME(ic2.object_id, ic2.column_id) AS col_name,
    ic2.key_ordinal,
    ic2.is_included_column
FROM sys.index_columns ic1
JOIN sys.columns       c   ON ic1.object_id = c.object_id AND ic1.column_id = c.column_id
JOIN sys.objects       o   ON ic1.object_id = o.object_id
JOIN sys.indexes       i   ON ic1.object_id = i.object_id AND ic1.index_id  = i.index_id
JOIN sys.index_columns ic2 ON ic1.object_id = ic2.object_id AND ic1.index_id = ic2.index_id
WHERE o.type = 'U'
  AND o.name IN ('pra', 'instrument_capture')
  AND c.name = 'temp_fileno'
ORDER BY o.name, i.name, ic2.key_ordinal;
";
$indexes = [];  // collect for fix generation
foreach ($pdo->query($idxSql) as $row) {
    $key  = $row['table_name'] . '.' . $row['index_name'];
    $indexes[$key]['table']      = $row['table_name'];
    $indexes[$key]['index']      = $row['index_name'];
    $indexes[$key]['type']       = $row['type_desc'];
    $indexes[$key]['is_unique']  = $row['is_unique'];
    $indexes[$key]['cols'][]     = ['col' => $row['col_name'], 'key_ord' => $row['key_ordinal'], 'included' => $row['is_included_column']];
    echo "  [{$row['table_name']}].[{$row['index_name']}]  col={$row['col_name']}  key_ordinal={$row['key_ordinal']}  included={$row['is_included_column']}\n";
}

// ── 4. Full index column list for each found index ────────────────────────────
echo "\n=== 4. Full column list of each dependent index ===\n";
if (!empty($indexes)) {
    $nameList = "'" . implode("','", array_map(fn($v) => $v['index'], $indexes)) . "'";
    $fullColSql = "
    SELECT
        o.name  AS table_name,
        i.name  AS index_name,
        ic.key_ordinal,
        ic.is_included_column,
        c.name  AS col_name
    FROM sys.index_columns ic
    JOIN sys.columns       c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
    JOIN sys.objects       o ON ic.object_id = o.object_id
    JOIN sys.indexes       i ON ic.object_id = i.object_id AND ic.index_id  = i.index_id
    WHERE o.name IN ('pra','instrument_capture')
      AND i.name IN ({$nameList})
    ORDER BY o.name, i.name, ic.key_ordinal;
    ";
    $fullCols = [];
    foreach ($pdo->query($fullColSql) as $row) {
        $k = $row['table_name'] . '.' . $row['index_name'];
        $fullCols[$k][] = $row;
        echo "  [{$row['table_name']}].[{$row['index_name']}]  ord={$row['key_ordinal']}  col={$row['col_name']}  included={$row['is_included_column']}\n";
    }
} else {
    echo "  No dependent indexes found.\n";
    $fullCols = [];
}

// ── 5. Generate fix SQL ───────────────────────────────────────────────────────
echo "\n\n=== 5. GENERATED FIX SCRIPT (copy and run in SSMS) ===\n";
echo str_repeat('-', 70) . "\n";

if (empty($indexes)) {
    echo "-- No dependent indexes found; just ALTER the column:\n";
    echo "ALTER TABLE dbo.pra ALTER COLUMN temp_fileno NVARCHAR(450) NULL;\n";
    if (isset($fullCols['instrument_capture'])) {
        echo "ALTER TABLE dbo.instrument_capture ALTER COLUMN temp_fileno NVARCHAR(450) NULL;\n";
    }
} else {
    // DROP phase
    echo "-- STEP 1: Drop all dependent indexes\n";
    $droppedTables = [];
    foreach ($indexes as $k => $idx) {
        echo "DROP INDEX IF EXISTS [{$idx['index']}] ON dbo.[{$idx['table']}];\n";
        $droppedTables[$idx['table']] = true;
    }

    echo "\n-- STEP 2: Alter column type\n";
    foreach (array_keys($droppedTables) as $tbl) {
        echo "ALTER TABLE dbo.[{$tbl}] ALTER COLUMN temp_fileno NVARCHAR(450) NULL;\n";
    }
    echo "GO\n";

    // RECREATE phase — build from fullCols
    echo "\n-- STEP 3: Recreate indexes\n";
    if (!empty($fullCols)) {
        foreach ($fullCols as $k => $cols) {
            [$tbl, $idxName] = explode('.', $k, 2);
            $isUnique = $indexes[$k]['is_unique'] ?? false;
            $keyColsList = [];
            $includedList = [];
            usort($cols, fn($a, $b) => $a['key_ordinal'] <=> $b['key_ordinal']);
            foreach ($cols as $c) {
                if ($c['is_included_column']) {
                    $includedList[] = "[{$c['col_name']}]";
                } else {
                    $keyColsList[] = "[{$c['col_name']}]";
                }
            }
            $unique  = $isUnique ? 'UNIQUE ' : '';
            $keyCols = implode(', ', $keyColsList);
            $incl    = !empty($includedList) ? "\n    INCLUDE (" . implode(', ', $includedList) . ")" : '';
            echo "CREATE {$unique}INDEX [{$idxName}]\n    ON dbo.[{$tbl}] ({$keyCols}){$incl};\n\n";
        }
    } else {
        echo "-- Could not retrieve full column lists; see Section 4 above and rebuild manually.\n";
    }
}

echo str_repeat('-', 70) . "\n";
echo "\nDone. Review the generated script above before running it on production.\n";
