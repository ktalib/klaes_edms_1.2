<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHS Onboarding Approved</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 24px auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .content { padding: 24px; }
        .button { display: inline-block; background: #10b981; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 8px; margin-top: 16px; }
        .section-title { font-size: 18px; font-weight: 700; margin-top: 24px; }
        .list { margin: 12px 0 0; padding-left: 18px; }
        .footer { margin-top: 32px; font-size: 14px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <h1>Your PHS Onboarding Request Has Been Approved</h1>
            <p>Welcome! Your onboarding request for the Property History Search portal has been reviewed and approved.</p>

            <p class="section-title">Organization Information</p>
            <ul class="list">
                <li><strong>Name:</strong> {{ $request->organization_name }}</li>
                <li><strong>Type:</strong> {{ str_replace('_', ' ', $request->organization_type) }}</li>
                <li><strong>Contact:</strong> {{ $request->contact_name }}</li>
            </ul>

            <p class="section-title">Complete Your Registration</p>
            <p>Click the button below to complete your registration and activate your account.</p>
            <a class="button" href="{{ $registrationUrl }}">Complete Registration</a>

            <p>You will be asked to create a password to secure your account.</p>

            <p class="section-title">Important Notes</p>
            <ul class="list">
                <li><strong>Link Expires:</strong> {{ $expiresAt->format('F j, Y \a\t g:i A') }}</li>
                <li>If the link expires, please submit a new request.</li>
                <li>Once registered, you can purchase search tokens to begin searching property records.</li>
            </ul>

            <p class="section-title">Need Help?</p>
            <p>If you have any questions about the PHS portal or need assistance, please contact our support team.</p>

            <p class="footer">Thank you for choosing the Property History Search portal.</p>
        </div>
    </div>
</body>
</html>
