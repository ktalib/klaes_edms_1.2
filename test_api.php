<?php

try {
    $request = request();
    $controller = app(\App\Http\Controllers\Api\FileTrackerDashboardApiController::class);
    $response = $controller->commissionerOverview($request);
    
    echo json_encode($response->getData(true));
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
