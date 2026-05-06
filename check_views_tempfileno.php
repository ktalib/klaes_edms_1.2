<?php
/**
 * check_views_tempfileno.php
 * Check all SQL Server views/objects that reference pra or instrument_capture
 * with temp_fileno, and any schema-bound views that could block ALTER COLUMN.
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
    echo "Connected OK\n\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// ── 1. Schema-bound views on pra or instrument_capture ───────────────────────
// WITH SCHEMABINDING blocks ALTER COLUMN entirely
echo "=== 1. Schema-bound views referencing pra or instrument_capture ===\n";
$sql = "
SELECT DISTINCT
    o.name        AS view_name,
    CASE WHEN OBJECTPROPERTY(o.object_id, 'IsSchemaBound') = 1 THEN 'YES' ELSE 'no' END AS is_schema_bound
FROM sys.sql_expression_dependencies d
JOIN sys.objects    o ON d.referencing_id = o.object_id
JOIN sys.objects    t ON d.referenced_id  = t.object_id
WHERE t.name IN ('pra','instrument_capture')
  AND o.type = 'V'
ORDER BY o.name;
";
$found = false;
foreach ($pdo->query($sql) as $row) {
    $sb = $row['is_schema_bound'] === 'YES' ? 'YES *** SCHEMA BOUND — will block ALTER ***' : 'no';
    echo "  VIEW: [{$row['view_name']}]  schema_bound={$sb}\n";
    $found = true;
}
if (!$found) echo "  None found.\n";

// ── 2. All views / stored procs / functions that reference temp_fileno ────────
echo "\n=== 2. All objects referencing temp_fileno in their definition ===\n";
$sql2 = "
SELECT
    o.name    AS object_name,
    o.type_desc
FROM sys.sql_modules m
JOIN sys.objects    o ON m.object_id = o.object_id
WHERE m.definition LIKE '%temp_fileno%'
ORDER BY o.type_desc, o.name;
";
$found2 = false;
foreach ($pdo->query($sql2) as $row) {
    echo "  [{$row['object_name']}]  type={$row['type_desc']}\n";
    $found2 = true;
}
if (!$found2) echo "  None found.\n";

// ── 3. Computed columns on pra that reference temp_fileno ─────────────────────
echo "\n=== 3. Computed columns on pra or instrument_capture using temp_fileno ===\n";
$sql3 = "
SELECT
    o.name  AS table_name,
    c.name  AS col_name,
    cc.definition
FROM sys.computed_columns cc
JOIN sys.columns          c  ON cc.object_id = c.object_id AND cc.column_id = c.column_id
JOIN sys.objects          o  ON cc.object_id = o.object_id
WHERE o.name IN ('pra','instrument_capture')
  AND cc.definition LIKE '%temp_fileno%';
";
$found3 = false;
foreach ($pdo->query($sql3) as $row) {
    echo "  [{$row['table_name']}].[{$row['col_name']}] = {$row['definition']}\n";
    $found3 = true;
}
if (!$found3) echo "  None found.\n";

// ── 4. Foreign keys that reference temp_fileno ────────────────────────────────
echo "\n=== 4. Foreign keys referencing temp_fileno ===\n";
$sql4 = "
SELECT fk.name AS fk_name, OBJECT_NAME(fk.parent_object_id) AS parent_table,
       c.name AS col_name
FROM sys.foreign_key_columns fkc
JOIN sys.foreign_keys fk ON fkc.constraint_object_id = fk.object_id
JOIN sys.columns c ON fkc.parent_object_id = c.object_id AND fkc.parent_column_id = c.column_id
WHERE (OBJECT_NAME(fkc.parent_object_id) IN ('pra','instrument_capture')
    OR OBJECT_NAME(fkc.referenced_object_id) IN ('pra','instrument_capture'))
  AND c.name = 'temp_fileno';
";
$found4 = false;
foreach ($pdo->query($sql4) as $row) {
    echo "  FK [{$row['fk_name']}] on [{$row['parent_table']}].[{$row['col_name']}]\n";
    $found4 = true;
}
if (!$found4) echo "  None found.\n";

echo "\n=== Summary ===\n";
echo "- Schema-bound views blocking ALTER  : " . ($found  ? "YES — check above" : "NONE (safe)") . "\n";
echo "- Objects referencing temp_fileno    : " . ($found2 ? "YES — review above" : "NONE") . "\n";
echo "- Computed columns using temp_fileno : " . ($found3 ? "YES — check above" : "NONE (safe)") . "\n";
echo "- Foreign keys on temp_fileno        : " . ($found4 ? "YES — check above" : "NONE (safe)") . "\n";
