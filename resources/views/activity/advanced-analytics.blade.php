@extends('app.layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-2">Advanced Analytics Dashboard</h1>
        <p class="text-gray-600">Comprehensive activity analysis and insights</p>
    </div>

    <!-- Controls Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <!-- Date Range -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                <select id="dateRange" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="7">Last 7 days</option>
                    <option value="14">Last 14 days</option>
                    <option value="30" selected>Last 30 days</option>
                    <option value="60">Last 60 days</option>
                    <option value="90">Last 90 days</option>
                </select>
            </div>

            <!-- Granularity -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Granularity</label>
                <select id="granularity" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="hourly">Hourly</option>
                    <option value="daily" selected>Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>

            <!-- Refresh Rate -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Auto-refresh</label>
                <select id="refreshRate" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="0">Disabled</option>
                    <option value="30">30 seconds</option>
                    <option value="60" selected>1 minute</option>
                    <option value="300">5 minutes</option>
                </select>
            </div>

            <!-- Actions -->
            <div class="flex gap-2">
                <button id="refreshBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Refresh Now
                </button>
                <button id="exportBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    Export PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Total Sessions -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Total Sessions</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2" id="totalSessions">-</p>
                </div>
                <div class="text-4xl text-blue-500 opacity-20">📊</div>
            </div>
            <p class="text-xs text-gray-500 mt-4">Based on selected period</p>
        </div>

        <!-- Unique Users -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Unique Users</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2" id="uniqueUsers">-</p>
                </div>
                <div class="text-4xl text-green-500 opacity-20">👥</div>
            </div>
            <p class="text-xs text-gray-500 mt-4">Active users in period</p>
        </div>

        <!-- Avg Duration -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Avg Duration</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2" id="avgDuration">-</p>
                </div>
                <div class="text-4xl text-purple-500 opacity-20">⏱️</div>
            </div>
            <p class="text-xs text-gray-500 mt-4">Minutes per session</p>
        </div>

        <!-- Peak Hour -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Peak Hour</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2" id="peakHour">-</p>
                </div>
                <div class="text-4xl text-orange-500 opacity-20">⚡</div>
            </div>
            <p class="text-xs text-gray-500 mt-4">Hour with most activity</p>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Activity Trends Chart -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Activity Trends</h3>
            <canvas id="trendChart" height="300"></canvas>
            <p class="text-xs text-gray-500 mt-4 text-center">Sessions and unique users over time</p>
        </div>

        <!-- Browser Distribution -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Browser Distribution</h3>
            <canvas id="browserChart" height="300"></canvas>
            <p class="text-xs text-gray-500 mt-4 text-center">Browser usage breakdown</p>
        </div>

        <!-- Platform Distribution -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Platform Distribution</h3>
            <canvas id="platformChart" height="300"></canvas>
            <p class="text-xs text-gray-500 mt-4 text-center">Operating system usage</p>
        </div>

        <!-- Peak Hours Heatmap -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Activity by Hour</h3>
            <canvas id="peakHoursChart" height="300"></canvas>
            <p class="text-xs text-gray-500 mt-4 text-center">Hourly activity distribution (0-23)</p>
        </div>

        <!-- Top Users -->
        <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Top 10 Active Users</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 font-semibold text-gray-700">User</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700">Sessions</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700">Total Duration (min)</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700">Avg Duration (min)</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700">Last Login</th>
                        </tr>
                    </thead>
                    <tbody id="topUsersTable">
                        <tr>
                            <td colspan="5" class="text-center py-8 text-gray-500">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Device Analytics -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Device Analytics</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Devices List -->
            <div>
                <h4 class="font-medium text-gray-900 mb-4">Top Devices</h4>
                <div id="devicesList" class="space-y-2">
                    <p class="text-gray-500">Loading...</p>
                </div>
            </div>

            <!-- Browsers List -->
            <div>
                <h4 class="font-medium text-gray-900 mb-4">Top Browsers</h4>
                <div id="browsersList" class="space-y-2">
                    <p class="text-gray-500">Loading...</p>
                </div>
            </div>

            <!-- Platforms List -->
            <div>
                <h4 class="font-medium text-gray-900 mb-4">Top Platforms</h4>
                <div id="platformsList" class="space-y-2">
                    <p class="text-gray-500">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div id="loadingIndicator" style="display: none;" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 text-center">
            <div class="animate-spin mb-4">
                <div class="text-3xl">⟳</div>
            </div>
            <p class="text-gray-700 font-medium">Loading analytics data...</p>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
/**
 * Advanced Analytics Dashboard
 * Real-time analytics with multiple chart types and auto-refresh
 */
class AdvancedAnalyticsDashboard {
    constructor() {
        this.charts = {};
        this.refreshInterval = null;
        this.apiBaseUrl = '/api/analytics';
        this.init();
    }

    init() {
        this.attachEventListeners();
        this.loadData();
        this.setupAutoRefresh();
    }

    attachEventListeners() {
        document.getElementById('refreshBtn').addEventListener('click', () => this.loadData());
        document.getElementById('dateRange').addEventListener('change', () => this.loadData());
        document.getElementById('granularity').addEventListener('change', () => this.loadData());
        document.getElementById('refreshRate').addEventListener('change', () => this.setupAutoRefresh());
        document.getElementById('exportBtn').addEventListener('click', () => this.exportToPdf());
    }

    setupAutoRefresh() {
        if (this.refreshInterval) clearInterval(this.refreshInterval);

        const refreshRate = parseInt(document.getElementById('refreshRate').value);
        if (refreshRate > 0) {
            this.refreshInterval = setInterval(() => this.loadData(), refreshRate * 1000);
        }
    }

    loadData() {
        this.showLoading(true);

        const days = document.getElementById('dateRange').value;
        const granularity = document.getElementById('granularity').value;

        Promise.all([
            this.fetchTrends(days, granularity),
            this.fetchTopUsers(days),
            this.fetchSessionStats(days),
            this.fetchDeviceAnalytics(days),
            this.fetchPeakHours(days),
        ])
        .then(() => this.showLoading(false))
        .catch(err => {
            console.error('Error loading analytics:', err);
            this.showLoading(false);
        });
    }

    async fetchTrends(days, granularity) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/trends?days=${days}&granularity=${granularity}`, {
                headers: { 'Authorization': `Bearer ${this.getToken()}` }
            });
            const data = await response.json();

            if (data.success) {
                this.renderTrendChart(data.data);
            }
        } catch (err) {
            console.error('Error fetching trends:', err);
        }
    }

    async fetchTopUsers(days) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/top-users?days=${days}&limit=10`, {
                headers: { 'Authorization': `Bearer ${this.getToken()}` }
            });
            const data = await response.json();

            if (data.success) {
                this.renderTopUsersTable(data.data);
            }
        } catch (err) {
            console.error('Error fetching top users:', err);
        }
    }

    async fetchSessionStats(days) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/session-stats?days=${days}`, {
                headers: { 'Authorization': `Bearer ${this.getToken()}` }
            });
            const data = await response.json();

            if (data.success) {
                this.renderSessionStats(data.data);
            }
        } catch (err) {
            console.error('Error fetching session stats:', err);
        }
    }

    async fetchDeviceAnalytics(days) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/device-analytics?days=${days}`, {
                headers: { 'Authorization': `Bearer ${this.getToken()}` }
            });
            const data = await response.json();

            if (data.success) {
                this.renderDeviceCharts(data.data);
                this.renderDeviceAnalytics(data.data);
            }
        } catch (err) {
            console.error('Error fetching device analytics:', err);
        }
    }

    async fetchPeakHours(days) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/peak-hours?days=${days}`, {
                headers: { 'Authorization': `Bearer ${this.getToken()}` }
            });
            const data = await response.json();

            if (data.success) {
                this.renderPeakHoursChart(data.data);
                document.getElementById('peakHour').textContent = `${data.data.peak_hour}:00 (${data.data.peak_count} sessions)`;
            }
        } catch (err) {
            console.error('Error fetching peak hours:', err);
        }
    }

    renderSessionStats(data) {
        document.getElementById('totalSessions').textContent = data.total_sessions.toLocaleString();
        document.getElementById('uniqueUsers').textContent = data.unique_users.toLocaleString();
        document.getElementById('avgDuration').textContent = data.avg_duration_per_session.toFixed(1) + ' min';
    }

    renderTrendChart(data) {
        const ctx = document.getElementById('trendChart');

        if (this.charts.trend) this.charts.trend.destroy();

        this.charts.trend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Sessions',
                        data: data.sessions,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                    },
                    {
                        label: 'Unique Users',
                        data: data.unique_users,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        fill: false,
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
    }

    renderDeviceCharts(data) {
        // Browser chart
        const browserCtx = document.getElementById('browserChart');
        if (this.charts.browser) this.charts.browser.destroy();

        this.charts.browser = new Chart(browserCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(data.browsers),
                datasets: [{
                    data: Object.values(data.browsers),
                    backgroundColor: [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                        '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1',
                    ],
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
            },
        });

        // Platform chart
        const platformCtx = document.getElementById('platformChart');
        if (this.charts.platform) this.charts.platform.destroy();

        this.charts.platform = new Chart(platformCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(data.platforms),
                datasets: [{
                    data: Object.values(data.platforms),
                    backgroundColor: [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                    ],
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
            },
        });
    }

    renderPeakHoursChart(data) {
        const ctx = document.getElementById('peakHoursChart');

        if (this.charts.peakHours) this.charts.peakHours.destroy();

        this.charts.peakHours = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.hours.map(h => `${h}:00`),
                datasets: [{
                    label: 'Activity Count',
                    data: data.activity,
                    backgroundColor: data.activity.map((val, idx) => 
                        idx === data.peak_hour ? '#ef4444' : '#3b82f6'
                    ),
                    borderRadius: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'x',
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: { beginAtZero: true },
                },
            },
        });
    }

    renderTopUsersTable(users) {
        const tbody = document.getElementById('topUsersTable');
        tbody.innerHTML = users.map(user => `
            <tr class="border-b border-gray-100 hover:bg-gray-50">
                <td class="py-3 px-4">
                    <div class="font-medium text-gray-900">${user.name}</div>
                    <div class="text-xs text-gray-500">${user.email}</div>
                </td>
                <td class="text-right py-3 px-4 text-gray-900">${user.sessions}</td>
                <td class="text-right py-3 px-4 text-gray-900">${user.total_duration}</td>
                <td class="text-right py-3 px-4 text-gray-900">${user.avg_duration.toFixed(1)}</td>
                <td class="text-right py-3 px-4 text-gray-500 text-sm">${new Date(user.last_login).toLocaleDateString()}</td>
            </tr>
        `).join('');
    }

    renderDeviceAnalytics(data) {
        // Devices
        document.getElementById('devicesList').innerHTML = Object.entries(data.devices)
            .slice(0, 5)
            .map(([device, count]) => `
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">${device}</span>
                    <span class="font-semibold text-gray-900">${count}</span>
                </div>
            `).join('');

        // Browsers
        document.getElementById('browsersList').innerHTML = Object.entries(data.browsers)
            .slice(0, 5)
            .map(([browser, count]) => `
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">${browser}</span>
                    <span class="font-semibold text-gray-900">${count}</span>
                </div>
            `).join('');

        // Platforms
        document.getElementById('platformsList').innerHTML = Object.entries(data.platforms)
            .slice(0, 5)
            .map(([platform, count]) => `
                <div class="flex justify-between items-center">
                    <span class="text-gray-700">${platform}</span>
                    <span class="font-semibold text-gray-900">${count}</span>
                </div>
            `).join('');
    }

    showLoading(show) {
        const indicator = document.getElementById('loadingIndicator');
        indicator.style.display = show ? 'flex' : 'none';
    }

    exportToPdf() {
        alert('PDF export feature coming soon. Charts can be captured using browser screenshot.');
    }

    getToken() {
        // Get CSRF token or API token from page
        const token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.content : '';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    new AdvancedAnalyticsDashboard();
});
</script>
@endsection
