<!-- Export Preview Modal -->
<div id="exportPreviewModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeExportModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full border border-gray-200">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-lg">
                        <i class="fas fa-file-export text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white" id="modal-title">Export Instruments Preview</h3>
                        <p class="text-green-100 text-sm opacity-90">Review data before downloading CSV</p>
                    </div>
                </div>
                <button type="button" onclick="closeExportModal()" class="text-white hover:text-green-100 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="bg-white px-6 py-6">
                <!-- Filter Summary -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200 flex flex-wrap gap-6 items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Type:</span>
                        <span id="previewFilterType" class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-bold border border-green-200">All Types</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Volume:</span>
                        <span id="previewFilterVolume" class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-bold border border-blue-200">All</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <span class="text-sm text-gray-600 font-medium">Total Records:</span>
                        <span id="previewRecordCount" class="text-lg font-bold text-gray-900 px-2 py-0.5 bg-gray-200 rounded">0</span>
                    </div>
                </div>

                <!-- Preview Table Wrapper -->
                <div class="overflow-hidden border border-gray-200 rounded-xl shadow-sm">
                    <div class="overflow-x-auto max-h-[500px]">
                        <table class="min-w-full divide-y divide-gray-200" id="exportPreviewTable">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">SN</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Op Serial No</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Registration Particulars</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Allottee</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Deed Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50">Location</th>
                                </tr>
                            </thead>
 
                            <tbody class="bg-white divide-y divide-gray-200" id="exportPreviewBody">
                                <!-- Data will be loaded here via JS -->
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500 italic">
                                        <div class="flex flex-col items-center gap-2">
                                            <i class="fas fa-spinner fa-spin text-3xl text-green-500"></i>
                                            <span>Fetching data for preview...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-3 border-t border-gray-200">
                <button type="button" id="confirmDownloadPdfBtn" onclick="downloadExportPdf()" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-red-600 text-base font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:w-auto sm:text-sm items-center gap-2 transition-all">
                    <i class="fas fa-file-pdf"></i>
                    <span>Download PDF</span>
                </button>
                <button type="button" id="confirmDownloadBtn" onclick="downloadExportCsv()" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-green-600 text-base font-bold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:w-auto sm:text-sm items-center gap-2 transition-all">
                    <i class="fas fa-file-csv"></i>
                    <span>Download CSV</span>
                </button>
                <button type="button" onclick="closeExportModal()" class="w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-base font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 sm:w-auto sm:text-sm transition-all">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #exportPreviewTable thead th {
        position: sticky;
        top: 0;
        z-index: 10;
    }
</style>
