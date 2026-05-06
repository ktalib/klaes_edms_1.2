<?php
$lines = file('modified.txt');
$out = [];
foreach ($lines as $line) {
    if (preg_match('/^M\s+(.*)/', $line, $m)) {
        $file = trim($m[1]);
        if (strpos($file, 'OpResettlementApplicationController') !== false ||
            strpos($file, 'MlsFileNoController') !== false ||
            strpos($file, 'ApplicationController') !== false ||
            strpos($file, 'PraRecordService') !== false ||
            strpos($file, 'InstrumentCaptureService') !== false ||
            strpos($file, 'InstrumentRegistrationService') !== false ||
            strpos($file, 'PropertyIdAllocationService') !== false ||
            strpos($file, 'match_op.php') !== false ||
            strpos($file, 'applications.blade.php') !== false ||
             strpos($file, 'migration') !== false
        ) {
            $out[] = $file;
        }
    }
}
$out = array_unique($out);
sort($out);
foreach ($out as $file) {
    if (strpos($file, 'tests') === false) {
        echo "- " . $file . "\n";
    }
}
