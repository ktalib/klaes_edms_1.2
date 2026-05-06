<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$kernel->handle($request);
try {
    $c = app(\App\Http\Controllers\Api\FileTrackerApiController::class);
    $r = $c->dashboard();
    echo $r->getContent();
} catch(\Exception $e) {
    echo 'ERROR: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine();
}
