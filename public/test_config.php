<?php
// Simple script to check effective PHP upload settings
header('Content-Type: text/plain');

echo "Effective PHP Upload Settings:\n\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";

$uploadMax = return_bytes(ini_get('upload_max_filesize'));
$postMax = return_bytes(ini_get('post_max_size'));
$effectiveMax = min($uploadMax, $postMax);

echo "\nResult: Your effective maximum upload size is " . format_bytes($effectiveMax) . ".\n";

if ($effectiveMax >= 536870912) { // 512MB
    echo "SUCCESS: The .htaccess settings are working correctly.\n";
} else {
    echo "WARNING: The limits are lower than 512MB. You likely need to update your php.ini file directly.\n";
}

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

function format_bytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
