<?php
// Only fix the blade file now - the JS was already fixed.
// Box drawing double horizontal â•[control] = U+00E2 + U+2022(bullet) + U+0090(control)
// Bytes: C3 A2  E2 80 A2  C2 90
$boxDoubleH = "\xC3\xA2\xE2\x80\xA2\xC2\x90";

$file = 'C:/wamp64/www/klaes/resources/views/change_of_purpose/index.blade.php';
$c = file_get_contents($file);
$orig_len = strlen($c);

$count = substr_count($c, $boxDoubleH);
echo "Occurrences of box-double-H in blade: $count\n";

$c = str_replace($boxDoubleH, '=', $c);

// Also check for any remaining non-ASCII (U+00E2 family)
$remaining = 0;
for ($i = 0; $i < strlen($c) - 1; $i++) {
    if (ord($c[$i]) === 0xC3 && ord($c[$i+1]) === 0xA2) $remaining++;
}
echo "Remaining U+00E2 (â) occurrences: $remaining\n";

$new_len = strlen($c);
file_put_contents($file, $c);
echo "Fixed blade: ($orig_len -> $new_len bytes)\n";

