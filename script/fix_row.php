<?php
$file = 'c:/wamp64/www/klaes/resources/views/instrument_registration/index.blade.php';
$content = file_get_contents($file);
$replacement = file_get_contents('c:/wamp64/www/klaes/resources/views/instrument_registration/fix_row_content.txt');
$pattern = '/const row = `\s*`;\s*tableBody\.insertAdjacentHTML\(\'beforeend\', row\);/s';
$newContent = preg_replace($pattern, $replacement, $content);
if ($newContent !== null && $newContent !== $content) {
    file_put_contents($file, $newContent);
    echo "Fixed successfully.";
} else {
    echo "Pattern not found or no change.";
}
