<?php
$content = file_get_contents('found_full.txt');
preg_match_all('/\"\*\*\* Begin Patch.*?\"/s', $content, $m);
foreach($m[0] as $i => $match) {
    file_put_contents("my_patch_{$i}.txt", stripslashes(str_replace('\n', "\n", substr($match, 1, -1))));
}
