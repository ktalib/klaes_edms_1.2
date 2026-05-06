try {
    $r = \App\Models\SurveyReportRequest::find(8);
    if (!$r) {
        echo "NOT_FOUND".PHP_EOL;
    } else {
        $fields = ["lands_section_signature","director_cadastral_signature","director_signature"];
        foreach ($fields as $f) {
            $v = $r->$f;
            $hasVal = (($v !== null) and ($v !== ""));
            echo $f.": ".($hasVal ? $v : "NULL").PHP_EOL;
            if (!$hasVal) { continue; }
            $p = parse_url($v, PHP_URL_PATH);
            if (($p === null) or ($p === false) or ($p === "")) { $p = $v; }
            $p = ltrim($p, "/\\");
            if (strpos($p, "storage/") === 0) { $p = substr($p, 8); }
            if (strpos($p, "public/") === 0) { $p = substr($p, 7); }
            $full = storage_path("app/public/".$p);
            $exists = file_exists($full) ? "YES" : "NO";
            $dim = "-";
            if ($exists === "YES") {
                $gi = @getimagesize($full);
                if ($gi) { $dim = $gi[0]."x".$gi[1]; }
            }
            echo "  -> ".$exists." | ".$dim." | ".$full.PHP_EOL;
        }
    }
} catch (\Throwable $e) {
    echo "ERROR: ".$e->getMessage().PHP_EOL;
}
