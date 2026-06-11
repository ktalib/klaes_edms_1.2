@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">New PHS Onboarding Request Received</h2>
    
    <p>A new organization has submitted an onboarding request for the Property History Search portal. Please review the details below:</p>
    
    <h3 style="margin-top: 30px;">Organization Details</h3>
    <table class="details">
        <tr>
            <td>Organization Name:</td>
            <td><strong>{{ $request->organization_name }}</strong></td>
        </tr>
        <tr>
            <td>Organization Type:</td>
            <td>{{ str_replace('_', ' ', $request->organization_type) }}</td>
        </tr>
        <tr>
            <td>Phone:</td>
            <td>{{ $request->phone ?? 'Not provided' }}</td>
        </tr>
        <tr>
            <td>Address:</td>
            <td>{{ $request->address ?? 'Not provided' }}</td>
        </tr>
    </table>
    
    <h3>Contact Information</h3>
    <table class="details">
        <tr>
            <td>Contact Name:</td>
            <td><strong>{{ $request->contact_name }}</strong></td>
        </tr>
        <tr>
            <td>Email:</td>
            <td><a href="mailto:{{ $request->contact_email }}">{{ $request->contact_email }}</a></td>
        </tr>
        <tr>
            <td>Job Title:</td>
            <td>{{ $request->job_title ?? 'Not provided' }}</td>
        </tr>
        <tr>
            <td>Department:</td>
            <td>{{ $request->department ?? 'Not provided' }}</td>
        </tr>
    </table>
    
    <h3>Additional Information</h3>
    <table class="details">
        <tr>
            <td>Preferred Token Package:</td>
            <td>{{ $request->initial_token_package ?? 'No preference' }}</td>
        </tr>
        <tr>
            <td>Additional Notes:</td>
            <td>{{ $request->additional_notes ?? 'None provided' }}</td>
        </tr>
        <tr>
            <td>Submission Date:</td>
            <td>{{ $request->created_at ? $request->created_at->format('F j, Y \a\t g:i A') : now()->format('F j, Y \a\t g:i A') }}</td>
        </tr>
    </table>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $adminDashboardUrl ?? 'https://app.klaes.ng/admin' }}" class="btn btn-primary" style="display: inline-block; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600;">Review in Dashboard</a>
    </div>
    
    <hr class="divider">
    
    <p style="color: #6b7280; font-size: 12px;">
        Log in to the admin dashboard to approve or reject this request.
    </p>
@endsection
