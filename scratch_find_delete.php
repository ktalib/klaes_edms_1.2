<?php
$content = file_get_contents('resources/views/lands_one_stop_shop/applications.blade.php');
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (stripos($line, 'deleteSelectedMasters') !== false) {
        echo "Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}
