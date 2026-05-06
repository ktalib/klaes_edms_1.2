<?php
$lines = file('transcript.jsonl');
foreach ($lines as $i => $line) {
    if (strpos($line, 'public function index(') !== false && strpos($line, 'OpResettlementApplicationController') !== false) {
        echo "Found in line $i\n";
        file_put_contents("line_$i.txt", $line);
    }
}
