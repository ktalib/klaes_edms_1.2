@extends('layouts.app')

@section('page-title', 'User Activity Monitoring')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
@endpush

@section('content')
@php
    $activityMonitoringConfig = [
        'initialPayload' => $initialPayload,
        'statsEndpoint' => route('activity-monitoring.stats'),
        'breakdownEndpoint' => route('activity-monitoring.users.breakdown', ['user' => '__USER__']),
        'timelineEndpoint' => route('activity-monitoring.users.timeline', ['user' => '__USER__']),
        'weeklyEndpoint' => route('activity-monitoring.users.weekly', ['user' => '__USER__']),
        'canExport' => $canExport,
        'exportCsvEndpoint' => route('activity-monitoring.export.csv'),
        'exportPdfEndpoint' => route('activity-monitoring.export.pdf'),
        'leaderboardEndpoint' => route('activity-monitoring.leaderboard'),
        'forceAlert' => $forceAlert ?? false,
    ];
@endphp
<div class="flex-1 overflow-auto bg-gray-50"
     x-data="activityMonitoringPage(@js($activityMonitoringConfig))"
     x-init="init()">
    @include('admin.header')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        @include('activity-monitoring.partials.header')
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <p class="text-sm uppercase tracking-wide text-blue-600 font-semibold">User Activity Monitoring</p>
                <h1 class="text-3xl font-bold text-gray-900 mt-1">Team Performance Dashboard</h1>
                <p class="text-gray-500 mt-2">
                    Track indexing throughput, property record progress, page typing, scanning and blind scans with live trend indicators.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-sm text-gray-500">
                    Last refreshed
                    <span class="font-semibold text-gray-900" x-text="formatTimestamp(state.meta?.last_refreshed_at)"></span>
                </div>
                <button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                        @click="fetchStats()">
                    <i class="fas fa-rotate mr-2"></i>
                    Refresh
                </button>
                <div class="relative" x-data="{ open: false }" x-show="config.canExport" @click.away="open = false">
                    <button @click="open = !open"
                            class="inline-flex items-center px-4 py-2 border border-blue-600 text-blue-600 rounded-md text-sm font-semibold hover:bg-blue-50">
                        <i class="fas fa-download mr-2"></i>
                        Export
                        <i class="fas fa-chevron-down ml-2 text-xs"></i>
                    </button>
                    <div x-show="open" x-transition
                         class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-slate-100 z-30">
                        <a href="#" @click.prevent="triggerExport('csv'); open = false"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-slate-50">
                            <i class="fas fa-file-csv text-emerald-500"></i> CSV Report
                        </a>
                        <a href="#" @click.prevent="triggerExport('pdf'); open = false"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-slate-50">
                            <i class="fas fa-file-pdf text-rose-500"></i> PDF Report
                        </a>
                        <div class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed">
                            <i class="fas fa-paper-plane"></i> Email summary (soon)
                        </div>
                    </div>
                </div>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-50 text-blue-600 text-sm font-semibold"
                     x-show="loading"
                     x-transition
                     x-cloak>
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    Updating metrics...
                </div>
            </div>
        </div>

        <template x-if="alerts.length">
            <div class="space-y-3">
                <template x-for="alert in alerts" :key="alert.id">
                    <div :class="alertToneClasses(alert.tone)">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide opacity-80" x-text="alert.window?.label || 'Daily target window'"></p>
                                <h3 class="text-lg font-bold mt-1" x-text="alert.headline"></h3>
                                <p class="text-sm opacity-90" x-text="alert.details"></p>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-bold" x-text="`${formatNumber(alert.achieved)} / ${formatNumber(alert.target)}`"></div>
                                <p class="text-xs opacity-80" x-text="alert.percent !== null && alert.percent !== undefined ? `${alert.percent}% complete` : 'No progress yet'"></p>
                                <p class="text-xs opacity-70" x-show="alert.expected" x-text="`Pace target: ${formatNumber(alert.expected)} by now`"></p>
                            </div>
                        </div>
                        <div class="mt-4" x-show="alert.target > 0">
                            <div class="w-full bg-white/40 rounded-full h-2 overflow-hidden">
                                <div class="h-2 rounded-full"
                                     :class="alert.tone === 'danger' ? 'bg-rose-500' : (alert.tone === 'warning' ? 'bg-amber-500' : 'bg-emerald-500')"
                                     :style="`width: ${Math.min(100, alert.percent || 0)}%`"></div>
                            </div>
                            <div class="flex justify-between text-xs mt-1 opacity-80">
                                <span x-text="alert.modules?.length === 1 ? alert.modules[0].label : 'Combined output'"></span>
                                <span x-text="alert.percent !== null && alert.percent !== undefined ? `${alert.percent}%` : ''"></span>
                            </div>
                            <div class="mt-3 grid gap-2 md:grid-cols-3" x-show="alert.modules?.length > 1">
                                <template x-for="module in alert.modules" :key="module.key">
                                    <div class="rounded-lg bg-white/40 px-3 py-2 text-xs font-semibold">
                                        <div class="text-[11px] uppercase tracking-wide opacity-70" x-text="module.label"></div>
                                        <div class="text-sm" x-text="`${formatNumber(module.count)} / ${formatNumber(module.target)}`"></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
 

        @include('activity-monitoring.partials.filters')

        <div x-cloak x-show="activeTab === 'team'">
            @include('activity-monitoring.partials.summary-cards')
        </div>

        <div x-cloak x-show="activeTab === 'performance'">
            <div class="bg-white rounded-xl shadow p-6 space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Performance Metrics</h2>
                        <p class="text-sm text-gray-500">Heatmaps, distributions and contribution breakdowns</p>
                    </div>
                    <div class="text-sm text-gray-500">
                        Displaying <span class="font-semibold" x-text="state.meta?.active_modules?.length ?? 0"></span> Work Stations
                    </div>
                </div>
                @include('activity-monitoring.partials.charts')
            </div>
        </div>

        <div x-cloak x-show="activeTab === 'workstations'">
            @include('activity-monitoring.partials.summary-bar')
        </div>

        <div x-cloak x-show="activeTab === 'target'">
            @include('activity-monitoring.partials.leaderboard-table')
        </div>

        <div x-cloak x-show="activeTab === 'leaderboard'">
            @include('activity-monitoring.partials.leaderboard-top15')
        </div>

    </div>

    @include('activity-monitoring.partials.user-detail-modal')

    @include('admin.footer')
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
function activityMonitoringPage(config) {
    return {
        config,
        state: config.initialPayload,
        filters: {
            date_from: null,
            date_to: null,
            preset: 'this_week',
            users: [],
            workstations: [],
        },
        availableUsers: config.initialPayload.filters?.users?.options ?? [],
        alerts: [],
        forceAlert: config.forceAlert ?? false,
        loading: false,
        charts: { heatmap: null, contribution: null, modules: null },
        autoRefreshHandle: null,
        userSearch: '',
        detailModal: {
            open: false,
            user: null,
            loading: false,
            breakdown: null,
            timeline: [],
            weekly: null,
        },
        leaderboardTab: {
            loading: false,
            rows: [],
            summary: null,
            meta: null,
            columns: [],
            loaded: false,
        },
        activeTab: 'team',
        init() {
            this.filters.date_from = this.state.meta?.date_range?.start ?? null;
            this.filters.date_to = this.state.meta?.date_range?.end ?? null;
            this.filters.preset = this.state.meta?.date_range?.preset ?? 'this_week';
            this.filters.users = [...(this.state.filters?.users?.selected ?? [])];
            this.filters.workstations = [...(this.state.filters?.workstations?.selected ?? [])];
            this.setupFlatpickr();
            this.renderCharts();
            this.syncAlerts();
            const refreshSeconds = this.state.meta?.auto_refresh_seconds ?? 300;
            this.autoRefreshHandle = setInterval(() => this.fetchStats(true), refreshSeconds * 1000);
        },
        setupFlatpickr() {
            const opts = {
                dateFormat: 'Y-m-d',
                onChange: () => {
                    this.filters.preset = 'custom';
                    this.filters.date_from = this.$refs.startDate?.value ?? null;
                    this.filters.date_to = this.$refs.endDate?.value ?? null;
                }
            };
            if (this.$refs.startDate) {
                flatpickr(this.$refs.startDate, {...opts, defaultDate: this.filters.date_from});
            }
            if (this.$refs.endDate) {
                flatpickr(this.$refs.endDate, {...opts, defaultDate: this.filters.date_to});
            }
        },
        syncDateInputs() {
            if (this.$refs.startDate && this.$refs.startDate._flatpickr) {
                this.$refs.startDate._flatpickr.setDate(this.filters.date_from || null, true);
            } else if (this.$refs.startDate) {
                this.$refs.startDate.value = this.filters.date_from || '';
            }
            if (this.$refs.endDate && this.$refs.endDate._flatpickr) {
                this.$refs.endDate._flatpickr.setDate(this.filters.date_to || null, true);
            } else if (this.$refs.endDate) {
                this.$refs.endDate.value = this.filters.date_to || '';
            }
        },
        processedUsers() {
            if (!this.userSearch) {
                return this.availableUsers;
            }
            const term = this.userSearch.toLowerCase();
            return this.availableUsers.filter(user =>
                user.name.toLowerCase().includes(term) ||
                (user.department || '').toLowerCase().includes(term)
            );
        },
        toggleUser(userId) {
            const index = this.filters.users.indexOf(userId);
            if (index === -1) {
                this.filters.users.push(userId);
            } else {
                this.filters.users.splice(index, 1);
            }
        },
        selectAllUsers() {
            this.filters.users = this.availableUsers.map(user => user.id);
        },
        clearUsers() {
            this.filters.users = [];
        },
        toggleWorkStation(optionValue) {
            const index = this.filters.workstations.indexOf(optionValue);
            if (index === -1) {
                this.filters.workstations.push(optionValue);
            } else {
                this.filters.workstations.splice(index, 1);
            }
        },
        applyPreset(preset) {
            this.filters.preset = preset;
            this.filters.date_from = null;
            this.filters.date_to = null;
            this.syncDateInputs();
            this.fetchStats();
        },
        applyFilters() {
            this.fetchStats();
        },
        resetFilters() {
            this.filters.date_from = this.state.meta?.date_range?.start ?? null;
            this.filters.date_to = this.state.meta?.date_range?.end ?? null;
            this.filters.preset = this.state.meta?.date_range?.preset ?? 'this_week';
            this.filters.users = [...(this.state.filters?.users?.selected ?? [])];
            this.filters.workstations = [...(this.state.filters?.workstations?.selected ?? [])];
            this.syncDateInputs();
            this.fetchStats();
        },
        async fetchStats(isAuto = false) {
            this.loading = true;
            const query = this.buildQueryString();
            try {
                const response = await fetch(`${this.config.statsEndpoint}?${query}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) throw new Error('Unable to load statistics');
                this.state = await response.json();
                if (!isAuto) {
                    this.filters.date_from = this.state.meta?.date_range?.start ?? this.filters.date_from;
                    this.filters.date_to = this.state.meta?.date_range?.end ?? this.filters.date_to;
                    this.filters.workstations = [...(this.state.filters?.workstations?.selected ?? this.filters.workstations)];
                    this.syncDateInputs();
                }
                this.renderCharts();
                this.syncAlerts();
                this.leaderboardTab.loaded = false;
                if (this.activeTab === 'leaderboard') {
                    this.loadLeaderboardDetail();
                }
            } catch (error) {
                console.error(error);
            } finally {
                this.loading = false;
            }
        },
        buildQueryString(extra = {}) {
            const params = new URLSearchParams();
            const currentUrl = new URLSearchParams(window.location.search).get('url');
            if (this.filters.date_from) params.set('date_from', this.filters.date_from);
            if (this.filters.date_to) params.set('date_to', this.filters.date_to);
            if (this.filters.preset) params.set('preset', this.filters.preset);
            if (this.filters.users.length) params.set('users', this.filters.users.join(','));
            if (this.filters.workstations.length) params.set('workstations', this.filters.workstations.join(','));
            if (this.forceAlert) params.set('force_alert', '1');
            if (currentUrl) params.set('url', currentUrl);
            Object.entries(extra).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') {
                    params.set(key, value);
                }
            });
            return params.toString();
        },
        renderCharts() {
            this.$nextTick(() => {
                this.renderHeatmap();
                this.renderContribution();
                this.renderModuleDistribution();
            });
        },
        syncAlerts() {
            const insight = this.state.personal_alert;
            if (!insight) {
                this.alerts = [];
                return;
            }

            this.alerts = [
                Object.assign({
                    id: `personal-${insight.status || 'status'}`,
                }, insight),
            ];
        },
        alertToneClasses(tone) {
            const palette = {
                success: 'bg-emerald-50 border border-emerald-200 text-emerald-900 shadow-sm',
                warning: 'bg-amber-50 border border-amber-200 text-amber-900 shadow-sm',
                danger: 'bg-rose-50 border border-rose-200 text-rose-900 shadow-sm',
                info: 'bg-blue-50 border border-blue-200 text-blue-900 shadow-sm',
            };
            return `rounded-2xl p-5 ${palette[tone] || palette.info}`;
        },
        renderHeatmap() {
            if (!this.$refs.heatmapChart) return;
            const options = {
                chart: { type: 'area', height: 320, toolbar: { show: false } },
                stroke: { curve: 'smooth', width: 2 },
                dataLabels: { enabled: false },
                xaxis: { categories: this.state.charts?.activity_heatmap?.categories ?? [] },
                colors: (this.state.charts?.activity_heatmap?.series ?? []).map(s => s.color),
                series: this.state.charts?.activity_heatmap?.series ?? []
            };

            if (!this.charts.heatmap) {
                this.charts.heatmap = new ApexCharts(this.$refs.heatmapChart, options);
                this.charts.heatmap.render();
            } else {
                this.charts.heatmap.updateOptions(options, false, true);
            }
        },
        renderContribution() {
            if (!this.$refs.contributionChart) return;
            const options = {
                chart: { type: 'donut', height: 320 },
                labels: (this.state.charts?.user_contribution?.series ?? []).map(item => item.name),
                series: (this.state.charts?.user_contribution?.series ?? []).map(item => item.value),
                dataLabels: { enabled: true },
                legend: { position: 'bottom' }
            };
            if (!this.charts.contribution) {
                this.charts.contribution = new ApexCharts(this.$refs.contributionChart, options);
                this.charts.contribution.render();
            } else {
                this.charts.contribution.updateOptions(options, false, true);
            }
        },
        renderModuleDistribution() {
            if (!this.$refs.moduleChart) return;
            const categories = (this.state.charts?.module_distribution?.series ?? []).map(item => item.label);
            const series = (this.state.leaderboard?.rows ?? []).map(row => ({
                name: row.user.name,
                data: (this.state.charts?.module_distribution?.series ?? []).map(module =>
                    module.segments.find(segment => segment.user_id === row.user.id)?.value ?? 0
                )
            }));

            const options = {
                chart: { type: 'bar', height: 360, stacked: true, toolbar: { show: false } },
                plotOptions: { bar: { horizontal: true } },
                xaxis: { categories },
                series
            };

            if (!this.charts.modules) {
                this.charts.modules = new ApexCharts(this.$refs.moduleChart, options);
                this.charts.modules.render();
            } else {
                this.charts.modules.updateOptions(options, false, true);
            }
        },
        formatNumber(value) {
            if (value === null || value === undefined) return '0';
            return Intl.NumberFormat().format(value);
        },
        formatPercent(value) {
            if (value === null || value === undefined) return '0%';
            return `${value}%`;
        },
        formatTimestamp(value) {
            if (!value) return '—';
            return new Date(value).toLocaleString();
        },
        triggerExport(type) {
            const endpoint = type === 'pdf' ? this.config.exportPdfEndpoint : this.config.exportCsvEndpoint;
            window.location = `${endpoint}?${this.buildQueryString()}`;
        },
        openLeaderboard() {
            const query = this.buildQueryString();
            const connector = query ? '&' : '';
            const url = `${this.config.leaderboardEndpoint}?${query}${connector}leaderboard_limit=15`;
            window.open(url, '_blank');
        },
        openUserModal(row) {
            this.detailModal.user = row.user;
            this.detailModal.open = true;
            this.loadUserDetail(row.user.id);
        },
        setTab(tab) {
            this.activeTab = tab;
            if (tab === 'leaderboard') {
                this.ensureLeaderboardDetail();
            }
        },
        ensureLeaderboardDetail() {
            if (this.leaderboardTab.loaded) {
                return;
            }
            this.loadLeaderboardDetail();
        },
        async loadLeaderboardDetail() {
            this.leaderboardTab.loading = true;
            try {
                const query = this.buildQueryString({ leaderboard_limit: 15 });
                const response = await fetch(`${this.config.statsEndpoint}?${query}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) throw new Error('Unable to load leaderboard');
                const data = await response.json();
                this.leaderboardTab.rows = data.leaderboard?.rows ?? [];
                this.leaderboardTab.summary = data.summary_cards ?? null;
                this.leaderboardTab.meta = data.meta ?? null;
                this.leaderboardTab.columns = data.leaderboard?.columns ?? [];
                this.leaderboardTab.loaded = true;
            } catch (error) {
                console.error(error);
                this.leaderboardTab.loaded = false;
            } finally {
                this.leaderboardTab.loading = false;
            }
        },
        async loadUserDetail(userId) {
            this.detailModal.loading = true;
            try {
                const [breakdown, timeline, weekly] = await Promise.all([
                    fetch(this.config.breakdownEndpoint.replace('__USER__', userId) + `?${this.buildQueryString()}`),
                    fetch(this.config.timelineEndpoint.replace('__USER__', userId)),
                    fetch(this.config.weeklyEndpoint.replace('__USER__', userId))
                ]);
                this.detailModal.breakdown = await breakdown.json();
                const timelineData = await timeline.json();
                this.detailModal.timeline = timelineData.data ?? [];
                this.detailModal.weekly = await weekly.json();
            } catch (error) {
                console.error(error);
            } finally {
                this.detailModal.loading = false;
            }
        }
    };
}
</script>
@endpush
