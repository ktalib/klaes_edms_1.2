<?php
$c = file_get_contents(__DIR__ . '/resources/views/pagetyping/index.blade.php');
preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $c, $m);
$maxLen = 0; $maxIdx = 0;
foreach ($m[1] as $i => $block) { if (strlen($block) > $maxLen) { $maxLen = strlen($block); $maxIdx = $i; } }
$js = $m[1][$maxIdx];
$js = preg_replace('/"\{\{[^}]*\}\}"/', '"BLADE"', $js);
$js = preg_replace("/'\{\{[^}]*\}\}'/", "'BLADE'", $js);
$js = preg_replace('/\{\{[^}]*\}\}/', '"BLADE"', $js);
$js = preg_replace('/@php.*?@endphp/s', '', $js);
$js = preg_replace('/@\w+/', '', $js);
file_put_contents(__DIR__ . '/_temp_js.js', $js);
echo "Extracted JS: " . strlen($js) . " chars\n";
