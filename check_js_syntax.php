<?php
$content = file_get_contents($argv[1]);
// Extract content inside <script> tags
if (preg_match_all('/<script.*?>\s*(.*?)\s*<\/script>/is', $content, $matches)) {
    $jsContent = implode("\n", $matches[1]);

    // Strip Blade directives (simplified)
    $jsContent = preg_replace('/@json\(.*?\)/is', '{}', $jsContent);
    $jsContent = preg_replace('/@php.*?@endphp/is', '', $jsContent);
    $jsContent = preg_replace('/{{.*?}}/is', '""', $jsContent);
    $jsContent = preg_replace('/@if\(.*?\)/is', '', $jsContent);
    $jsContent = preg_replace('/@endif/is', '', $jsContent);
    $jsContent = preg_replace('/@else/is', '', $jsContent);
    $jsContent = preg_replace('/@auth/is', '', $jsContent);
    $jsContent = preg_replace('/@endauth/is', '', $jsContent);

    file_put_contents('temp_check.js', $jsContent);
    exec('node --check temp_check.js 2>&1', $output, $return_var);
    echo implode("\n", $output);
} else {
    echo "No <script> tags found\n";
}
if ($return_var !== 0) {
    exit(1);
}
