<?php
$data = file_get_contents('line_1235.txt');
preg_match('/public function index\([^\{\}]+\).*?public function/s', str_replace('\n', "\n", $data), $m);
if ($m) {
    file_put_contents('found_method.txt', $m[0]);
    echo "Found it!\n";
} else {
    echo "Regexp failed, writing out full readable JSON\n";
    $j = json_decode($data, true);
    file_put_contents('found_full.txt', print_r($j, true));
}
