@extends('layouts.app')
@section('page-title')
    {{ __('Legal Search Reports') }}
@endsection

@section('content')
 <style>
    /* Custom styles */
  

    /* cards styles */
    .cards {
        background-color: white;
        border-radius: 0.5rem;
        border: 1px solid var(--border);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* Button styles */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.375rem;
        font-weight: 500;
        font-size: 0.875rem;
        line-height: 1.25rem;
        padding: 0.5rem 1rem;
        transition: all 0.2s;
        cursor: pointer;
        border: none;
    }

    .btn-primary {
        background-color: var(--primary);
        color: var(--primary-foreground);
    }

    .btn-primary:hover {
        background-color: #2563eb;
    }

    .btn-outline {
        background-color: transparent;
        border: 1px solid var(--border);
        color: #374151;
    }

    .btn-outline:hover {
        background-color: var(--muted);
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }

    .btn-icon {
        padding: 0.5rem;
        width: 2.5rem;
        height: 2.5rem;
    }

    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Input styles */
    .input {
        display: block;
        width: 100%;
        border-radius: 0.375rem;
        border: 1px solid var(--border);
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        line-height: 1.25rem;
        background-color: white;
    }

    .input:focus {
        outline: none;
        border-color: var(--ring);
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
    }

    /* Select styles */
    .select {
        display: block;
        width: 100%;
        border-radius: 0.375rem;
        border: 1px solid var(--border);
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        line-height: 1.25rem;
        background-color: white;
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
        padding-right: 2.5rem;
    }

    .select:focus {
        outline: none;
        border-color: var(--ring);
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
    }

    /* Badge styles */
    .badge {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1;
        padding: 0.25rem 0.5rem;
        white-space: nowrap;
    }

    .badge-default {
        background-color: var(--primary);
        color: var(--primary-foreground);
    }

    .badge-outline {
        background-color: transparent;
        border: 1px solid var(--border);
        color: #374151;
    }

    .badge-secondary {
        background-color: var(--secondary);
        color: var(--secondary-foreground);
    }

    .badge-destructive {
        background-color: var(--destructive);
        color: white;
    }

    /* Table styles */
    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th,
    .table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid var(--border);
        vertical-align: top;
    }

    .table th {
        font-weight: 500;
        color: var(--muted-foreground);
        font-size: 0.875rem;
        background-color: #f9fafb;
    }

    .table tbody tr:hover {
        background-color: var(--muted);
    }

    /* Tab styles */
    .tabs-list {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        background-color: var(--muted);
        border-radius: 0.375rem;
        padding: 0.25rem;
    }

    .tab-trigger {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 0.25rem;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        background-color: transparent;
        color: var(--muted-foreground);
        border: none;
    }

    .tab-trigger.active {
        background-color: white;
        color: var(--primary);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .tab-contents {
        display: none;
    }

    .tab-contents.active {
        display: block;
    }

    /* Popover styles */
    .popover {
        position: relative;
        display: inline-block;
    }

    .popover-content {
        position: absolute;
        top: 100%;
        left: 0;
        z-index: 50;
        background-color: white;
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        padding: 1rem;
        width: 20rem;
        margin-top: 0.25rem;
        display: none;
    }

    .popover-content.show {
        display: block;
    }

    /* Animation classes */
    .animate-fade-in {
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

   
    /* Responsive design */
    @media (min-width: 768px) {
        .md\\:flex-row { flex-direction: row; }
        .md\\:items-center { align-items: center; }
        .md\\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .md\\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .md\\:gap-6 { gap: 1.5rem; }
        .md\\:col-span-2 { grid-column: span 2 / span 2; }
        .md\\:h-350 { height: 350px; }
    }

    @media (min-width: 1024px) {
        .lg\\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .lg\\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .lg\\:col-span-2 { grid-column: span 2 / span 2; }
        .lg\\:h-400 { height: 400px; }
    }

    /* Chart container styles */
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }

    @media (min-width: 768px) {
        .chart-container {
            height: 350px;
        }
    }

    @media (min-width: 1024px) {
        .chart-container {
            height: 400px;
        }
    }
</style>
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
            <script src="https://cdn.tailwindcss.com"></script>
<!-- Lucide Icons -->
 <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

 
<div  >
    <div  >
        <div >
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h2 class="text-2xl font-bold">Legal Search Reports</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Filters Popover -->
                    <div class="popover">
                        <button id="filters-btn" class="btn btn-outline gap-2">
                            <i data-lucide="filter" class="h-4 w-4"></i>
                            Filters
                            <i data-lucide="chevron-down" class="h-4 w-4"></i>
                        </button>
                        <div id="filters-popover" class="popover-content">
                            <div class="space-y-4">
                                <h3 class="font-medium">Filter Reports</h3>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Date Range</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input id="date-from" type="date" class="input" value="2023-01-01">
                                        <input id="date-to" type="date" class="input" value="2023-12-31">
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Report Period</label>
                                    <select id="filter-period" class="select">
                                        <option value="day">Daily</option>
                                        <option value="week">Weekly</option>
                                        <option value="month" selected>Monthly</option>
                                        <option value="quarter">Quarterly</option>
                                        <option value="year">Yearly</option>
                                        <option value="custom">Custom Range</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Payment Status</label>
                                    <select id="filter-payment" class="select">
                                        <option value="all" selected>All Statuses</option>
                                        <option value="paid">Paid</option>
                                        <option value="pending">Pending</option>
                                        <option value="refunded">Refunded</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Search Type</label>
                                    <select id="filter-search-type" class="select">
                                        <option value="all" selected>All Types</option>
                                        <option value="commercial">Commercial</option>
                                        <option value="official">Official</option>
                                        <option value="online">Online</option>
                                    </select>
                                </div>
                                <button id="apply-filters-btn" class="btn btn-primary w-full">Apply Filters</button>
                            </div>
                        </div>
                    </div>
                    
                    <select id="report-period" class="select" style="width: 180px;">
                        <option value="day">Daily</option>
                        <option value="week">Weekly</option>
                        <option value="month" selected>Monthly</option>
                        <option value="quarter">Quarterly</option>
                        <option value="year">Yearly</option>
                        <option value="custom">Custom Range</option>
                    </select>
                    
                    <div class="flex items-center gap-1">
                        <button id="print-btn" class="btn btn-outline btn-icon">
                            <i data-lucide="printer" class="h-4 w-4"></i>
                        </button>
                        <button id="export-pdf-btn" class="btn btn-outline btn-icon">
                            <i data-lucide="file-text" class="h-4 w-4"></i>
                        </button>
                        <button id="export-excel-btn" class="btn btn-outline btn-icon">
                            <i data-lucide="download" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="w-full">
                <div class="tabs-list">
                    <button class="tab-trigger active" data-tab="table">
                        <i data-lucide="table" class="h-4 w-4"></i>
                        Detailed View
                    </button>
                    <button class="tab-trigger" data-tab="summary">
                        <i data-lucide="file-text" class="h-4 w-4"></i>
                        Summary View
                    </button>
                    <button class="tab-trigger" data-tab="charts">
                        <i data-lucide="bar-chart-3" class="h-4 w-4"></i>
                        Charts View
                    </button>
                </div>

                <!-- Table Tab Content -->
                <div id="table-tab" class="tab-contents active mt-6 w-full">
                    <div class="cards w-full">
                        <div class="p-6 border-b">
                            <h3 class="text-lg font-semibold">Legal Search Transactions</h3>
                            <p class="text-sm text-muted-foreground">Detailed view of all legal search transactions for the selected period.</p>
                        </div>
                        <div class="p-6 w-full overflow-x-auto">
                            <div class="rounded-md border min-w-full">
                                <table class="table min-w-full">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">S/N</th>
                                            <th style="width: 100px;">Date</th>
                                            <th style="width: 150px;">Search Parameter</th>
                                            <th style="width: 200px;">Search Value</th>
                                            <th style="width: 100px;">Result</th>
                                            <th style="width: 120px;">LGA</th>
                                            <th style="width: 120px;">Receipt No.</th>
                                            <th style="width: 120px;">Staff</th>
                                        </tr>
                                    </thead>
                                    <tbody id="transactions-table-body">
                                        <!-- Data will be populated by JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Tab Content -->
                <div id="summary-tab" class="tab-contents mt-6 w-full overflow-x-auto">
                    <!-- Summary cardss -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 mb-6">
                        <div class="cards w-full">
                            <div class="p-6 pb-2">
                                <h4 class="text-sm font-medium">Legal Searches Conducted</h4>
                            </div>
                            <div class="p-6">
                                <div id="total-searches" class="text-2xl font-bold">0</div>
                                <p class="text-xs text-muted-foreground mt-1">Total search requests processed</p>
                            </div>
                        </div>
                        <div class="cards w-full">
                            <div class="p-6 pb-2">
                                <h4 class="text-sm font-medium">Search Revenue</h4>
                            </div>
                            <div class="p-6">
                                <div id="total-revenue" class="text-2xl font-bold">₦0</div>
                                <p class="text-xs text-muted-foreground mt-1">From completed searches</p>
                            </div>
                        </div>
                        <div class="cards w-full">
                            <div class="p-6 pb-2">
                                <h4 class="text-sm font-medium">Search Status Distribution</h4>
                            </div>
                            <div class="p-6">
                                <div id="status-distribution" class="flex flex-wrap items-center gap-4">
                                    <!-- Status badges will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Results and Efficiency cardss -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 w-full">
                        <div class="cards md:col-span-2 lg:col-span-2">
                            <div class="p-6 border-b">
                                <h4 class="text-lg font-semibold">Search Results</h4>
                                <p class="text-sm text-muted-foreground">Distribution of search results</p>
                            </div>
                            <div class="p-6 w-full max-w-full">
                                <div id="results-distribution" class="flex flex-wrap items-center justify-center gap-8 py-4">
                                    <!-- Results will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>

                        <div class="cards md:col-span-2 lg:col-span-2">
                            <div class="p-6 border-b">
                                <h4 class="text-lg font-semibold">Search Efficiency</h4>
                                <p class="text-sm text-muted-foreground">Key performance metrics</p>
                            </div>
                            <div class="p-6 w-full max-w-full">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="text-center">
                                        <div id="avg-revenue" class="text-3xl font-bold mb-1">₦0</div>
                                        <div class="text-sm font-medium">Average Revenue</div>
                                        <div class="text-xs text-muted-foreground mt-1">Per search</div>
                                    </div>
                                    <div class="text-center">
                                        <div id="success-rate" class="text-3xl font-bold mb-1">0%</div>
                                        <div class="text-sm font-medium">Success Rate</div>
                                        <div class="text-xs text-muted-foreground mt-1">Records found</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Parameter and LGA Tables -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
                        <div class="cards w-full">
                            <div class="p-6 border-b">
                                <h4 class="text-lg font-semibold">Search Parameters</h4>
                                <p class="text-sm text-muted-foreground">Distribution of search parameter types</p>
                            </div>
                            <div class="p-6 w-full max-w-full">
                                <div class="overflow-x-auto w-full">
                                    <table class="table w-full">
                                        <thead>
                                            <tr>
                                                <th>Parameter</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody id="parameters-table-body">
                                            <!-- Data will be populated by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="cards w-full">
                            <div class="p-6 border-b">
                                <h4 class="text-lg font-semibold">Searches by LGA</h4>
                                <p class="text-sm text-muted-foreground">Distribution of searches by Local Government Area</p>
                            </div>
                            <div class="p-6 w-full max-w-full">
                                <div class="overflow-x-auto w-full">
                                    <table class="table w-full">
                                        <thead>
                                            <tr>
                                                <th>LGA</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody id="lga-table-body">
                                            <!-- Data will be populated by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Tab Content -->
                <div id="charts-tab" class="tab-contents mt-6 w-full">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
                        <div class="cards w-full">
                            <div class="p-6 border-b">
                                <h4 class="text-lg font-semibold">Searches by LGA</h4>
                                <p class="text-sm text-muted-foreground">Bar chart showing distribution of searches by Local Government Area</p>
                            </div>
                            <div class="p-6">
                                <div class="chart-container">
                                    <canvas id="lga-chart"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="cards w-full">
                            <div class="p-6 border-b">
                                <h4 class="text-lg font-semibold">Search Parameters Distribution</h4>
                                <p class="text-sm text-muted-foreground">Pie chart showing distribution of search parameter types</p>
                            </div>
                            <div class="p-6">
                                <div class="chart-container">
                                    <canvas id="parameters-chart"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="cards w-full lg:col-span-2">
                            <div class="p-6 border-b">
                                <h4 class="text-lg font-semibold">Monthly Trends</h4>
                                <p class="text-sm text-muted-foreground">Line chart showing monthly search volume and revenue trends</p>
                            </div>
                            <div class="p-6">
                                <div class="chart-container">
                                    <canvas id="trends-chart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Sample data
    const sampleReportData = [
        {
            id: "1",
            date: "2023-01-15",
            searchType: "Commercial",
            searchParameter: "File Number",
            searchValue: "CON-RES-2018-242",
            result: "Found",
            amount: "5000",
            paymentStatus: "Paid",
            paymentMethod: "Bank Transfer",
            receiptNumber: "RCP001",
            staff: "Musa Ali",
            lga: "Kano Municipal"
        },
        {
            id: "2",
            date: "2023-01-16",
            searchType: "Official",
            searchParameter: "Plot Number",
            searchValue: "GP No. 1067/1",
            result: "Found",
            amount: "3000",
            paymentStatus: "Paid",
            paymentMethod: "Cash",
            receiptNumber: "RCP002",
            staff: "Jane Smith",
            lga: "Fagge"
        },
        {
            id: "3",
            date: "2023-01-17",
            searchType: "Online",
            searchParameter: "Owner Name",
            searchValue: "Alhaji Musa Ibrahim",
            result: "Not Found",
            amount: "2500",
            paymentStatus: "Pending",
            paymentMethod: "Online",
            receiptNumber: "RCP003",
            staff: "Mike Johnson",
            lga: "Gwale"
        },
        {
            id: "4",
            date: "2023-01-18",
            searchType: "Commercial",
            searchParameter: "KANGIS Number",
            searchValue: "KNML 08146",
            result: "Found",
            amount: "4500",
            paymentStatus: "Paid",
            paymentMethod: "POS",
            receiptNumber: "RCP004",
            staff: "Sarah Wilson",
            lga: "Tarauni"
        },
        {
            id: "5",
            date: "2023-01-19",
            searchType: "Official",
            searchParameter: "Plan Number",
            searchValue: "LKN/RES/2021/3006",
            result: "Found",
            amount: "3500",
            paymentStatus: "Refunded",
            paymentMethod: "Bank Transfer",
            receiptNumber: "RCP005",
            staff: "David Brown",
            lga: "Nassarawa"
        }
    ];

    const sampleMonthlyData = [
        { month: "Jan", searches: 45, revenue: 225000 },
        { month: "Feb", searches: 52, revenue: 260000 },
        { month: "Mar", searches: 38, revenue: 190000 },
        { month: "Apr", searches: 61, revenue: 305000 },
        { month: "May", searches: 49, revenue: 245000 },
        { month: "Jun", searches: 55, revenue: 275000 },
        { month: "Jul", searches: 43, revenue: 215000 },
        { month: "Aug", searches: 58, revenue: 290000 },
        { month: "Sep", searches: 47, revenue: 235000 },
        { month: "Oct", searches: 53, revenue: 265000 },
        { month: "Nov", searches: 41, revenue: 205000 },
        { month: "Dec", searches: 48, revenue: 240000 }
    ];

    // State management
    let currentTab = 'table';
    let reportData = sampleReportData;
    let monthlyData = sampleMonthlyData;
    let charts = {};

    // DOM elements
    const elements = {
        filtersBtn: document.getElementById('filters-btn'),
        filtersPopover: document.getElementById('filters-popover'),
        printBtn: document.getElementById('print-btn'),
        exportPdfBtn: document.getElementById('export-pdf-btn'),
        exportExcelBtn: document.getElementById('export-excel-btn'),
        tabTriggers: document.querySelectorAll('.tab-trigger'),
        tabContents: document.querySelectorAll('.tab-contents'),
        transactionsTableBody: document.getElementById('transactions-table-body'),
        totalSearches: document.getElementById('total-searches'),
        totalRevenue: document.getElementById('total-revenue'),
        statusDistribution: document.getElementById('status-distribution'),
        resultsDistribution: document.getElementById('results-distribution'),
        avgRevenue: document.getElementById('avg-revenue'),
        successRate: document.getElementById('success-rate'),
        parametersTableBody: document.getElementById('parameters-table-body'),
        lgaTableBody: document.getElementById('lga-table-body')
    };

    // Helper functions
    function calculateStatistics(data) {
        const totalSearches = data.length;
        const totalRevenue = data.reduce((sum, item) => {
            if (item.paymentStatus === "Paid") {
                return sum + parseInt(item.amount);
            }
            return sum;
        }, 0);

        const paymentStatusCounts = data.reduce((counts, item) => {
            counts[item.paymentStatus] = (counts[item.paymentStatus] || 0) + 1;
            return counts;
        }, {});

        const searchParameterCounts = data.reduce((counts, item) => {
            counts[item.searchParameter] = (counts[item.searchParameter] || 0) + 1;
            return counts;
        }, {});

        const lgaCounts = data.reduce((counts, item) => {
            counts[item.lga] = (counts[item.lga] || 0) + 1;
            return counts;
        }, {});

        const resultCounts = data.reduce((counts, item) => {
            counts[item.result] = (counts[item.result] || 0) + 1;
            return counts;
        }, {});

        return {
            totalSearches,
            totalRevenue,
            paymentStatusCounts,
            searchParameterCounts,
            lgaCounts,
            resultCounts
        };
    }

    function renderTransactionsTable(data) {
        elements.transactionsTableBody.innerHTML = '';
        
        data.forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="font-medium">${index + 1}</td>
                <td>${item.date}</td>
                <td>${item.searchParameter}</td>
                <td class="max-w-200 truncate">${item.searchValue}</td>
                <td>
                    <span class="badge ${item.result === 'Found' ? 'badge-outline' : 'badge-secondary'}">${item.result}</span>
                </td>
                <td>${item.lga}</td>
                <td>${item.receiptNumber}</td>
                <td>${item.staff}</td>
            `;
            elements.transactionsTableBody.appendChild(row);
        });
    }

    function renderSummaryView(stats) {
        // Update summary cardss
        elements.totalSearches.textContent = stats.totalSearches;
        elements.totalRevenue.textContent = `₦${stats.totalRevenue.toLocaleString()}`;

        // Update status distribution
        elements.statusDistribution.innerHTML = '';
        Object.entries(stats.paymentStatusCounts).forEach(([status, count]) => {
            const statusDiv = document.createElement('div');
            statusDiv.className = 'flex items-center gap-1';
            statusDiv.innerHTML = `
                <span class="badge ${status === 'Paid' ? 'badge-default' : status === 'Pending' ? 'badge-secondary' : 'badge-destructive'} h-2 w-2 rounded-full p-0"></span>
                <span class="text-sm">${status}: ${count}</span>
            `;
            elements.statusDistribution.appendChild(statusDiv);
        });

        // Update results distribution
        elements.resultsDistribution.innerHTML = '';
        Object.entries(stats.resultCounts).forEach(([result, count]) => {
            const resultDiv = document.createElement('div');
            resultDiv.className = 'text-center';
            resultDiv.innerHTML = `
                <div class="text-3xl font-bold mb-1">${count}</div>
                <span class="badge ${result === 'Found' ? 'badge-outline' : 'badge-secondary'} px-3 py-1">${result}</span>
                <div class="text-xs text-muted-foreground mt-1">
                    ${((count / stats.totalSearches) * 100).toFixed(1)}% of searches
                </div>
            `;
            elements.resultsDistribution.appendChild(resultDiv);
        });

        // Update efficiency metrics
        const avgRevenue = stats.totalRevenue / stats.totalSearches;
        const successRate = (stats.resultCounts["Found"] / stats.totalSearches) * 100;
        
        elements.avgRevenue.textContent = `₦${avgRevenue.toLocaleString()}`;
        elements.successRate.textContent = `${successRate.toFixed(1)}%`;

        // Update parameters table
        elements.parametersTableBody.innerHTML = '';
        Object.entries(stats.searchParameterCounts).forEach(([param, count]) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${param}</td>
                <td>${count}</td>
                <td>${((count / stats.totalSearches) * 100).toFixed(1)}%</td>
            `;
            elements.parametersTableBody.appendChild(row);
        });

        // Update LGA table
        elements.lgaTableBody.innerHTML = '';
        Object.entries(stats.lgaCounts).forEach(([lga, count]) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${lga}</td>
                <td>${count}</td>
                <td>${((count / stats.totalSearches) * 100).toFixed(1)}%</td>
            `;
            elements.lgaTableBody.appendChild(row);
        });
    }

    function createCharts(stats) {
        // Destroy existing charts
        Object.values(charts).forEach(chart => {
            if (chart) chart.destroy();
        });

        // LGA Bar Chart
        const lgaCtx = document.getElementById('lga-chart').getContext('2d');
        charts.lgaChart = new Chart(lgaCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(stats.lgaCounts),
                datasets: [{
                    label: 'Number of Searches',
                    data: Object.values(stats.lgaCounts),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Searches by LGA'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Parameters Pie Chart
        const parametersCtx = document.getElementById('parameters-chart').getContext('2d');
        charts.parametersChart = new Chart(parametersCtx, {
            type: 'pie',
            data: {
                labels: Object.keys(stats.searchParameterCounts),
                datasets: [{
                    data: Object.values(stats.searchParameterCounts),
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 205, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Search Parameters Distribution'
                    }
                }
            }
        });

        // Monthly Trends Line Chart
        const trendsCtx = document.getElementById('trends-chart').getContext('2d');
        charts.trendsChart = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(d => d.month),
                datasets: [{
                    label: 'Searches',
                    data: monthlyData.map(d => d.searches),
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y'
                }, {
                    label: 'Revenue (₦\'000)',
                    data: monthlyData.map(d => d.revenue / 1000),
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Searches and Revenue Trends'
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Number of Searches'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenue (₦\'000)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    }

    function switchTab(tabName) {
        // Update tab triggers
        elements.tabTriggers.forEach(trigger => {
            trigger.classList.remove('active');
            if (trigger.dataset.tab === tabName) {
                trigger.classList.add('active');
            }
        });

        // Update tab contents
        elements.tabContents.forEach(content => {
            content.classList.remove('active');
            if (content.id === `${tabName}-tab`) {
                content.classList.add('active');
            }
        });

        currentTab = tabName;

        // Create charts when charts tab is activated
        if (tabName === 'charts') {
            setTimeout(() => {
                const stats = calculateStatistics(reportData);
                createCharts(stats);
            }, 100);
        }
    }

    function toggleFiltersPopover() {
        elements.filtersPopover.classList.toggle('show');
    }

    function handlePrint() {
        window.print();
    }

    function handleExportPDF() {
        alert('Exporting to PDF... This would generate a PDF in a real application.');
    }

    function handleExportExcel() {
        alert('Exporting to Excel... This would generate an Excel file in a real application.');
    }

    function renderData() {
        const stats = calculateStatistics(reportData);
        
        renderTransactionsTable(reportData);
        renderSummaryView(stats);
        
        if (currentTab === 'charts') {
            createCharts(stats);
        }
    }

    // Event listeners
    elements.filtersBtn.addEventListener('click', toggleFiltersPopover);
    elements.printBtn.addEventListener('click', handlePrint);
    elements.exportPdfBtn.addEventListener('click', handleExportPDF);
    elements.exportExcelBtn.addEventListener('click', handleExportExcel);

    elements.tabTriggers.forEach(trigger => {
        trigger.addEventListener('click', () => {
            switchTab(trigger.dataset.tab);
        });
    });

    // Close popover when clicking outside
    document.addEventListener('click', (e) => {
        if (!elements.filtersBtn.contains(e.target) && !elements.filtersPopover.contains(e.target)) {
            elements.filtersPopover.classList.remove('show');
        }
    });

    // Initialize the page
    function init() {
        renderData();
        lucide.createIcons();
    }

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', init);
</script>



        </div>
        <!-- Footer -->
        @include('admin.footer')
    </div>
    
    
@endsection
