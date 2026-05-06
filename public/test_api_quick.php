<?php
// Quick test script to check API endpoints
echo "<h1>Grouping API Quick Test</h1>";

$endpoints = [
    '/api/grouping-analytics/stats',
    '/api/grouping-analytics/debug',
    '/api/grouping-analytics/preview'
];

foreach ($endpoints as $endpoint) {
    echo "<h3>Testing: $endpoint</h3>";
    
    $url = "http://localhost" . $endpoint;
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'method' => 'GET'
        ]
    ]);
    
    $start = microtime(true);
    $response = @file_get_contents($url, false, $context);
    $duration = (microtime(true) - $start) * 1000;
    
    if ($response !== false) {
        echo "<p style='color: green'>✅ Success in " . round($duration, 2) . "ms</p>";
        
        $data = json_decode($response, true);
        if ($data) {
            echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
        } else {
            echo "<p>Response: " . htmlspecialchars(substr($response, 0, 200)) . "...</p>";
        }
    } else {
        echo "<p style='color: red'>❌ Failed after " . round($duration, 2) . "ms</p>";
        echo "<p>Error: " . error_get_last()['message'] . "</p>";
    }
    
    echo "<hr>";
}
?>