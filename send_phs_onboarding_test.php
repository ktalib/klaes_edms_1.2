<?php

// Lightweight script to send a test PHS onboarding email.
// Run: php send_phs_onboarding_test.php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Mail;
use App\Mail\PhsOnboardingRequestSubmitted;
use App\Models\Phs\PhsOnboardingRequest;

error_reporting(E_ALL);
ini_set('display_errors', '1');

$request = new PhsOnboardingRequest([
    'organization_name' => 'Test Organization (PHS email)',
    'organization_type' => 'corporate',
    'contact_name' => 'Test User',
    'contact_email' => 'iorkuakator@gmail.com',
    'phone' => '0000000000',
    'address' => 'Test address',
    'department' => 'IT',
    'job_title' => 'CTO',
    'initial_token_package' => 'Starter',
    'additional_notes' => 'This is a test of the PHS onboarding email template.',
    'status' => PhsOnboardingRequest::STATUS_PENDING,
]);

try {
    // Send a plain-text fallback email (avoids markdown component/hint-path issues).
    $body = "New PHS Onboarding Request\n\n" .
        "Organization: " . ($request->organization_name ?? 'N/A') . "\n" .
        "Type: " . ($request->organization_type ?? 'N/A') . "\n" .
        "Contact: " . ($request->contact_name ?? 'N/A') . " <" . ($request->contact_email ?? 'N/A') . ">\n" .
        "Phone: " . ($request->phone ?? 'N/A') . "\n\n" .
        "Address:\n" . ($request->address ?? 'N/A') . "\n\n" .
        "Notes:\n" . ($request->additional_notes ?? 'None') . "\n\n" .
        "Preferred Package: " . ($request->initial_token_package ?? 'No preference') . "\n\n" .
        "View admin dashboard: " . url('/admin/phs/requests') . "\n";

    $adminEmail = config('mail.admin_email') ?? 'iorkuakator@gmail.com';
    Mail::raw($body, function ($message) use ($adminEmail) {
        $message->to($adminEmail)->subject('Test PHS Onboarding Request');
    });

    echo "Mail send attempted to: {$adminEmail}\n";

    echo "Mail send attempted\n";
} catch (Throwable $e) {
    echo "Error sending mail: ", $e->getMessage(), "\n";
}
