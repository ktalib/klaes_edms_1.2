<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController;

$req = Request::create('/?source=lands-one-stop-shop&type=change-of-name&page=1&limit=5', 'GET');
$req->headers->add(['X-Requested-With' => 'XMLHttpRequest']);

$ctrl = app(OpResettlementApplicationController::class);
$resp = $ctrl->index($req);

if (method_exists($resp, 'getContent')) {
    $data = json_decode($resp->getContent(), true);
    if (!empty($data['data'])) {
        print_r($data['data'][0]);
    } else {
        echo "No data\n";
    }
} else {
    echo "Got view\n";
    $viewData = $resp->getData();
    if (isset($viewData['records'][0])) {
        print_r($viewData['records'][0]);
    } else {
        echo "No records in view data\n";
        var_dump(array_keys($viewData));
    }
}
