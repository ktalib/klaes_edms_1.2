@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Your Legal Search Request Has Been Received</h2>

    <p>Thank you. Your Online Legal Search request has been submitted to the Ministry and is now awaiting review by the Director / Deputy Director.</p>

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
            <td>Payment Reference:</td>
            <td>{{ $searchRequest->tracking_id ?: $searchRequest->reference }}</td>
        </tr>
        <tr>
            <td>Submitted:</td>
            <td>{{ optional($searchRequest->submitted_at)->format('F j, Y \a\t g:i A') }}</td>
        </tr>
    </table>

    <div class="info-box">
        <strong>What happens next:</strong> once a Director or Deputy Director approves your request, the full Legal Search report will be emailed to
        <strong>{{ $searchRequest->requester_email }}</strong> as a PDF attachment. No further action is needed from you.
    </div>

    <p style="font-size: 13px; color: #64748b;">
        Please quote request number <strong>{{ $searchRequest->request_no }}</strong> in any correspondence about this search.
    </p>
@endsection
