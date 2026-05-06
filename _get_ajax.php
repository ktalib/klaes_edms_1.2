<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController;

$req = Request::create('?source=lands-one-stop-shop&type=change-of-name&page=1&limit=5', 'GET');
$req->server->set('HTTP_X_REQUESTED_WITH', 'XMLHttpRequest');

$ctrl = app(OpResettlementApplicationController::class);
$resp = $ctrl->index($req);

$data = json_decode($resp->getContent(), true);
if (isset($data['data'][0])) {
    print_r($data['data'][0]);
} else {
    echo "No rows returned\n";
    print_r($data);
}
