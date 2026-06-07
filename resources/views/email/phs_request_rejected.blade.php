<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHS Onboarding Request Status</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 24px auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .content { padding: 24px; }
        .button { display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 8px; margin-top: 16px; }
        .section-title { font-size: 18px; font-weight: 700; margin-top: 24px; }
        .blockquote { margin: 12px 0; padding: 14px 18px; background: #f3f4f6; border-left: 4px solid #ef4444; }
        .footer { margin-top: 32px; font-size: 14px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <h1>Your PHS Onboarding Request Status</h1>
            <p>Dear {{ $request->contact_name }},</p>
            <p>Thank you for submitting your onboarding request for the Property History Search (PHS) portal. We appreciate your interest.</p>

            <p class="section-title">Request Status</p>
            <p>Unfortunately, your request has been <strong>rejected</strong>. Please see the reason below:</p>
            <div class="blockquote">{{ $request->rejection_reason }}</div>

            <p class="section-title">Next Steps</p>
            <p>If you believe this decision was made in error or would like to discuss your request further, please contact our support team.</p>
            <p>You may also submit a new request with updated information:</p>
            <a class="button" href="{{ $resubmitUrl }}">Submit a New Request</a>

            <p class="section-title">Request Details</p>
            <p><strong>Organization:</strong> {{ $request->organization_name }}<br>
            <strong>Request Submitted:</strong> {{ $request->created_at->format('F j, Y') }}</p>

            <p class="footer">If you have any questions, please reach out to our support team.</p>
        </div>
    </div>
</body>
</html>
