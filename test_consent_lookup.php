<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$fileno = 'IND-2026-80';
$consentApp = \DB::connection('sqlsrv')->table('consent_applications')
    ->where('file_number', $fileno)
    ->orderBy('created_at', 'desc')
    ->first();

echo "File: $fileno\n";
echo "Found: " . ($consentApp ? 'YES' : 'NO') . "\n";
if ($consentApp) {
    echo "applicant_name: " . ($consentApp->applicant_name ?? 'NULL') . "\n";
    echo "party_name: " . ($consentApp->party_name ?? 'NULL') . "\n";
    echo "consent_type: " . ($consentApp->consent_type ?? 'NULL') . "\n";
}
