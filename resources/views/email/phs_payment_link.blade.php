@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Your PHS Portal Onboarding Request Has Been Approved!</h2>

    <div class="success-box">
        <strong>Great news!</strong> Your onboarding request for the PHS Portal has been reviewed and approved. Please activate your account to proceed.
    </div>

    <h3>Organization Information</h3>
    <table class="details">
        <tr>
            <td>Organization:</td>
            <td><strong>{{ $request->organization_name }}</strong></td>
        </tr>
        <tr>
            <td>Contact:</td>
            <td>{{ $request->contact_name }}</td>
        </tr>
        <tr>
            <td>Email:</td>
            <td>{{ $request->contact_email }}</td>
        </tr>
    </table>

    <h3>Activate Your Account</h3>
    <p>Click the button below to select your subscription package and activate your account securely via Paystack:</p>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $paymentUrl }}"
           style="display: inline-block; background: linear-gradient(135deg, #166534 0%, #14532d 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 15px;">
            Activate Your Account
        </a>
    </div>

    <div class="info-box">
        <strong>What happens next?</strong>
        <ul class="list" style="margin-top: 8px;">
            <li>Select your preferred subscription package</li>
            <li>Complete payment securely via Paystack</li>
            <li>Download, sign, and upload the Service Level Agreement (SLA)</li>
            <li>Legal team reviews your SLA</li>
            <li>You will receive a registration link to activate your account</li>
        </ul>
    </div>

    <div class="warning-box">
        <strong>Important:</strong> This payment link is unique to your organization. Please keep it confidential and complete payment at your earliest convenience.
    </div>

    <hr class="divider">

    <p style="color: #6b7280; font-size: 12px;">
        If you have any questions, contact the ministry's finance office.<br>
        Kano State Ministry of Land &amp; Physical Planning — PHS Portal
    </p>
@endsection
