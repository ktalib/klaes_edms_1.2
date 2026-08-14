@extends('email.layouts.master')

@section('content')
    <h2 style="color: #1e3a5f; font-size: 22px; margin-bottom: 20px;">Your Legal Search Report Is Ready</h2>

    <div class="success-box">
        <strong>Approved.</strong> Your Online Legal Search request has been approved and the report is attached to this email as a PDF.
    </div>

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
            <td>Approved By:</td>
            <td>{{ $searchRequest->reviewer_name ?: 'The Ministry' }}@if($searchRequest->reviewer_rank) — {{ $searchRequest->reviewer_rank }}@endif</td>
        </tr>
        <tr>
            <td>Approved On:</td>
            <td>{{ optional($searchRequest->reviewed_at)->format('F j, Y \a\t g:i A') }}</td>
        </tr>
        <tr>
            <td>Payment Reference:</td>
            <td>{{ $searchRequest->tracking_id ?: $searchRequest->reference }}</td>
        </tr>
    </table>

    <div class="info-box">
        <strong>📎 Attached:</strong> your <strong>Legal Search Report</strong> (PDF). Please keep it for your records — it is issued to
        {{ $searchRequest->requester_email }} and quotes request number {{ $searchRequest->request_no }}.
    </div>

    <p style="font-size: 13px; color: #64748b;">
        N.B: This search report is deduced from the records available on the file and does not represent any document in the possession of any body.
    </p>
@endsection
