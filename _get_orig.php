<?php
$f = file_get_contents('c:\Users\admin\AppData\Roaming\Code\User\workspaceStorage\608a2dc29044cf37e8c7982a3feb860e\GitHub.copilot-chat\transcripts\8979aeec-2e2b-42a5-827b-e61d48fb97a3.jsonl');
$lines = explode("\n", $f);
foreach ($lines as $line) {
    if (strpos($line, 'OpResettlementApplicationController.php') !== false && strpos($line, 'function index') !== false) {
        $json = json_decode($line, true);
        if (isset($json['content'])) {
            echo substr($json['content'], strpos($json['content'], 'public function index'), 1000);
            exit;
        }
    }
}
