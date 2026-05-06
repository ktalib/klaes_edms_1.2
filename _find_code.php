<?php
$lines = file('transcript.jsonl');
foreach ($lines as $i => $line) {
    if (strpos($line, 'public function index(Request $request): View') !== false) {
        $json = json_decode($line, true);
        if ($json && isset($json['text'])) {
            echo "Line $i has text:\n=================\n";
            echo substr($json['text'], 0, 1500) . "\n=================\n";
        }
    }
}
