/**
 * Instrument Capture Export Logic
 * High-fidelity PDF and CSV export for captured instruments
 */

// Global state for export data
window.exportData = [];
window.exportMode = 'general';

const EXPORT_TH_CLASS = 'px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50';
const EXPORT_TD_CLASS = 'px-4 py-3 text-sm text-gray-600';

function escapeExportHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function csvExportValue(value) {
    return `"${String(value ?? '').replace(/"/g, '""')}"`;
}

function isOccupancyPermitExport(type) {
    const normalized = String(type || '').trim().toLowerCase();
    return normalized === 'occupancy permit (op)'
        || normalized === 'occupancy permit'
        || normalized.startsWith('op ');
}

function getExportColumns(mode) {
    if (mode === 'op') {
        return [
            { key: 'SN', label: 'S/N', preview: true, pdfWidth: 10 },
            { key: 'op_serial', label: 'Op Serial No', preview: true, pdfWidth: 24, fallback: 'N/A' },
            { key: 'serialNo', label: 'Serial No', csvOnly: true, pdfWidth: 14 },
            { key: 'pageNo', label: 'Page No', csvOnly: true, pdfWidth: 14 },
            { key: 'volumeNo', label: 'Volume No', csvOnly: true, pdfWidth: 14 },
            { key: 'reg_particulars', label: 'Registration Particulars', preview: true, pdfWidth: 48 },
            { key: 'party_2', label: 'Allottee', preview: true, pdfWidth: 42 },
            { key: 'deed_date', label: 'Deed Date', preview: true, pdfWidth: 22 },
            { key: 'location', label: 'Location', preview: true, pdfWidth: 'auto' }
        ];
    }

    // Note: A4 landscape usable width is ~277mm with 10mm side margins.
    // Keep total fixed widths well under that so "Location" gets enough room.
    return [
        { key: 'SN', label: 'S/N', preview: true, pdfWidth: 8 },
        { key: 'fileno', label: 'File No', preview: true, pdfWidth: 24 },
        { key: 'serialNo', label: 'Serial No', preview: true, pdfWidth: 16 },
        { key: 'pageNo', label: 'Page No', preview: true, pdfWidth: 16 },
        { key: 'volumeNo', label: 'Vol No', preview: true, pdfWidth: 16 },
        { key: 'reg_particulars', label: 'Registration Particulars', preview: true, pdfWidth: 30 },
        { key: 'party_1', label: 'Party 1', preview: true, pdfWidth: 30 },
        { key: 'party_2', label: 'Party 2', preview: true, pdfWidth: 30 },
        { key: 'party_3', label: 'Party 3', preview: true, pdfWidth: 24 },
        { key: 'location', label: 'Location', preview: true, pdfWidth: 'auto' },
        { key: 'deed_date', label: 'Deed Date', preview: true, pdfWidth: 22 }
    ];
}

function valueForExportColumn(item, column) {
    const value = item[column.key];
    if (value === null || value === undefined || value === '') {
        return column.fallback ?? '';
    }
    return value;
}

function renderExportHeaders(columns, includeCsvOnly = false) {
    return columns
        .filter(column => includeCsvOnly || column.preview)
        .map(column => `<th class="${EXPORT_TH_CLASS}">${escapeExportHtml(column.label)}</th>`)
        .join('');
}

