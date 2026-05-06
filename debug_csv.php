<?php
$csvPath = __DIR__ . '/docs/data/COFO_DATA/COFOREADY.csv';
$content = file_get_contents($csvPath);
echo "File size: " . strlen($content) . " bytes\n";

// Check line ending style
if (strpos($content, "\r\n") !== false) {
    echo "Line endings: CRLF (Windows)\n";
} elseif (strpos($content, "\n") !== false) {
    echo "Line endings: LF (Unix)\n";
} elseif (strpos($content, "\r") !== false) {
    echo "Line endings: CR (old Mac)\n";
} else {
    echo "Line endings: NONE (single line?)\n";
}

$lines = file($csvPath, FILE_IGNORE_NEW_LINES);
echo "Total lines from file(): " . count($lines) . "\n";
echo "First 3 lines:\n";
for ($i = 0; $i < min(3, count($lines)); $i++) {
    echo "  [$i] => [" . $lines[$i] . "]\n";
}

// Try with explode on \r
$crLines = explode("\r", $content);
echo "\nTotal lines from CR explode: " . count($crLines) . "\n";
echo "First 3 CR lines:\n";
for ($i = 0; $i < min(3, count($crLines)); $i++) {
    echo "  [$i] => [" . $crLines[$i] . "]\n";
}
