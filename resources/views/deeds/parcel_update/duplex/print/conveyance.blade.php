@extends('deeds.parcel_update.duplex.print._layout')

@section('doc-title', 'Deed of Conveyance — Duplex Parcel Update')

@section('doc-body')
    <table class="meta">
        <tr><td>Applicant / Holder</td><td>{{ $duplex->applicant_name ?: '—' }}</td></tr>
        <tr><td>Source File Number(s)</td>
            <td class="mono">{{ implode(', ', (array) ($duplex->source_file_nos ?? [])) ?: '—' }}</td></tr>
        <tr><td>Location</td>
            <td>{{ collect([$duplex->plot_no, $duplex->street_name, $duplex->district, $duplex->lga, $duplex->state])->filter()->implode(', ') ?: '—' }}</td></tr>
    </table>

    <p class="body-paragraph">
        This conveyance covers the parcel{{ count((array) $duplex->source_file_nos) > 1 ? 's' : '' }} named
        above and the {{ $duplex->isSingleStage() ? 'update' : 'series of updates' }} carried out under
        duplex reference <span class="mono">{{ $duplex->duplex_id }}</span>. The parcels resulting from the
        final stage are the parcels conveyed; every earlier record in the chain is decommissioned and
        ceases to have effect.
    </p>

    <table class="stage-table">
        <thead>
            <tr>
                <th style="width:12%">Order</th>
                <th style="width:30%">Update</th>
                <th style="width:29%">Holding</th>
                <th style="width:29%">File number assigned</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($duplex->stageRows as $stage)
                @forelse ($stage->files as $file)
                <tr>
                    <td class="mono">{{ $loop->first ? $stage->rank : '' }}</td>
                    <td>{{ $loop->first ? $stage->label() : '' }}</td>
                    <td class="mono">{{ $file->holding_no ?: $file->source_file_no ?: '—' }}</td>
                    <td class="mono">{{ $file->final_file_no ?: '— pending commissioning —' }}</td>
                </tr>
                @empty
                <tr>
                    <td class="mono">{{ $stage->rank }}</td>
                    <td>{{ $stage->label() }}</td>
                    <td colspan="2">No holding numbers recorded.</td>
                </tr>
                @endforelse
            @endforeach
        </tbody>
    </table>

    @php $holders = $duplex->files->pluck('holder_name')->filter()->unique(); @endphp
    @if ($holders->count() > 1)
    <p class="body-paragraph">
        <strong>Holders:</strong> {{ $holders->implode('; ') }}. Where a resulting parcel is held by a
        person other than the applicant, the holder named against that parcel takes the conveyance for it.
    </p>
    @endif

    <p class="note">
        File numbers shown as pending are assigned when the Lands department commissions this duplex.
    </p>
@endsection