window.openCaptureExportModal = async function () {
    const modal = document.getElementById('exportPreviewModal');
    const body = document.getElementById('exportPreviewBody');
    const type = document.getElementById('instrumentTypeFilter')?.value || '';
    const volume = document.getElementById('volumeFilter')?.value || '';
    const mode = isOccupancyPermitExport(type) ? 'op' : 'general';
    const columns = getExportColumns(mode);
    window.exportMode = mode;

    // Update labels in modal
    if (document.getElementById('previewFilterType')) {
        document.getElementById('previewFilterType').textContent = type || 'All Types';
    }
    if (document.getElementById('previewFilterVolume')) {
        document.getElementById('previewFilterVolume').textContent = volume || 'All';
    }
    
    // Update headers
    const head = document.querySelector('#exportPreviewTable thead tr');
    if (head) {  
        head.innerHTML = renderExportHeaders(columns);
    }
    
    modal.classList.remove('hidden');

    // Show loading
    body.innerHTML = `
        <tr>
            <td colspan="${columns.filter(column => column.preview).length}" class="px-6 py-12 text-center text-gray-500 italic">
                <div class="flex flex-col items-center gap-2">
                    <i class="fas fa-spinner fa-spin text-3xl text-green-500"></i>
                    <span>Fetching captured data for preview...</span>
                </div>
            </td>
        </tr>
    `;

    try {
        const response = await fetch(`${window.baseUrl}/instruments/export?instrument_type=${encodeURIComponent(type)}&volume_no=${volume}`);
        const result = await response.json();

        if (result.success) {
            const toTitleCase = (value) => {
                if (!value || typeof value !== 'string') {
                    return value ?? '';
                }
                return value
                    .toLowerCase()
                    .replace(/\b([a-z])([a-z']*)/g, (match, first, rest) => {
                        return first.toUpperCase() + rest;
                    });
            };

            const normalizeExportRow = (item) => ({
                ...item,
                party_1: toTitleCase(item.party_1),
                party_2: toTitleCase(item.party_2),
                party_3: toTitleCase(item.party_3),
                instrument_type: item.instrument_type ?? '',
                location: toTitleCase(item.location)
            });

            const sortedData = result.data.slice().sort((a, b) => {
                const getSerialValue = (item) => {
                    const raw = item.serialNo ?? item.serial_no ?? item.reg_serial_no ?? item.SN;
                    const parsed = parseInt(raw, 10);
                    return Number.isFinite(parsed) ? parsed : Number.POSITIVE_INFINITY;
                };
                const aSerial = getSerialValue(a);
                const bSerial = getSerialValue(b);

                if (aSerial !== bSerial) {
                    return aSerial - bSerial;
                }

                return String(a.fileno || '').localeCompare(String(b.fileno || ''));
            }).map((item, index) => ({
                ...normalizeExportRow(item),
                SN: index + 1
            }));

            window.exportData = sortedData;
            if (document.getElementById('previewRecordCount')) {
                document.getElementById('previewRecordCount').textContent = sortedData.length;
            }

            if (sortedData.length === 0) {
                body.innerHTML = `<tr><td colspan="${columns.filter(column => column.preview).length}" class="px-6 py-8 text-center text-gray-500">No records found matching filters.</td></tr>`;
                return;
            }

            body.innerHTML = sortedData.map(item => `
                <tr class="hover:bg-gray-50 transition-colors">
                    ${columns.filter(column => column.preview).map(column => {
                        const value = valueForExportColumn(item, column);
                        const isLongText = ['party_1', 'party_2', 'party_3', 'instrument_type', 'location'].includes(column.key);
                        const isRegistration = column.key === 'reg_particulars';
                        const classes = [
                            EXPORT_TD_CLASS,
                            isLongText ? 'truncate max-w-[160px]' : '',
                            isRegistration ? 'font-mono' : ''
                        ].filter(Boolean).join(' ');
                        return `<td class="${classes}" title="${escapeExportHtml(value)}">${escapeExportHtml(value)}</td>`;
                    }).join('')}
                </tr>
            `).join('');
        } else {
            throw new Error(result.error || 'Failed to fetch data');
        }
    } catch (e) {
        body.innerHTML = `<tr><td colspan="${columns.filter(column => column.preview).length}" class="px-6 py-8 text-center text-red-500">Error: ${escapeExportHtml(e.message)}</td></tr>`;
    }
};

window.closeExportModal = function () {
    document.getElementById('exportPreviewModal').classList.add('hidden');
};

window.downloadExportCsv = function () {
    if (!window.exportData || window.exportData.length === 0) {
        Swal.fire('No Data', 'There is no data to export.', 'warning');
        return;
    }

    const columns = getExportColumns(window.exportMode || 'general').filter(column => window.exportMode === 'op' || column.preview);
    const headers = columns.map(column => column.label);
    const csvContent = [
        headers.join(','),
        ...window.exportData.map(item => columns.map(column => csvExportValue(valueForExportColumn(item, column))).join(','))
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    const typeLabel = (document.getElementById('instrumentTypeFilter')?.value || 'All').replace(/\s+/g, '_');
    const volLabel = document.getElementById('volumeFilter')?.value || 'All';

    link.setAttribute('href', url);
    link.setAttribute('download', `Instrument_Capture_${typeLabel}_Vol_${volLabel}_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

window.downloadExportPdf = function () {
    if (!window.exportData || window.exportData.length === 0) {
        Swal.fire('No Data', 'There is no data to export.', 'warning');
        return;
    }

    // Load a remote image as a base64 data URL so jsPDF can embed it reliably.
    function loadImage(url) {
        return fetch(url)
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.blob();
            })
            .then(function (blob) {
                return new Promise(function (resolve) {
                    var reader = new FileReader();
                    reader.onloadend = function () { resolve(reader.result); };
                    reader.onerror = function () { resolve(null); };
                    reader.readAsDataURL(blob);
                });
            })
            .catch(function (e) {
                console.warn('Failed to load logo:', url, e);
                return null;
            });
    }

    var headerLeftLogoUrl = '/assets/logo/ministry1.jpg';
    var headerRightLogoUrl = '/assets/logo/ministry2.jpeg';
    var watermarkUrl = '/assets/logo/Nigerian-Coat-of-Arms.png';

    Promise.all([
        loadImage(headerLeftLogoUrl),
        loadImage(headerRightLogoUrl),
        loadImage(watermarkUrl)
    ]).then(function (logos) {
        try {
            var headerLeftLogo = logos[0];
            var headerRightLogo = logos[1];
            var watermarkLogo = logos[2];

            var { jsPDF } = window.jspdf;
            var doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

            var pageWidth = doc.internal.pageSize.getWidth();
            var pageHeight = doc.internal.pageSize.getHeight();
            var pageCenter = pageWidth / 2;
            var headerLogoSize = 22;

            function paintWatermark() {
                if (!watermarkLogo) return;
                // Low-opacity centered coat of arms behind the content.
                try {
                    var wmSize = 120; // mm
                    var wmX = (pageWidth - wmSize) / 2;
                    var wmY = (pageHeight - wmSize) / 2;
                    if (typeof doc.GState === 'function' && typeof doc.setGState === 'function') {
                        doc.setGState(new doc.GState({ opacity: 0.08 }));
                        doc.addImage(watermarkLogo, 'PNG', wmX, wmY, wmSize, wmSize);
                        doc.setGState(new doc.GState({ opacity: 1 }));
                    } else {
                        // Fallback: draw without transparency (will still sit behind text drawn after it).
                        doc.addImage(watermarkLogo, 'PNG', wmX, wmY, wmSize, wmSize);
                    }
                } catch (e) {
                    console.warn('Watermark draw failed', e);
                }
            }

            function paintMinistryHeader() {
                if (headerLeftLogo) {
                    doc.addImage(headerLeftLogo, 'JPEG', 10, 8, headerLogoSize, headerLogoSize);
                }
                if (headerRightLogo) {
                    doc.addImage(headerRightLogo, 'JPEG', pageWidth - 10 - headerLogoSize, 8, headerLogoSize, headerLogoSize);
                }
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(16);
                doc.setTextColor(0, 0, 0);
                doc.text('KANO STATE GOVERNMENT', pageCenter, 14, { align: 'center' });
                doc.setFontSize(12);
                doc.text('MINISTRY OF LAND AND PHYSICAL PLANNING', pageCenter, 20, { align: 'center' });
                doc.setFontSize(11);
                doc.text('DEEDS DEPARTMENT', pageCenter, 26, { align: 'center' });
                doc.setLineWidth(0.5);
                doc.line(10, 32, pageWidth - 10, 32);
            }

            paintMinistryHeader();

            var typeLabel = document.getElementById('instrumentTypeFilter')?.value || 'All Captured Instruments';
            var volLabel = document.getElementById('volumeFilter')?.value || 'All Volumes';

            var columns = getExportColumns(window.exportMode || 'general').filter(function (column) {
                return window.exportMode === 'op' || column.preview;
            });
            var tableColumn = columns.map(function (column) { return column.label; });
            var tableRows = window.exportData.map(function (item) {
                return columns.map(function (column) {
                    return valueForExportColumn(item, column);
                });
            });
            var columnStyles = {};
            columns.forEach(function (column, index) {
                if (column.pdfWidth) {
                    columnStyles[index] = { cellWidth: column.pdfWidth };
                }
            });

            doc.autoTable({
                head: [tableColumn],
                body: tableRows,
                startY: 50,
                theme: 'grid',
                styles: { fontSize: 8, cellPadding: 1.5, overflow: 'linebreak', valign: 'middle' },
                headStyles: { fillColor: [22, 163, 74], textColor: [255, 255, 255], fontStyle: 'bold', halign: 'center' },
                columnStyles: columnStyles,
                margin: { top: 50, left: 10, right: 10 },
                didDrawPage: function (data) {
                    // Repaint ministry header + title on every page
                    paintMinistryHeader();
                    doc.setFontSize(13);
                    doc.setFont('helvetica', 'bold');
                    doc.setTextColor(0, 0, 0);
                    doc.text('Instrument Capture Register - ' + typeLabel, 14, 39);
                    doc.setFontSize(10);
                    doc.setFont('helvetica', 'normal');
                    doc.text('Volume: ' + volLabel + ' | Generated on: ' + new Date().toLocaleDateString(), 14, 45);

                    // Paint the coat-of-arms watermark on every page AFTER the
                    // table body has been drawn so it sits visibly on top with
                    // low opacity instead of being hidden behind white cells.
                    paintWatermark();

                    // Page number footer placeholder – real total added in post-pass below
                    doc.setFontSize(8);
                    doc.setFont('helvetica', 'normal');
                    doc.setTextColor(100, 100, 100);
                    doc.text(
                        'Page ' + data.pageNumber + ' of {total}',
                        pageWidth - 10,
                        pageHeight - 5,
                        { align: 'right' }
                    );
                },
                didParseCell: function (data) {
                    if (data.column.index === 1 && data.cell.text[0] === 'N/A') {
                        data.cell.styles.textColor = [150, 150, 150];
                    }
                }
            });

            // Post-pass: replace "{total}" placeholder with real page count
            var totalPages = doc.internal.getNumberOfPages();
            for (var p = 1; p <= totalPages; p++) {
                doc.setPage(p);
                // White-out the placeholder area, then write the real string
                doc.setFillColor(255, 255, 255);
                doc.rect(pageWidth - 60, pageHeight - 10, 55, 8, 'F');
                doc.setFontSize(8);
                doc.setFont('helvetica', 'normal');
                doc.setTextColor(100, 100, 100);
                doc.text(
                    'Page ' + p + ' of ' + totalPages,
                    pageWidth - 10,
                    pageHeight - 5,
                    { align: 'right' }
                );
            }

            doc.save('Instrument_Capture_' + typeLabel.replace(/\s+/g, '_') + '_Vol_' + volLabel + '_' + new Date().toISOString().split('T')[0] + '.pdf');
        } catch (e) {
            console.error('PDF Generation Error:', e);
            Swal.fire('Error', 'Failed to generate PDF: ' + e.message, 'error');
        }
    });
};

// Initialize components when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 for filters if available
    if (window.$ && $.fn.select2) {
        $('#instrumentTypeFilter').select2({
            placeholder: "Select Type",
            allowClear: true,
            width: '100%'
        });

        $('#volumeFilter').select2({
            placeholder: "Vol",
            allowClear: true,
            width: '100%'
        });
        
        // Style Select2
        $('.select2-container--default .select2-selection--single').css({
            'height': '38px',
            'border-color': '#d1d5db',
            'border-radius': '0.5rem',
            'display': 'flex',
            'align-items': 'center'
        });
    }
    
    // Refresh Lucide icons
    if (window.lucide) {
        window.lucide.createIcons();
    }
});
