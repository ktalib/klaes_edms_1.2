@extends('layouts.app')
@section('page-title')
{{ __('Document Upload') }}
@endsection

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
 
 <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
      
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .label-format-option.selected {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        .orientation-option.selected {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        /* Print styles */
        @media print {
            body * {
                visibility: hidden;
            }
            /* make the print section printable */
            #printSection, #printSection * {
                visibility: visible;
            }
            #printSection {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                display: block !important;
            }
            .label-container {
                page-break-inside: avoid;
            }
            .no-print {
                display: none !important;
                visibility: hidden !important;
            }
            /* One A4 page with small margins */
            @page {
                size: A4 landscape;
                margin: 8mm;
            }
            /* Ensure the print section actually shows and clamps to one page */
            #printSection {
                display: block !important;
                width: 297mm; /* page width for landscape */
                max-width: 297mm;
                overflow: hidden; /* hide overflow to keep one page */
            }
            /* The page frame (content gets scaled inside this) */
            .print-page {
                width: calc(297mm - 16mm); /* page width - 2*margins for landscape */
                height: calc(210mm - 16mm); /* page height - 2*margins for landscape */
                overflow: hidden;
                position: relative;
                margin: 0 auto;
            }
            .label-container canvas {
                width: 60px !important;
                height: 60px !important;
            }
            /* Specific styles for 30-in-1 template */
            .print-30-in-1 {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                grid-template-rows: repeat(10, 1fr);
                gap: 1mm;
                width: 100%;
                height: 100%;
            }
            .print-label {
                border: 0.5px solid #ccc;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 1mm;
                font-size: 10pt;
                text-align: center;
                box-sizing: border-box;
            }
            .print-label canvas {
                width: 60px !important;
                height: 50px !important;
                margin-bottom: 1mm;
            }
            .print-label p {
                margin: 0;
                line-height: 1.1;
            }
            .file-number {
                font-weight: bold;
            }
            /* Added styles for standard print layout */
            .print-standard {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 4mm;
                width: 100%;
                height: 100%;
            }
            .print-standard-label {
                border: 1px solid #ccc;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 4mm;
                font-size: 12pt;
                text-align: center;
                box-sizing: border-box;
                min-height: 80mm;
            }
            .print-standard-label canvas {
                width: 120px !important;
                height: 60px !important;
                margin-bottom: 2mm;
            }
            .print-standard-label p {
                margin: 2px 0;
                line-height: 1.2;
            }
        }
    </style>
