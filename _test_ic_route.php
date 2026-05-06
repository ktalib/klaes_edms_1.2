<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test 1: Does the route resolve ic-4073?
$url = route('lands-one-stop-shop.applications.capture-edit', ['id' => 'ic-4073']);
echo "Route URL: {$url}" . PHP_EOL;

// Test 2: Does the regex match?
echo "Regex test: " . (preg_match('/^([0-9]+|pra-[0-9]+|ic-[0-9]+)$/', 'ic-4073') ? 'MATCH' : 'NO MATCH') . PHP_EOL;

// Test 3: Try resolving something through the router
$router = app('router');
$routes = $router->getRoutes();
$matched = $routes->match(Illuminate\Http\Request::create('/lands-one-stop-shop/applications/op-resettlement/ic-4073/capture-edit', 'GET'));
echo "Matched route: " . ($matched ? $matched->getName() : 'NONE') . PHP_EOL;
echo "Matched action: " . ($matched ? $matched->getActionName() : 'NONE') . PHP_EOL;
echo "Route params: " . json_encode($matched->parameters()) . PHP_EOL;
