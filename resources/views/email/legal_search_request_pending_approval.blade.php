@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Online Legal Search Request Awaiting Your Approval</h2>

    <p>Dear {{ $approver->name ?: $approver->username }},</p>

    <p>A member of the public has submitted an Online Legal Search request. It will not be released until it is approved.</p>

    <h3>Request Details</h3>
    <table class="details">
        <tr>
            <td>Request Number:</td>
            <td><strong>{{ $searchRequest->request_no }}</strong></td>
        </tr>
        <tr>
            <td>File Number:</td>
            <td><strong>{{ $searchRequest->file_number ?: '—' }}</strong></td>
        </tr>
        <tr>
            <td>Purpose of Search:</td>
            <td><strong>{{ $searchRequest->purpose ?: '—' }}</strong></td>
        </tr>
        <tr>
            <td>Requester:</td>
            <td>{{ $searchRequest->requester_email }}</td>
        </tr>
        <tr>
            <td>Payment Reference:</td>
            <td>{{ $searchRequest->tracking_id ?: $searchRequest->reference }}</td>
        </tr>
        <tr>
            <td>Submitted:</td>
            <td>{{ optional($searchRequest->submitted_at)->format('F j, Y \a\t g:i A') }}</td>
        </tr>
    </table>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $queueUrl }}" class="btn" style="display: inline-block; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: 600;">Review Request in KLAES</a>
    </div>

    <div class="info-box">
        On approval, the Legal Search report is generated and emailed to the requester as a PDF automatically. If you decline the request, the requester is told why.
    </div>
@endsection
