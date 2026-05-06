<?php
/**
 * Recovery script: Reconstruct deleted file_indexings rows from fileNumber table.
 *
 * Background:
 *   On 2026-04-28, 688 rows were accidentally deleted from file_indexings
 *   using: DELETE FROM file_indexings WHERE CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)
 *
 *   When a file is indexed, FileIndexController::updateFileNumberTable() either
 *   updates or inserts a row in [fileNumber] with the tracking_id of the
 *   file_indexings row.  We use that tracking_id to identify orphaned fileNumber
 *   rows (tracking_id exists in fileNumber but NOT in file_indexings) and
 *   reconstruct the missing file_indexings records.
 *
 * Usage:
 *   php _recover_fi_from_filenumber.php            -- dry run (shows what would be inserted)
 *   php _recover_fi_from_filenumber.php --execute  -- actually inserts the rows
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$dryRun = !in_array('--execute', $argv);

echo "=== File Indexing Recovery Script ===" . PHP_EOL;
echo "Mode: " . ($dryRun ? "DRY RUN (pass --execute to insert)" : "EXECUTE") . PHP_EOL;
echo "Target date: 2026-04-28" . PHP_EOL . PHP_EOL;

$pdo = DB::connection('sqlsrv')->getPdo();

// ----------------------------------------------------------------
// Step 1: Find orphaned fileNumber rows
// These are rows in fileNumber that:
//   - Were updated OR created on 2026-04-28
//   - Have a tracking_id (TRK-...)
//   - That tracking_id does NOT exist in file_indexings anymore
// ----------------------------------------------------------------
$sql = "
    SELECT fn.*
    FROM [klas].[dbo].[fileNumber] fn
    WHERE
        (CAST(fn.updated_at AS DATE) = '2026-04-28' OR CAST(fn.created_at AS DATE) = '2026-04-28')
        AND fn.tracking_id IS NOT NULL
        AND fn.tracking_id != ''
        AND fn.tracking_id LIKE 'TRK-%'
        AND fn.tracking_id NOT IN (
            SELECT fi.tracking_id
            FROM [klas].[dbo].[file_indexings] fi
            WHERE fi.tracking_id IS NOT NULL
        )
    ORDER BY fn.updated_at
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($rows) . " orphaned fileNumber rows to recover." . PHP_EOL . PHP_EOL;

if (empty($rows)) {
    echo "Nothing to recover. Exiting." . PHP_EOL;
    exit(0);
}

// ----------------------------------------------------------------
// Step 2: Show preview of first 5 rows
// ----------------------------------------------------------------
echo "=== Preview (first 5 rows) ===" . PHP_EOL;
foreach (array_slice($rows, 0, 5) as $i => $row) {
    $fileNo = $row['mlsfNo'] ?: ($row['kangisFileNo'] ?: ($row['NewKANGISFileNo'] ?: ($row['st_file_no'] ?: null)));
    echo ($i + 1) . ". tracking_id={$row['tracking_id']} | file_number={$fileNo} | title={$row['FileName']} | updated_at={$row['updated_at']}" . PHP_EOL;
}
echo PHP_EOL;

if ($dryRun) {
    echo "DRY RUN complete. No rows inserted." . PHP_EOL;
    echo "Run with --execute to perform the actual recovery." . PHP_EOL;
    exit(0);
}

// ----------------------------------------------------------------
// Step 3: Reconstruct and insert file_indexings rows
// ----------------------------------------------------------------
$inserted = 0;
$skipped  = 0;
$errors   = [];

// Check next available ID
$maxIdRow = $pdo->query("SELECT MAX(id) AS max_id FROM [klas].[dbo].[file_indexings]")->fetch(PDO::FETCH_ASSOC);
$nextId   = (int)($maxIdRow['max_id'] ?? 0) + 1;

echo "Starting insert from id=$nextId ..." . PHP_EOL;

foreach ($rows as $row) {
    // Resolve the best file_number from fileNumber columns
    $fileNumber = null;
    if (!empty($row['mlsfNo']))        $fileNumber = $row['mlsfNo'];
    elseif (!empty($row['NewKANGISFileNo'])) $fileNumber = $row['NewKANGISFileNo'];
    elseif (!empty($row['kangisFileNo']))    $fileNumber = $row['kangisFileNo'];
    elseif (!empty($row['st_file_no']))     $fileNumber = $row['st_file_no'];

    if (empty($fileNumber)) {
        $errors[] = "Row id={$row['id']}: could not determine file_number, skipped.";
        $skipped++;
        continue;
    }

    // Determine file type (registry) from file number pattern
    $generalRegistry = 'KANGIS Registry'; // default
    if (preg_match('/^(RES|COM|AGR|INS|EDU|SPO|MIX)-\d{4}-\d+/i', $fileNumber)) {
        $generalRegistry = 'KANGIS Registry';
    } elseif (preg_match('/^MLSF\//i', $fileNumber)) {
        $generalRegistry = 'MLSF Registry';
    } elseif (preg_match('/^ST-/i', $fileNumber)) {
        $generalRegistry = 'ST Registry';
    }

    // Determine kangis_file_type
    $kangisFileType = null;
    if (!empty($row['mlsfNo']))             $kangisFileType = 'MLS';
    elseif (!empty($row['NewKANGISFileNo'])) $kangisFileType = 'new_kangis';
    elseif (!empty($row['kangisFileNo']))    $kangisFileType = 'KANGIS';

    // Use April 28 as the created_at (the day records were created and deleted)
    $createdAt = '2026-04-28 08:00:00';

    $insertSql = "
        INSERT INTO [klas].[dbo].[file_indexings]
        (
            file_number, file_title, tracking_id, location, lga, district, tp_no,
            source, general_registry, registry, physical_registry,
            related_fileno, has_temp_file, temp_file_no,
            kangis_file_type, mls_file_no, kangis_file_no, new_kangis_file_no,
            current_holder, original_holder,
            workflow_status,
            created_by, updated_by,
            created_at, updated_at,
            is_deleted, holders_backfilled,
            has_cofo, is_merged, has_transaction, is_problematic, is_co_owned_plot,
            batch_generated
        )
        VALUES (
            :file_number, :file_title, :tracking_id, :location, :lga, :district, :tp_no,
            :source, :general_registry, :general_registry2, :physical_registry,
            :related_fileno, :has_temp_file, :temp_file_no,
            :kangis_file_type, :mls_file_no, :kangis_file_no, :new_kangis_file_no,
            :current_holder, :original_holder,
            'indexed',
            :created_by, :updated_by,
            :created_at, :updated_at,
            0, 0,
            0, 0, 0, 0, 0,
            0
        )
    ";

    try {
        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([
            ':file_number'       => $fileNumber,
            ':file_title'        => $row['FileName'] ?: $fileNumber,
            ':tracking_id'       => $row['tracking_id'],
            ':location'          => $row['location'] ?: null,
            ':lga'               => $row['lga'] ?: null,
            ':district'          => $row['district'] ?: null,
            ':tp_no'             => $row['tp_no'] ?: null,
            ':source'            => 'recovered_from_filenumber',
            ':general_registry'  => $generalRegistry,
            ':general_registry2' => $generalRegistry,
            ':physical_registry' => $generalRegistry,
            ':related_fileno'    => $row['related_fileno'] ?: null,
            ':has_temp_file'     => (int)($row['has_temp_file'] ?? 0),
            ':temp_file_no'      => $row['temp_file_no'] ?: ($row['temp_fileno'] ?: null),
            ':kangis_file_type'  => $kangisFileType,
            ':mls_file_no'       => $row['mlsfNo'] ?: null,
            ':kangis_file_no'    => $row['kangisFileNo'] ?: null,
            ':new_kangis_file_no'=> $row['NewKANGISFileNo'] ?: null,
            ':current_holder'    => $row['FileName'] ?: $fileNumber,
            ':original_holder'   => json_encode($row['FileName'] ?: $fileNumber),
            ':created_by'        => $row['created_by'] ?: 'Recovery Script',
            ':updated_by'        => $row['updated_by'] ?: 'Recovery Script',
            ':created_at'        => $createdAt,
            ':updated_at'        => $row['updated_at'] ?: $createdAt,
        ]);
        $inserted++;

        if ($inserted % 50 === 0) {
            echo "Inserted $inserted rows..." . PHP_EOL;
        }
    } catch (\Exception $e) {
        $errors[] = "Row tracking_id={$row['tracking_id']} file_number={$fileNumber}: " . $e->getMessage();
        $skipped++;
    }
}

echo PHP_EOL . "=== Recovery Complete ===" . PHP_EOL;
echo "Inserted : $inserted" . PHP_EOL;
echo "Skipped  : $skipped" . PHP_EOL;
echo "Errors   : " . count($errors) . PHP_EOL;

if (!empty($errors)) {
    echo PHP_EOL . "=== Errors ===" . PHP_EOL;
    foreach ($errors as $e) echo "  - $e" . PHP_EOL;
}

echo PHP_EOL . "NOTE: The following fields were NOT recoverable and are set to defaults:" . PHP_EOL;
echo "  plot_number, has_cofo, is_merged, has_transaction, is_problematic," . PHP_EOL;
echo "  is_co_owned_plot, batch_no, serial_no, shelf_location, batch_id, shelf_label_id," . PHP_EOL;
echo "  land_use_type, customer_type, file_type, phone, nin, tin, rc_no, dob" . PHP_EOL;
echo "  -> These will need to be manually re-entered or recovered from paper records." . PHP_EOL;
