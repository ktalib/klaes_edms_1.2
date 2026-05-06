@extends('app.layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-2">Report Comparison</h1>
        <p class="text-gray-600">Compare activity metrics between two periods</p>
    </div>

    <!-- Period Selector -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <!-- Period 1 -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Period 1</label>
                <input type="number" id="period1Days" value="30" min="1" max="365" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Period 2 -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Period 2</label>
                <input type="number" id="period2Days" value="60" min="1" max="365" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Metric Selector -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Metric</label>
                <select id="metricSelector" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="sessions">Total Sessions</option>
                    <option value="users">Unique Users</option>
                    <option value="duration">Avg Duration</option>
                    <option value="all">All Metrics</option>
                </select>
            </div>

            <!-- Actions -->
            <div class="flex gap-2">
                <button id="compareBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Compare
                </button>
                <button id="exportBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    Export
                </button>
            </div>
        </div>
    </div>

    <!-- Comparison Results -->
    <div id="comparisonResults" class="space-y-8">
        <!-- Loading State -->
        <div id="loadingState" class="text-center py-12">
            <p class="text-gray-500">Select periods and click "Compare" to view results</p>
        </div>

        <!-- Comparison Table (Hidden Initially) -->
        <div id="comparisonTable" class="hidden bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 border-b border-gray-300">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Metric</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-gray-700">
                                <span id="period1Header">Period 1 (30 days)</span>
                            </th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-gray-700">
                                <span id="period2Header">Period 2 (60 days)</span>
                            </th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-gray-700">Change</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-gray-700">% Change</th>
                        </tr>
                    </thead>
                    <tbody id="comparisonBody">
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Key Insights -->
        <div id="keyInsights" class="hidden">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Key Insights</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h4 class="font-medium text-green-900 mb-2">📈 Improvements</h4>
                    <ul id="improvementsList" class="space-y-1 text-sm text-green-800">
                        <!-- Populated by JavaScript -->
                    </ul>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <h4 class="font-medium text-red-900 mb-2">📉 Declines</h4>
                    <ul id="declinesList" class="space-y-1 text-sm text-red-800">
                        <!-- Populated by JavaScript -->
                    </ul>
                </div>
            </div>
        </div>

        <!-- Comparison Chart -->
        <div id="chartSection" class="hidden bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Trend Comparison</h3>
            <canvas id="comparisonChart" height="300"></canvas>
        </div>
    </div>

    <!-- Error Message -->
    <div id="errorMessage" class="hidden bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
        <!-- Error content populated by JavaScript -->
    </div>

    <!-- Loading Indicator -->
    <div id="loadingIndicator" style="display: none;" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 text-center">
            <div class="animate-spin mb-4">
                <div class="text-3xl">⟳</div>
            </div>
            <p class="text-gray-700 font-medium">Comparing periods...</p>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
/**
 * Report Comparison Dashboard
 * Compares metrics between two time periods
 */
class ReportComparisonDashboard {
    constructor() {
        this.comparisonChart = null;
        this.apiBaseUrl = '/api/analytics';
        this.init();
    }

    init() {
        this.attachEventListeners();
    }

    attachEventListeners() {
        document.getElementById('compareBtn').addEventListener('click', () => this.compareData());
        document.getElementById('exportBtn').addEventListener('click', () => this.exportComparison());
        document.getElementById('period1Days').addEventListener('change', () => this.updateHeaders());
        document.getElementById('period2Days').addEventListener('change', () => this.updateHeaders());
    }

    updateHeaders() {
        const period1 = document.getElementById('period1Days').value;
        const period2 = document.getElementById('period2Days').value;
        document.getElementById('period1Header').textContent = `Period 1 (${period1} days)`;
        document.getElementById('period2Header').textContent = `Period 2 (${period2} days)`;
    }

    compareData() {
        this.showLoading(true);
        this.hideError();

        const period1Days = parseInt(document.getElementById('period1Days').value);
        const period2Days = parseInt(document.getElementById('period2Days').value);
        const metric = document.getElementById('metricSelector').value;

        Promise.all([
            this.fetchSessionStats(period1Days),
            this.fetchSessionStats(period2Days),
            this.fetchTrends(period1Days),
            this.fetchTrends(period2Days),
        ])
        .then(([stats1, stats2, trends1, trends2]) => {
            this.renderComparison(stats1, stats2, metric);
            this.renderTrendComparison(trends1, trends2);
            this.generateInsights(stats1, stats2);
            this.showLoading(false);
        })
        .catch(err => {
            console.error('Error comparing data:', err);
            this.showError('Failed to compare periods. Please try again.');
            this.showLoading(false);
        });
    }

    async fetchSessionStats(days) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/session-stats?days=${days}`, {
                headers: { 'Authorization': `Bearer ${this.getToken()}` }
            });
            const data = await response.json();
            return data.success ? data.data : null;
        } catch (err) {
            console.error('Error fetching stats:', err);
            return null;
        }
    }

    async fetchTrends(days) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/trends?days=${days}&granularity=daily`, {
                headers: { 'Authorization': `Bearer ${this.getToken()}` }
            });
            const data = await response.json();
            return data.success ? data.data : null;
        } catch (err) {
            console.error('Error fetching trends:', err);
            return null;
        }
    }

    renderComparison(stats1, stats2, metric) {
        if (!stats1 || !stats2) {
            this.showError('Unable to retrieve data for comparison');
            return;
        }

        const metrics = [
            { key: 'total_sessions', label: 'Total Sessions', value1: stats1.total_sessions, value2: stats2.total_sessions },
            { key: 'unique_users', label: 'Unique Users', value1: stats1.unique_users, value2: stats2.unique_users },
            { key: 'avg_duration_per_session', label: 'Avg Duration (min)', value1: stats1.avg_duration_per_session, value2: stats2.avg_duration_per_session },
            { key: 'total_duration_hours', label: 'Total Duration (hours)', value1: stats1.total_duration_hours, value2: stats2.total_duration_hours },
            { key: 'avg_sessions_per_user', label: 'Avg Sessions per User', value1: stats1.avg_sessions_per_user, value2: stats2.avg_sessions_per_user },
        ];

        let filteredMetrics = metrics;
        if (metric !== 'all') {
            filteredMetrics = metrics.filter(m => m.key === `${metric}_sessions` || m.key === metric);
        }

        const tbody = document.getElementById('comparisonBody');
        tbody.innerHTML = filteredMetrics.map(m => {
            const change = m.value2 - m.value1;
            const percentChange = m.value1 > 0 ? ((change / m.value1) * 100).toFixed(1) : 0;
            const isPositive = change >= 0;
            const arrow = isPositive ? '↑' : '↓';
            const color = isPositive ? 'text-green-600' : 'text-red-600';

            return `
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium text-gray-900">${m.label}</td>
                    <td class="px-6 py-4 text-right text-gray-900">${this.formatValue(m.value1)}</td>
                    <td class="px-6 py-4 text-right text-gray-900">${this.formatValue(m.value2)}</td>
                    <td class="px-6 py-4 text-right ${color} font-medium">${arrow} ${this.formatValue(change)}</td>
                    <td class="px-6 py-4 text-right ${color} font-semibold">${arrow} ${percentChange}%</td>
                </tr>
            `;
        }).join('');

        document.getElementById('comparisonTable').classList.remove('hidden');
        document.getElementById('loadingState').classList.add('hidden');
    }

    renderTrendComparison(trends1, trends2) {
        if (!trends1 || !trends2) return;

        const ctx = document.getElementById('comparisonChart');

        if (this.comparisonChart) this.comparisonChart.destroy();

        // Limit to 30 data points for readability
        const labels = trends1.labels.slice(0, 30);
        const sessions1 = trends1.sessions.slice(0, 30);
        const sessions2 = trends2.sessions.slice(0, 30);

        this.comparisonChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Period 1 Sessions',
                        data: sessions1,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                    },
                    {
                        label: 'Period 2 Sessions',
                        data: sessions2,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                },
                scales: {
                    y: { beginAtZero: true },
                },
            },
        });

        document.getElementById('chartSection').classList.remove('hidden');
    }

    generateInsights(stats1, stats2) {
        const insights = {
            improvements: [],
            declines: [],
        };

        // Compare metrics
        const comparisons = [
            { label: 'Total Sessions', val1: stats1.total_sessions, val2: stats2.total_sessions },
            { label: 'Unique Users', val1: stats1.unique_users, val2: stats2.unique_users },
            { label: 'Avg Duration', val1: stats1.avg_duration_per_session, val2: stats2.avg_duration_per_session },
        ];

        comparisons.forEach(comp => {
            const change = comp.val2 - comp.val1;
            const percent = comp.val1 > 0 ? ((change / comp.val1) * 100).toFixed(0) : 0;

            if (change > 0) {
                insights.improvements.push(`${comp.label} increased by ${percent}%`);
            } else if (change < 0) {
                insights.declines.push(`${comp.label} decreased by ${Math.abs(percent)}%`);
            }
        });

        // Render insights
        document.getElementById('improvementsList').innerHTML = insights.improvements
            .map(insight => `<li>• ${insight}</li>`)
            .join('');
        document.getElementById('declinesList').innerHTML = insights.declines
            .map(decline => `<li>• ${decline}</li>`)
            .join('');

        if (insights.improvements.length > 0 || insights.declines.length > 0) {
            document.getElementById('keyInsights').classList.remove('hidden');
        }
    }

    formatValue(value) {
        if (typeof value !== 'number') return 'N/A';
        if (value > 1000) {
            return (value / 1000).toFixed(1) + 'k';
        }
        return Math.round(value).toLocaleString();
    }

    showLoading(show) {
        document.getElementById('loadingIndicator').style.display = show ? 'flex' : 'none';
    }

    showError(message) {
        const errorDiv = document.getElementById('errorMessage');
        errorDiv.textContent = message;
        errorDiv.classList.remove('hidden');
    }

    hideError() {
        document.getElementById('errorMessage').classList.add('hidden');
    }

    exportComparison() {
        alert('Export feature coming soon. You can capture this view as PDF using your browser print function.');
    }

    getToken() {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.content : '';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    new ReportComparisonDashboard();
});
</script>
@endsection
