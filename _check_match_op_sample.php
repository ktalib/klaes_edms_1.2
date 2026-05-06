<?php
/**
 * Diagnostic: Check RES-2026-7 across pra, fileNumber, file_indexings, mls_file_no
 * to understand the full state before implementing "Match OP".
 */

$pdo = new PDO(
    'sqlsrv:Server=.;Database=klas',
    'klasUser',
    '12WithStrongPassword',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$fileNo = 'RES-2026-7';

echo "=== PRA rows for $fileNo ===\n";
$stmt = $pdo->prepare("
    SELECT id, prop_id, mlsFNo, fileno, temp_fileno,
           instrument_type, transaction_type, op_type, op_serial_number,
           system_source, Grantor, Grantee, party_1, party_2,
           land_use, location, plot_no, tp_no, lgsaOrCity,
           created_by, created_at, updated_at
    FROM pra
    WHERE mlsFNo = ? OR fileno = ?
    ORDER BY id
");
$stmt->execute([$fileNo, $fileNo]);
$praRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($praRows) {
    foreach ($praRows as $row) {
        foreach ($row as $k => $v) {
            echo "  $k: " . ($v ?? 'NULL') . "\n";
        }
        echo "---\n";
    }
} else {
    echo "  (no rows found)\n";
}

echo "\n=== fileNumber table for $fileNo ===\n";
$stmt = $pdo->prepare("
    SELECT id, mlsfNo, FileName, tracking_id, plot_no, tp_no, lga, location,
           SOURCE, temp_fileno, is_deleted, created_at, updated_at
    FROM fileNumber
    WHERE mlsfNo = ?
    ORDER BY id DESC
");
$stmt->execute([$fileNo]);
$fnRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($fnRows) {
    foreach ($fnRows as $row) {
        foreach ($row as $k => $v) {
            echo "  $k: " . ($v ?? 'NULL') . "\n";
        }
        echo "---\n";
    }
} else {
    echo "  (no rows found)\n";
}

echo "\n=== file_indexings for $fileNo ===\n";
try {
    $stmt = $pdo->prepare("
        SELECT TOP 3 id, file_number, file_title, current_holder, original_holder,
               file_type, customer_type, registry, land_use_type,
               created_at, updated_at
        FROM file_indexings
        WHERE file_number = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$fileNo]);
    $fiRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($fiRows) {
        foreach ($fiRows as $row) {
            foreach ($row as $k => $v) {
                echo "  $k: " . ($v ?? 'NULL') . "\n";
            }
            echo "---\n";
        }
    } else {
        echo "  (no rows found)\n";
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n=== mls_file_no for $fileNo ===\n";
try {
    $stmt = $pdo->prepare("
        SELECT TOP 3 id, tracking_id, full_file_number, file_name, land_use,
               customer_type, source, sub_source, source_instrument_capture_id,
               source_pra_id, con_commissioned_at, created_at, updated_at
        FROM mls_file_no
        WHERE full_file_number = ? OR file_number = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$fileNo, $fileNo]);
    $mlsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($mlsRows) {
        foreach ($mlsRows as $row) {
            foreach ($row as $k => $v) {
                echo "  $k: " . ($v ?? 'NULL') . "\n";
            }
            echo "---\n";
        }
    } else {
        echo "  (no rows found)\n";
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n=== instrument_capture for $fileNo ===\n";
try {
    $stmt = $pdo->prepare("
        SELECT TOP 3 id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number,
               party_1_name, party_2_name, prop_id, system_source,
               is_deleted, created_at
        FROM instrument_capture
        WHERE mlsFNo = ? OR temp_fileno = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$fileNo, $fileNo]);
    $icRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($icRows) {
        foreach ($icRows as $row) {
            foreach ($row as $k => $v) {
                echo "  $k: " . ($v ?? 'NULL') . "\n";
            }
            echo "---\n";
        }
    } else {
        echo "  (no rows found)\n";
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n=== temp_fileno_sequence (last 3 entries) ===\n";
try {
    $stmt = $pdo->query("SELECT TOP 3 * FROM temp_fileno_sequence ORDER BY id DESC");
    $seqRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($seqRows as $row) {
        foreach ($row as $k => $v) {
            echo "  $k: " . ($v ?? 'NULL') . "\n";
        }
        echo "---\n";
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