<!-- Main Content -->
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <!-- Dashboard Content -->
    <div class="p-6">

 
  <div class="container mx-auto py-6 space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">
                    Print File Labels
                    @if(isset($showOnlyST) && $showOnlyST)
                        <span class="text-lg text-blue-600 font-medium">(Sectional Titling Files Only)</span>
                    @endif
                </h1>
                <p class="text-gray-600 mt-1">
                    Generate and print labels for physical files
                    @if(isset($showOnlyST) && $showOnlyST)
                        {{-- <br><span class="text-blue-600 text-sm font-medium">
                            ✓ Filtering for ST Primary and Sub Application files only
                        </span> --}}
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                <button
                    id="historyBtn"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    <i data-lucide="history" class="h-4 w-4"></i>
                </button>
                <button
                    id="resetBtn"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                </button>
                <button
                    id="printBtn"
                    class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    Print Labels
                </button>
            </div>
        </div>

         <!-- Stats Cards  -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg border p-6">
                <div class="pb-2">
                    <h3 class="text-sm font-medium text-gray-700">Available Files</h3>
                </div>
                <div class="text-2xl font-bold" id="availableFilesCount">{{ $availableFilesCount ?? 0 }}</div>
                <p class="text-xs text-gray-500 mt-1">
                    Files available for label printing
                </p>
            </div>
            <div class="bg-white rounded-lg border p-6">
                <div class="pb-2">
                    <h3 class="text-sm font-medium text-gray-700">Selected Files</h3>
                </div>
                <div class="text-2xl font-bold" id="selectedFilesCount">0</div>
                <p class="text-xs text-gray-500 mt-1">
                    Files selected for label printing
                </p>
            </div>
            <div class="bg-white rounded-lg border p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-semibold">Printer Status</h3>
                        <p class="text-sm text-gray-600">Label printer status</p>
                    </div>
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
                        >Online</span
                    >
                </div>
                <div class="text-2xl font-bold flex items-center mt-2">Ready</div>
            </div>
        </div>

         <!-- Print History (Hidden by default)  -->
        <div
            id="printHistory"
            class="bg-white rounded-lg border mb-6"
            style="display: none"
        >
            <div class="p-6 border-b">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-semibold">Print History</h3>
                        <p class="text-sm text-gray-600">
                            Recent label printing activity
                        </p>
                    </div>
                    <button
                        id="closeHistoryBtn"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="rounded-md border">
                    <div class="p-3 bg-gray-50 grid grid-cols-5 gap-4">
                        <div class="text-sm font-medium">ID</div>
                        <div class="text-sm font-medium">Date</div>
                        <div class="text-sm font-medium">Files</div>
                        <div class="text-sm font-medium">Template</div>
                        <div class="text-sm font-medium">User</div>
                    </div>
                    <div class="divide-y">
                        <div class="p-3 grid grid-cols-5 gap-4">
                            <div class="text-sm">PRINT-001</div>
                            <div class="text-sm">2023-06-15</div>
                            <div class="text-sm">5</div>
                            <div class="text-sm">Standard</div>
                            <div class="text-sm">Admin</div>
                        </div>
                        <div class="p-3 grid grid-cols-5 gap-4">
                            <div class="text-sm">PRINT-002</div>
                            <div class="text-sm">2023-06-14</div>
                            <div class="text-sm">3</div>
                            <div class="text-sm">Compact</div>
                            <div class="text-sm">Admin</div>
                        </div>
                        <div class="p-3 grid grid-cols-5 gap-4">
                            <div class="text-sm">PRINT-003</div>
                            <div class="text-sm">2023-06-12</div>
                            <div class="text-sm">10</div>
                            <div class="text-sm">QR Code</div>
                            <div class="text-sm">Supervisor</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

         <!-- Tabs  -->
        <div class="w-full">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button
                        class="tab-btn active border-b-2 border-blue-500 py-2 px-1 text-sm font-medium text-blue-600"
                        data-tab="files"
                    >
                        Select Files
                    </button>
                    <button
                        class="tab-btn border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        data-tab="generated"
                    >
                        Generated Batches
                    </button>
                    <button
                        class="tab-btn border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        data-tab="settings"
                    >
                        Label Settings
                    </button>
                    <button
                        class="tab-btn border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        data-tab="preview"
                    >
                        Preview & Print
                    </button>
                </nav>
            </div>

             <!-- Files Tab  -->
            <div id="files-tab" class="tab-content active mt-6">
                <div class="bg-white rounded-lg border">
                    <div class="p-6 border-b">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold">Select Files for Labels</h3>
                                <p class="text-sm text-gray-600">
                                    Choose files to generate and print labels
                                </p>
                            </div>
                            <div class="relative w-full md:w-64">
                                <i
                                    data-lucide="search"
                                    class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400"
                                ></i>
                                <input
                                    type="search"
                                    id="searchInput"
                                    placeholder="Search files..."
                                    class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                         Batch Mode Controls 
                        <div class="mb-4 flex items-center gap-4 flex-wrap">
                            <div class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="batchMode"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <label for="batchMode" class="text-sm font-medium"
                                    >Batch Mode</label
                                >
                            </div>

                            <div >
                            <div
                                id="batchControls"
                                class="flex items-center gap-2 flex-wrap hidden"
                                style="display: none"
                            >
                                <label class="text-sm whitespace-nowrap">Start:</label>
                                <input
                                    type="number"
                                    id="batchStart"
                                    min="1"
                                    class="w-20 border border-gray-300 rounded-md px-2 py-1 text-sm"
                                    value="1"
                                />
                                <label class="text-sm whitespace-nowrap">Count:</label>
                                <input
                                    type="number"
                                    id="batchCount"
                                    min="1"
                                    class="w-20 border border-gray-300 rounded-md px-2 py-1 text-sm"
                                    value="30"
                                />
                                <button
                                    id="generateBatchBtn"
                                    class="px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                                >
                                    Generate
                                </button>
                            </div>
                            </div>
                        </div>

                         <!-- File List  -->
                        <div id="fileList" class="rounded-md border">
                            <div class="p-3 bg-gray-50 flex justify-between items-center">
                                <div class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="selectAll"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <label for="selectAll" class="text-sm font-medium"
                                        >Select All</label
                                    >
                                    @if(isset($showOnlyST) && $showOnlyST)
                                        <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">
                                            ST Files Only
                                        </span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500" id="selectionStatus"
                                        >0 of 30 selected</span
                                    >
                                </div>
                            </div>
                            <div class="divide-y max-h-96 overflow-y-auto" id="fileListContent">
                               
                            </div>
                        </div>
                    </div>
                    <div class="p-6 border-t flex justify-between">
                        <button
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Back to Indexing
                        </button>
                        <div class="flex gap-2">
                            <button
                                id="duplicateBtn"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 gap-2 inline-flex items-center"
                            >
                                <i data-lucide="copy" class="h-4 w-4"></i>
                                Duplicate
                            </button>
                            <button
                                id="continueToSettingsBtn"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 gap-2 inline-flex items-center"
                            >
                                <i data-lucide="settings" class="h-4 w-4"></i>
                                Continue to Label Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Generated Batches Tab -->
            <div id="generated-tab" class="tab-content mt-6">
                <div class="bg-white rounded-lg border">
                    <div class="p-6 border-b">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold">Generated Label Batches</h3>
                                <p class="text-sm text-gray-600">
                                    View and manage generated label batches
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <select id="statusFilter" class="border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <option value="">All Status</option>
                                    <option value="generated">Generated</option>
                                    <option value="printed">Printed</option>
                                    <option value="completed">Completed</option>
                                </select>
                                <button
                                    id="refreshBatchesBtn"
                                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <!-- Batch Statistics -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <div class="text-sm font-medium text-blue-700">Total Batches</div>
                                <div class="text-2xl font-bold text-blue-900" id="totalBatchesCount">0</div>
                            </div>
                            <div class="bg-green-50 rounded-lg p-4">
                                <div class="text-sm font-medium text-green-700">Generated</div>
                                <div class="text-2xl font-bold text-green-900" id="generatedBatchesCount">0</div>
                            </div>
                            <div class="bg-yellow-50 rounded-lg p-4">
                                <div class="text-sm font-medium text-yellow-700">Printed</div>
                                <div class="text-2xl font-bold text-yellow-900" id="printedBatchesCount">0</div>
                            </div>
                            <div class="bg-purple-50 rounded-lg p-4">
                                <div class="text-sm font-medium text-purple-700">Completed</div>
                                <div class="text-2xl font-bold text-purple-900" id="completedBatchesCount">0</div>
                            </div>
                        </div>

                        <!-- Batch List -->
                        <div id="batchList" class="rounded-md border">
                            <div class="p-3 bg-gray-50">
                                <div class="grid grid-cols-7 gap-4 text-sm font-medium">
                                    <div>Batch Number</div>
                                    <div>Created Date</div>
                                    <div>Files Count</div>
                                    <div>Format</div>
                                    <div>Status</div>
                                    <div>Created By</div>
                                    <div>Actions</div>
                                </div>
                            </div>
                            <div class="divide-y max-h-96 overflow-y-auto" id="batchListContent">
                                <div class="p-8 text-center text-gray-500">
                                    <div class="mb-2">
                                        <i data-lucide="package" class="h-8 w-8 mx-auto text-gray-400"></i>
                                    </div>
                                    <p>Loading batches...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div id="batchPagination" class="mt-4 flex items-center justify-between">
                            <div class="text-sm text-gray-500" id="batchPaginationInfo">
                                Showing 0 to 0 of 0 results
                            </div>
                            <div class="flex items-center gap-2" id="batchPaginationControls">
                                <!-- Pagination controls will be inserted here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

             <!-- Settings Tab  -->
            <div id="settings-tab" class="tab-content mt-6">
                <div class="bg-white rounded-lg border">
                    <div class="p-6 border-b">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold">Label Settings</h3>
                                <p class="text-sm text-gray-600">
                                    Configure label printing options
                                </p>
                            </div>
                            <button
                                id="advancedToggle"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Show Advanced
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label
                                        for="labelTemplate"
                                        class="block text-sm font-medium text-gray-700"
                                        >Label Template</label
                                    >
                                    <select
                                        id="labelTemplate"
                                        class="mt-1.5 w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="30-in-1">
                                            Labels with File No and Shelf/rack
                                        </option>
                                         <!-- CHANGE> Disabled all other label template options -->
                                        <!-- <option value="standard" disabled>
                                            Standard - Standard label with barcode and text
                                        </option>
                                        <option value="compact" disabled>
                                            Compact - Compact label with minimal information
                                        </option>
                                        <option value="detailed" disabled>
                                            Detailed - Detailed label with all file information
                                        </option>
                                        <option value="qrcode" disabled>
                                            QR Code - Label with QR code for scanning
                                        </option>
                                        <option value="custom" disabled>
                                            Custom - Custom label template
                                        </option> -->
                                    </select>
                                </div>
                                <div>
                                    <label
                                        for="labelSize"
                                        class="block text-sm font-medium text-gray-700"
                                        >Label Size</label
                                    >
                                    <select
                                        id="labelSize"
                                        class="mt-1.5 w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="30-in-1">
                                            A4 Template (1" x 0.8")
                                        </option>
                                         <!-- CHANGE> Disabled all other label size options -->
                                        <!-- <option value="small" disabled>Small (2.5" x 1")</option>
                                        <option value="standard" disabled>Standard (3" x 2")</option>
                                        <option value="large" disabled>Large (4" x 3")</option>
                                        <option value="custom" disabled>Custom Size</option> -->
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5"
                                        >Label Format</label
                                    >
                                    <div class="grid grid-cols-2 gap-4">
                                        <!-- Barcode option: disabled, faded, not selectable -->
                                        <div
                                            class="label-format-option border rounded-md p-3 flex flex-col items-center cursor-not-allowed opacity-50 pointer-events-none"
                                            data-format="barcode"
                                        >
                                            <i data-lucide="bar-chart-4" class="h-8 w-8 mb-2"></i>
                                            <span class="text-sm font-medium">Barcode</span>
                                        </div>
                                        <!-- QR code option: enabled, selected by default -->
                                        <div
                                            class="label-format-option selected border rounded-md p-3 flex flex-col items-center cursor-pointer"
                                            data-format="qrcode"
                                        >
                                            <i data-lucide="qr-code" class="h-8 w-8 mb-2"></i>
                                            <span class="text-sm font-medium">QR Code</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5"
                                        >Orientation</label
                                    >
                                    <div class="grid grid-cols-2 gap-4">
                                        <div
                                            class="orientation-option border rounded-md p-3 flex items-center cursor-pointer"
                                            data-orientation="portrait"
                                        >
                                            <input
                                                type="radio"
                                                name="orientation"
                                                value="portrait"
                                                class="mr-2"
                                            />
                                            <label class="cursor-pointer">Portrait</label>
                                        </div>
                                        <div
                                            class="orientation-option selected border rounded-md p-3 flex items-center cursor-pointer"
                                            data-orientation="landscape"
                                        >
                                            <input
                                                type="radio"
                                                name="orientation"
                                                value="landscape"
                                                checked
                                                class="mr-2"
                                            />
                                            <label class="cursor-pointer">Landscape</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="advancedOptions" class="space-y-4" style="display: none">
                                <div>
                                    <label
                                        for="margin"
                                        class="block text-sm font-medium text-gray-700"
                                        >Margin (inches)</label
                                    >
                                    <input
                                        type="number"
                                        id="margin"
                                        value="0.25"
                                        step="0.01"
                                        class="mt-1.5 w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                                <div>
                                    <label for="dpi" class="block text-sm font-medium text-gray-700"
                                        >DPI</label
                                    >
                                    <input
                                        type="number"
                                        id="dpi"
                                        value="300"
                                        class="mt-1.5 w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label
                                    for="copies"
                                    class="block text-sm font-medium text-gray-700"
                                    >Number of Copies</label
                                >
                                <input
                                    type="number"
                                    id="copies"
                                    min="1"
                                    value="1"
                                    class="mt-1.5 w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="p-6 border-t flex justify-between">
                        <button
                            id="backToFilesBtn"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Back to File Selection
                        </button>
                        <button
                            id="continueToPreviewBtn"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 gap-2 inline-flex items-center"
                        >
                            <i data-lucide="download" class="h-4 w-4"></i>
                            Continue to Preview
                        </button>
                    </div>
                </div>
            </div>

             <!-- Preview Tab  -->
            <div id="preview-tab" class="tab-content mt-6">
                <div class="bg-white rounded-lg border">
                    <div class="p-6 border-b">
                        <h3 class="text-lg font-semibold">Preview and Print</h3>
                        <p class="text-sm text-gray-600">
                            Preview labels before printing
                        </p>
                    </div>
                    <div class="p-6">
                        <div class="mb-4">
                            <p id="previewDescription">
                                You have selected 0 files to print labels for. Please review the
                                label previews below before printing.
                            </p>
                        </div>
                        <div class="space-y-6">
                             Preview Area 
                            <div
                                id="previewArea"
                                class="border rounded-md p-4 bg-white min-h-96"
                            >
                                <h3 class="text-sm font-medium mb-4 text-center">
                                    Label Preview
                                </h3>
                                 Added flex centering container for proper alignment 
                                <div class="flex justify-center items-start">
                                    <div
                                        id="previewContent"
                                        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
                                    >
                                         Preview content will be generated by JavaScript 
                                    </div>
                                </div>
                            </div>

                             Print Summary 
                            <div class="border rounded-md p-4">
                                <h3 class="text-sm font-medium mb-2">Print Summary</h3>
                                <div class="space-y-2" id="printSummary">
                                     Summary will be populated by JavaScript 
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2 mt-4">
                            <button
                                id="exportPdfBtn"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 gap-2 inline-flex items-center"
                            >
                                <i data-lucide="upload" class="h-4 w-4"></i>
                                Export as PDF
                            </button>
                            <button
                                id="saveTemplateBtn"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 gap-2 inline-flex items-center"
                            >
                                <i data-lucide="save" class="h-4 w-4"></i>
                                Save as Template
                            </button>
                            <button
                                id="importTemplateBtn"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 gap-2 inline-flex items-center"
                            >
                                <i data-lucide="file-text" class="h-4 w-4"></i>
                                Import Template
                            </button>
                        </div>
                    </div>
                    <div class="p-6 border-t flex justify-between">
                        <button
                            id="backToSettingsBtn"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Back to Settings
                        </button>
                        <button
                            id="finalPrintBtn"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700"
                        >
                            Print Labels
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

     <!-- Hidden print section  -->
    <div id="printSection" style="display: none"></div>

    </div>
        <!-- Footer -->
        @include('admin.footer')
        <!-- @include('printlabel.assets.js') -->
    </div>
    @include('printlabel.assets.js')
    @endsection