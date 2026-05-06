<?php
// Direct test of API endpoints
echo "<h1>Direct API Test</h1>";

// Test URL
$baseUrl = 'http://localhost:8000';
$endpoints = [
    '/api/grouping-analytics/stats',
    '/api/grouping-analytics/debug', 
    '/api/grouping-analytics/preview'
];

foreach ($endpoints as $endpoint) {
    echo "<h3>Testing: {$endpoint}</h3>";
    
    $fullUrl = $baseUrl . $endpoint;
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'header' => "Accept: application/json\r\n"
        ]
    ]);
    
    $start = microtime(true);
    $response = @file_get_contents($fullUrl, false, $context);
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    if ($response !== false) {
        echo "<p style='color: green'>✅ Success in {$duration}ms</p>";
        
        // Try to decode JSON
        $data = json_decode($response, true);
        if ($data) {
            echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: auto;'>";
            echo json_encode($data, JSON_PRETTY_PRINT);
            echo "</pre>";
        } else {
            echo "<p><strong>Raw Response:</strong></p>";
            echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: auto;'>";
            echo htmlspecialchars(substr($response, 0, 500));
            if (strlen($response) > 500) echo "\n... (truncated)";
            echo "</pre>";
        }
    } else {
        echo "<p style='color: red'>❌ Failed after {$duration}ms</p>";
        
        $error = error_get_last();
        if ($error) {
            echo "<p>Error: " . htmlspecialchars($error['message']) . "</p>";
        }
        
        // Check if $http_response_header is available
        if (isset($http_response_header)) {
            echo "<p><strong>HTTP Headers:</strong></p>";
            echo "<pre>" . implode("\n", $http_response_header) . "</pre>";
        }
    }
    
    echo "<hr>";
    
    // Small delay between requests
    usleep(100000); // 0.1 second
}

// Test if routes are accessible at all
echo "<h3>Route existence test</h3>";
echo "<p>Testing if Laravel app responds to basic requests...</p>";

$testUrl = $baseUrl . '/';
$response = @file_get_contents($testUrl, false, stream_context_create(['http' => ['timeout' => 10]]));

if ($response !== false) {
    echo "<p style='color: green'>✅ Laravel app is responding</p>";
} else {
    echo "<p style='color: red'>❌ Laravel app is not responding</p>";
}
?>