<?php
/**
 * Phase 8 — sanity check the OSS OP Resettlement index query compiles & runs.
 * Just count rows; we only care that the new tot_agg join + filters don't
 * blow up.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController;

// pick any user so auth helpers don't blow up if referenced
$user = \DB::connection('sqlsrv')->table('users')->orderBy('id')->first();
if ($user) { Auth::loginUsingId($user->id); }

$controller = app(OpResettlementApplicationController::class);
$req = Request::create('/lands-one-stop-shop/applications/op-resettlement', 'GET', [
    'source' => 'lands-one-stop-shop',
    'type' => 'change-of-name',
    'page' => 1,
    'limit' => 5,
]);

try {
    $resp = $controller->index($req);
    $data = is_object($resp) && method_exists($resp, 'getData') ? $resp->getData(true) : null;
    if (!$data) {
        $data = json_decode($resp->getContent(), true);
    }
    echo "HTTP status: " . (method_exists($resp, 'getStatusCode') ? $resp->getStatusCode() : 'n/a') . "\n";
    echo "total: " . ($data['pagination']['total'] ?? $data['total'] ?? 'n/a') . "\n";
    echo "rows returned: " . (isset($data['data']) ? count($data['data']) : 'n/a') . "\n";
    if (!empty($data['data'][0])) {
        $first = $data['data'][0];
        echo "first row keys: " . implode(', ', array_slice(array_keys((array) $first), 0, 12)) . " ...\n";
    }
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getFile() . ':' . $e->getLine() . "\n");
    exit(1);
}
