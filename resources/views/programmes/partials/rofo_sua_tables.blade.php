<div id="wrap-sua-not" class="table-container">
    <table id="table-sua-not" class="simple">
        <thead>
            <tr>
                <th>ST FileNo</th>
                <th>Scheme No</th>
                <th>Unit Owner</th>
                <th>Land Use</th>
                <th>LGA</th>
                <th>Unit Type</th>
                <th>Unit/Section/Block</th>
                <th>ST Memo Status</th>
                <th>RoFO Details</th>
                <th>Date Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($applications ?? collect())->filter(function($app) {
                return !($app->rofo_generated ?? false);
            }) as $unitApplication)
            <tr>
                <td>{{ $unitApplication->fileno ?? 'N/A' }}</td>
                <td>{{ $unitApplication->scheme_no ?? 'N/A' }}</td>
                <td>{{ $unitApplication->owner_name ?? 'N/A' }}</td>
                <td>{{ $unitApplication->land_use ?? 'N/A' }}</td>
                <td>{{ $unitApplication->property_lga ?? 'N/A' }}</td>
                <td>{{ $unitApplication->unit_type_label ?? 'SUA' }}</td>
                <td>{{ $unitApplication->unit_number ?? '' }}-{{ $unitApplication->floor_number ?? '' }}-{{ $unitApplication->block_number ?? '' }}</td>
                <td>
                    @if($unitApplication->has_st_memo ?? false)
                        <span>Generated</span>
                    @else
                        <span class="muted">Not Generated</span>
                    @endif
                </td>
                <td>
                    @if($unitApplication->details_captured ?? false)
                        <span>Captured</span>
                    @else
                        <span class="muted">Pending</span>
                    @endif
                </td>
                <td>{{ $unitApplication->created_at ? date('d-m-Y', strtotime($unitApplication->created_at)) : 'N/A' }}</td>
                <td class="actions">
                    <div class="dd">
                            <button type="button" class="dd-btn" onclick="toggleDD(this)">
                                <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                            </button>
                        <div class="dd-menu">
                            <a href="{{ route('sectionaltitling.viewrecorddetail_sub', $unitApplication->id) }}" class="dd-item">
                                <i data-lucide="eye" class="w-4 h-4 text-slate-400"></i>
                                <span>View Application</span>
                            </a>
                            @if($unitApplication->has_st_memo ?? false)
                                <a href="{{ route('programmes.generate_rofo', $unitApplication->id) }}" class="dd-item">
                                    <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-500"></i>
                                    <span>Generate RoFO</span>
                                </a>
                            @else
                                <span class="dd-item disabled">
                                    <i data-lucide="plus-circle" class="w-4 h-4 opacity-40"></i>
                                    <span>Generate RoFO</span>
                                </span>
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="muted">No SUA records pending RoFO generation</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div id="wrap-sua-gen" class="table-container" style="display:none;">
    <table id="table-sua-gen" class="simple">
        <thead>
            <tr>
                <th>ST FileNo</th>
                <th>RoFO No</th>
                <th>Scheme No</th>
                <th>Unit Owner</th>
                <th>Land Use</th>
                <th>LGA</th>
                <th>Unit Type</th>
                <th>Unit/Section/Block</th>
                <th>RoFO Details</th>
                <th>Date Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($applications ?? collect())->filter(function($app) {
                return (bool) ($app->rofo_generated ?? false);
            }) as $unitApplication)
            <tr>
                <td>{{ $unitApplication->fileno ?? 'N/A' }}</td>
                <td><strong>{{ $unitApplication->rofo_no ?? 'N/A' }}</strong></td>
                <td>{{ $unitApplication->scheme_no ?? 'N/A' }}</td>
                <td>{{ $unitApplication->owner_name ?? 'N/A' }}</td>
                <td>{{ $unitApplication->land_use ?? 'N/A' }}</td>
                <td>{{ $unitApplication->property_lga ?? 'N/A' }}</td>
                <td>{{ $unitApplication->unit_type_label ?? 'SUA' }}</td>
                <td>{{ $unitApplication->unit_number ?? '' }}-{{ $unitApplication->floor_number ?? '' }}-{{ $unitApplication->block_number ?? '' }}</td>
                <td>
                    @if($unitApplication->details_captured ?? false)
                        <span>Captured</span>
                    @else
                        <span class="muted">Pending</span>
                    @endif
                </td>
                <td>{{ $unitApplication->created_at ? date('d-m-Y', strtotime($unitApplication->created_at)) : 'N/A' }}</td>
                <td class="actions">
                    <div class="dd">
                            <button type="button" class="dd-btn" onclick="toggleDD(this)">
                                <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                            </button>
                        <div class="dd-menu">
                            <a href="{{ route('sectionaltitling.viewrecorddetail_sub', $unitApplication->id) }}" class="dd-item">
                                <i data-lucide="eye" class="w-4 h-4 text-slate-400"></i>
                                <span>View Application</span>
                            </a>
                            @php
                                $printCounter = (int) ($unitApplication->print_counter ?? 0);
                                $viewRofoUrl = route('programmes.view_rofo', $unitApplication->id);
                            @endphp

                            <button type="button" class="dd-item"
                                onclick="SmartPrintManager.open(
                                    '{{ $unitApplication->fileno }}',
                                    'ST RoFO',
                                    '{{ route('programmes.print_rofo', $unitApplication->id) }}'
                                )">
                                <i data-lucide="printer" class="w-4 h-4 text-indigo-600"></i>
                                <span>Print Manager</span>
                            </button>
                            @if($unitApplication->security_paper_code)
                                <span class="dd-item disabled" title="Security code already assigned">
                                    <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i>
                                    <span>Code Assigned: {{ $unitApplication->security_paper_code }}</span>
                                </span>
                            @elseif($printCounter < 1)
                                <span class="dd-item disabled" title="Print the RoFO before entering a security code">
                                    <i data-lucide="shield" class="w-4 h-4 text-slate-400"></i>
                                    <span>Enter Security Code (print first)</span>
                                </span>
                            @else
                                <button type="button" class="dd-item"
                                    onclick="openSecurityPaperModal('{{ $unitApplication->id }}', '{{ $unitApplication->fileno }}', '{{ $unitApplication->security_paper_code }}')">
                                    <i data-lucide="shield" class="w-4 h-4 text-emerald-600"></i>
                                    <span>Enter Security Code</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="muted">No generated SUA RoFO applications found</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
