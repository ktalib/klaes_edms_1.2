<?php
use App\Models\MotherApplication;
use App\Http\Controllers\CofoController;
use Illuminate\Http\Request;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$primary = \DB::connection('sqlsrv')->table('mother_applications')->first();
if (!$primary) {
    echo "No primary application found.";
    exit;
}

$controller = app(App\Http\Controllers\CofoController::class);
try {
    $result = $controller->puaUnitsModal(new Request(), $primary->id);
    echo "Result type: " . get_class($result) . "\n";
    if (method_exists($result, 'render')) {
        echo "HTML Rendered successfully.\n";
    } else {
        echo "No render method.\n";
    }
} catch (\Exception $e) {
    echo "Error calling puaUnitsModal: " . $e->getMessage() . "\n";
}
