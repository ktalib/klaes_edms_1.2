<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color: #111827; }
        .card { max-width: 680px; margin: 24px auto; padding: 24px; border: 1px solid #e5e7eb; border-radius: 8px; background: #ffffff; }
        .h1 { font-size: 20px; font-weight: 600; margin-bottom: 8px; }
        .muted { color: #6b7280; }
        .btn { display: inline-block; background: #0ea5e9; color: white; padding: 10px 16px; border-radius: 6px; text-decoration: none; }
        .meta { margin-top: 18px; color: #374151; font-size: 14px; }
        .section { margin-top: 14px; }
        .list dt { font-weight: 600; }
        .list dd { margin: 0 0 8px 0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="h1">New PHS Onboarding Request</div>
        <p class="muted">A new organization has submitted an onboarding request for the Property History Search portal.</p>

        <div class="section">
            <h3>Organization Details</h3>
            <dl class="list">
                <dt>Name</dt>
                <dd>{{ $request->organization_name }}</dd>
                <dt>Type</dt>
                <dd>{{ str_replace('_', ' ', $request->organization_type) }}</dd>
                <dt>Phone</dt>
                <dd>{{ $request->phone ?? 'Not provided' }}</dd>
                <dt>Address</dt>
                <dd>{{ $request->address ?? 'Not provided' }}</dd>
            </dl>
        </div>

        <div class="section">
            <h3>Contact Information</h3>
            <dl class="list">
                <dt>Name</dt>
                <dd>{{ $request->contact_name }}</dd>
                <dt>Email</dt>
                <dd>{{ $request->contact_email }}</dd>
                <dt>Job Title</dt>
                <dd>{{ $request->job_title ?? 'Not provided' }}</dd>
                <dt>Department</dt>
                <dd>{{ $request->department ?? 'Not provided' }}</dd>
            </dl>
        </div>

        <div class="section">
            <h3>Additional Notes</h3>
            <p>{{ $request->additional_notes ?? 'None provided.' }}</p>
        </div>

        <div class="section">
            <h3>Preferred Token Package</h3>
            <p>{{ $request->initial_token_package ?? 'No preference' }}</p>
        </div>

        <div class="section meta">
            <p>Review this request in the admin dashboard and approve or reject it.</p>
            <p><a class="btn" href="{{ $adminDashboardUrl }}">View in Dashboard</a></p>
            <p style="margin-top:12px; color:#6b7280; font-size:13px;">Request Submitted: {{ $request->created_at ? $request->created_at->format('F j, Y \a\t g:i A') : now()->format('F j, Y \a\t g:i A') }}</p>
        </div>
    </div>
</body>
</html>
