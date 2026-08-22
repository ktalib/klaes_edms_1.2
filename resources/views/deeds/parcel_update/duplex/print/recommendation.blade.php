@extends('deeds.parcel_update.duplex.print._layout')

@section('doc-title', 'Memo — Recommendation for Duplex Parcel Update')

@section('doc-body')
    <table class="meta">
        <tr><td>Applicant</td><td>{{ $duplex->applicant_name ?: '—' }}</td></tr>
        <tr><td>Source File Number(s)</td>
            <td class="mono">{{ implode(', ', (array) ($duplex->source_file_nos ?? [])) ?: '—' }}</td></tr>
        <tr><td>KNUPDA Status</td><td>{{ $duplex->knupda_status ?: '—' }}</td></tr>
        <tr><td>KNUPDA Fee</td><td>{{ $duplex->knupda_fee !== null ? number_format((float) $duplex->knupda_fee, 2) : '—' }}</td></tr>
        <tr><td>Land Value</td><td>{{ $duplex->land_value !== null ? number_format((float) $duplex->land_value, 2) : '—' }}</td></tr>
    </table>

    <p class="body-paragraph">
        The application referenced above has been examined and cleared by KNUPDA. It is recommended
        for approval as
        @if ($duplex->isSingleStage())
            a single parcel update,
        @else
            <strong>one instruction carrying {{ $duplex->stageRows->count() }} parcel updates</strong>,
            to be executed in the order listed,
        @endif
        with all resulting file numbers to be commissioned in a single operation.
    </p>

    <table class="stage-table">
        <thead>
            <tr>
                <th style="width:12%">Order</th>
                <th style="width:34%">Update</th>
                <th>Effect on the parcel</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($duplex->stageRows as $stage)
            <tr>
                <td class="mono">{{ $stage->rank }}</td>
                <td>{{ $stage->label() }}</td>
                <td>
                    @switch($stage->type)
                        @case('merger')
                            The source parcels are consolidated into one; the sources are decommissioned.
                            @break
                        @case('subdivision')
                            The parcel is divided into {{ $stage->outputCount() }} units; the mother file is decommissioned.
                            @break
                        @case('separation')
                            The parcel is separated into {{ $stage->outputCount() }} parts; the original is decommissioned.
                            @break
                        @case('extension')
                            The parcel boundary is adjusted; the original record is replaced.
                            @break
                        @case('change_of_purpose')
                            The land use changes to <strong>{{ $stage->payload['new_land_use'] ?? '—' }}</strong>;
                            the file number is re-issued under the new purpose.
                            @break
                    @endswitch
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p class="body-paragraph">
        Submitted for the approval of the Honourable Commissioner / Permanent Secretary.
    </p>

    @if ($duplex->knupda_remarks)
    <p class="note"><strong>KNUPDA remarks:</strong> {{ $duplex->knupda_remarks }}</p>
    @endif
@endsection
