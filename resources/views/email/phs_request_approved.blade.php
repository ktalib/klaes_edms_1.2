@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">🎉 Your PHS Onboarding Request Has Been Approved!</h2>
    
    <div class="success-box">
        <strong>Congratulations!</strong> Your onboarding request for the Property History Search portal has been reviewed and approved.
    </div>
    
    <h3>Organization Information</h3>
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
            <td>Primary Contact:</td>
            <td>{{ $request->contact_name }}</td>
        </tr>
    </table>
    
    <h3>Complete Your Registration</h3>
    <p>Click the button below to complete your registration and create your account password:</p>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $registrationUrl }}" class="btn" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600;">Complete Registration</a>
    </div>
    
    <div class="info-box">
        <strong>⏱️ Link Expiration:</strong> This link will expire on <strong>{{ $expiresAt->format('F j, Y \a\t g:i A') }}</strong>. If the link expires, please submit a new request.
    </div>
    
    <h3>Important Information</h3>
    <ul class="list">
        <li>You will be asked to create a secure password for your account</li>
        <li>Once registered, you can log in and purchase search tokens</li>
        <li>Begin searching property records immediately after activation</li>
        <li>Your account will have the requested token package: <strong>{{ $request->initial_token_package ?? 'Standard' }}</strong></li>
    </ul>
    
    <h3>Need Help?</h3>
    <p>If you have any questions about the PHS portal or need assistance with registration, please don't hesitate to contact our support team. We're here to help!</p>
    
    <hr class="divider">
    
    <p style="text-align: center; color: #6b7280; font-size: 12px; margin-top: 20px;">
        Thank you for choosing the Property History Search portal.<br>
        <strong>Kano State Ministry of Land & Physical Planning</strong>
    </p>
@endsection
