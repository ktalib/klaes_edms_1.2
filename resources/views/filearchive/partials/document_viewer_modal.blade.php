<!-- Document Viewer Modal -->
<div id="document-viewer-dialog" class="dialog-backdrop" style="display: none;" aria-hidden="true" tabindex="-1">
    <div class="modal-container h-screen w-screen p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl h-full flex flex-col">
            <div class="flex-shrink-0 flex items-center justify-between p-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold">Document Viewer</h2>
                <button id="close-viewer" class="btn btn-ghost btn-sm">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <div class="flex-1 flex overflow-hidden">
                <div class="w-64 border-r flex flex-col bg-gray-50">
                    <div class="p-3 border-b">
                        <h3 class="text-sm font-medium text-gray-700">Document Pages</h3>
                    </div>
                    <div class="flex-1 overflow-auto">
                        <div id="pages-list" class="p-2 space-y-2"></div>
                    </div>
                </div>

                <div class="flex-1 flex flex-col overflow-hidden">
                    <div class="p-2 border-b flex items-center justify-between bg-gray-50">
                        <div class="flex items-center gap-2">
                            <button class="btn btn-ghost btn-sm" id="prev-page">
                                <i data-lucide="chevron-left" class="h-4 w-4"></i>
                            </button>
                            <span class="text-sm" id="page-indicator">Page 1 of 0</span>
                            <button class="btn btn-ghost btn-sm" id="next-page">
                                <i data-lucide="chevron-right" class="h-4 w-4"></i>
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="qc-status-badge" class="hidden items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold"></span>
                            <button class="btn btn-ghost btn-sm" id="zoom-out">
                                <i data-lucide="zoom-out" class="h-4 w-4"></i>
                            </button>
                            <span class="text-sm" id="zoom-level">100%</span>
                            <button class="btn btn-ghost btn-sm" id="zoom-in">
                                <i data-lucide="zoom-in" class="h-4 w-4"></i>
                            </button>
                            <button class="btn btn-ghost btn-sm" id="rotate">
                                <i data-lucide="rotate-cw" class="h-4 w-4"></i>
                            </button>
                            <div class="w-px h-5 bg-gray-300 mx-1"></div>
                            <button class="btn btn-sm bg-blue-600 hover:bg-blue-700 text-white flex items-center gap-1" id="viewer-move-registry" title="Move this file's documents to another registry">
                                <i data-lucide="folder-symlink" class="h-4 w-4"></i>
                                <span class="hidden sm:inline">Move to NR</span>
                            </button>
                            <button class="btn btn-sm bg-emerald-600 hover:bg-emerald-700 text-white flex items-center gap-1" id="edit-filetype-toggle" title="Edit page classification (file type)">
                                <i data-lucide="tag" class="h-4 w-4"></i>
                                <span class="hidden sm:inline">Edit Type</span>
                            </button>
                            <button class="btn btn-sm bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-1" id="qc-edit-toggle" title="Quality control tools">
                                <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>
                                <span class="hidden sm:inline">Quality Control</span>
                            </button>
                        </div>
                    </div>

                    <!-- QC / Edit toolbar (hidden until "Quality Control" is toggled) -->
                    <div id="qc-toolbar" class="hidden border-b bg-indigo-50/60 px-3 py-2" data-apply-endpoint="{{ route('filearchive.pages.apply-edits', ['pageTyping' => '__ID__']) }}" data-qc-endpoint="{{ route('filearchive.pages.qc-status', ['pageTyping' => '__ID__']) }}" data-delete-endpoint="{{ route('filearchive.pages.delete', ['pageTyping' => '__ID__']) }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold text-indigo-700 uppercase tracking-wide mr-1">Edit</span>
                            <button type="button" class="btn btn-ghost btn-sm border" id="qc-rotate-left" title="Rotate left 90°">
                                <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm border" id="qc-rotate-right" title="Rotate right 90°">
                                <i data-lucide="rotate-cw" class="h-4 w-4"></i>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm border flex items-center gap-1" id="qc-crop-toggle" title="Crop to a selected region">
                                <i data-lucide="crop" class="h-4 w-4"></i><span class="text-xs">Crop</span>
                            </button>
                            <button type="button" class="btn btn-sm bg-amber-500 hover:bg-amber-600 text-white flex items-center gap-1 hidden" id="qc-crop-apply" title="Apply crop">
                                <i data-lucide="check" class="h-4 w-4"></i><span class="text-xs">Apply crop</span>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm border flex items-center gap-1 hidden" id="qc-crop-cancel">
                                <i data-lucide="x" class="h-4 w-4"></i><span class="text-xs">Cancel crop</span>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm border flex items-center gap-1" id="qc-enhance-toggle" title="Brightness / contrast">
                                <i data-lucide="sun" class="h-4 w-4"></i><span class="text-xs">Enhance</span>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm border flex items-center gap-1" id="qc-replace-btn" title="Replace with a new image">
                                <i data-lucide="upload" class="h-4 w-4"></i><span class="text-xs">Replace</span>
                            </button>
                            <input type="file" id="qc-replace-input" accept="image/png,image/jpeg,image/webp" class="hidden">
                            <button type="button" class="btn btn-sm bg-red-600 hover:bg-red-700 text-white flex items-center gap-1" id="qc-delete-btn" title="Delete this page">
                                <i data-lucide="trash-2" class="h-4 w-4"></i><span class="text-xs">Delete page</span>
                            </button>

                            <div class="flex-1"></div>

                            <button type="button" class="btn btn-sm bg-emerald-600 hover:bg-emerald-700 text-white flex items-center gap-1 disabled:opacity-50" id="qc-save-btn" disabled>
                                <i data-lucide="save" class="h-4 w-4"></i><span class="text-xs">Save changes</span>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm border flex items-center gap-1" id="qc-cancel-btn">
                                <i data-lucide="x" class="h-4 w-4"></i><span class="text-xs">Cancel</span>
                            </button>
                        </div>

                        <!-- Enhance sliders (hidden until Enhance is toggled) -->
                        <div id="qc-enhance-panel" class="hidden mt-2 flex flex-wrap items-center gap-4 bg-white border rounded-md p-2">
                            <label class="flex items-center gap-2 text-xs text-gray-600">
                                <i data-lucide="sun" class="h-3.5 w-3.5"></i> Brightness
                                <input type="range" id="qc-brightness" min="50" max="150" value="100" class="w-32">
                                <span id="qc-brightness-val" class="w-10 text-right font-mono">100%</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs text-gray-600">
                                <i data-lucide="contrast" class="h-3.5 w-3.5"></i> Contrast
                                <input type="range" id="qc-contrast" min="50" max="150" value="100" class="w-32">
                                <span id="qc-contrast-val" class="w-10 text-right font-mono">100%</span>
                            </label>
                            <button type="button" class="btn btn-ghost btn-xs border text-xs" id="qc-enhance-reset">Reset</button>
                        </div>

                        <!-- QC review decision -->
                        <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-indigo-100 pt-2">
                            <span class="text-xs font-semibold text-indigo-700 uppercase tracking-wide mr-1">Review</span>
                            <button type="button" class="btn btn-sm bg-green-600 hover:bg-green-700 text-white flex items-center gap-1" id="qc-pass-btn">
                                <i data-lucide="check-circle" class="h-4 w-4"></i><span class="text-xs">Pass</span>
                            </button>
                            <button type="button" class="btn btn-sm bg-red-600 hover:bg-red-700 text-white flex items-center gap-1" id="qc-fail-btn">
                                <i data-lucide="x-circle" class="h-4 w-4"></i><span class="text-xs">Fail</span>
                            </button>
                            <input type="text" id="qc-note" placeholder="Issue note (optional)" class="flex-1 min-w-[160px] text-sm border rounded-md px-2 py-1">
                            <span id="qc-review-meta" class="text-xs text-gray-500"></span>
                        </div>
                    </div>

                    <div class="flex-1 overflow-auto flex items-center justify-center bg-gray-100 p-4">
                        <div id="current-page-content" class="bg-white shadow-md rounded-md max-w-[900px] w-full mx-auto transition-transform" style="transform: scale(1) rotate(0deg);">
                            <div class="relative h-full w-full flex items-center justify-center">
                                <img src="" alt="Document page" id="document-image" class="w-full max-h-[85vh] object-contain hidden">
                                <canvas id="qc-canvas" class="max-w-full max-h-[85vh] object-contain hidden"></canvas>
                                <iframe id="document-pdf" src="" title="Document page" class="w-full h-[85vh] hidden" frameborder="0"></iframe>
                                <div id="document-placeholder" class="flex flex-col items-center justify-center p-6 text-center text-sm text-gray-500 gap-3" style="display: none;">
                                    <i data-lucide="file" class="h-10 w-10 text-gray-300"></i>
                                    <p>No preview available for this page.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-2 border-t bg-gray-50">
                        <div class="text-sm text-gray-500">
                            <div class="flex items-center justify-between">
                                <div id="page-info">
                                    <span class="font-medium">Select a page</span>
                                </div>
                                <div class="text-right space-y-0.5">
                                    <div class="font-semibold text-xs text-gray-900" id="viewer-file-title">-</div>
                                    <div class="font-mono text-xs font-semibold text-blue-700" id="viewer-file-number">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit File Type (Page Classification) Modal -->
<div id="edit-filetype-dialog" class="dialog-backdrop" style="display: none;" aria-hidden="true" tabindex="-1"
     data-typing-data-url="{{ route('pagetyping.api.typing-data') }}"
     data-save-url="{{ route('filearchive.pages.classification', ['pageTyping' => '__ID__']) }}">
    <div class="modal-container flex items-center justify-center p-4 min-h-screen">
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md flex flex-col max-h-[92vh]">
            <div class="flex-shrink-0 flex items-center justify-between p-4 border-b bg-gray-50 rounded-t-lg">
                <div class="flex items-center gap-2">
                    <i data-lucide="tag" class="h-5 w-5 text-emerald-600"></i>
                    <h2 class="text-base font-semibold">Edit Page Classification</h2>
                </div>
                <button id="edit-filetype-close" class="btn btn-ghost btn-sm" type="button">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <form id="edit-filetype-form" class="p-4 space-y-4 overflow-y-auto" autocomplete="off">
                <div class="text-xs text-gray-500">
                    Editing <span class="font-mono font-semibold text-blue-700" id="eft-page-label">page</span>
                    of <span class="font-semibold" id="eft-file-number">-</span>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Registry</label>
                    <select id="eft-registry" class="form-input w-full text-sm py-1.5 px-2 border rounded-md">
                        <option value="Lands Registry">Lands Registry</option>
                        <option value="KANGIS">KANGIS</option>
                        <option value="SLTR">SLTR</option>
                        <option value="DCIV">DCIV</option>
                        <option value="Cadastral">Cadastral</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Cover Type</label>
                    <select id="eft-cover-type" required class="form-input w-full text-sm py-1.5 px-2 border rounded-md">
                        <option value="">Select cover type</option>
                    </select>
                    <p class="text-xs text-amber-600 mt-1">Front Cover: main documents with pagination | Back Cover: supporting documents without pagination</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Page Type</label>
                    <select id="eft-page-type" required class="form-input w-full text-sm py-1.5 px-2 border rounded-md">
                        <option value="">Select page type</option>
                    </select>
                    <input type="text" id="eft-page-type-other" maxlength="50" placeholder="Enter custom page type"
                           class="form-input w-full text-sm mt-1 py-1.5 px-2 border rounded-md hidden">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Page Subtype</label>
                    <select id="eft-page-subtype" class="form-input w-full text-sm py-1.5 px-2 border rounded-md">
                        <option value="">Select page subtype</option>
                    </select>
                    <input type="text" id="eft-page-subtype-other" maxlength="50" placeholder="Enter custom subtype"
                           class="form-input w-full text-sm mt-1 py-1.5 px-2 border rounded-md hidden">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Serial Number</label>
                    <input type="text" id="eft-serial" maxlength="5" required placeholder="e.g., 0"
                           class="form-input w-full text-sm py-1.5 px-2 border rounded-md">
                    <p class="text-xs text-gray-500 mt-1">Auto-calculated: <span id="eft-serial-auto">0</span></p>
                </div>

                <div class="border rounded-md p-3 bg-gray-50">
                    <h4 class="text-sm font-semibold mb-2">Page Code Preview</h4>
                    <div class="flex flex-wrap items-center gap-2">
                        <span id="eft-code-preview" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-semibold bg-blue-100 text-blue-800">—</span>
                        <span id="eft-definition-preview" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-semibold bg-green-100 text-green-800">—</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Format: CoverType-PageType-SubType-SerialNo</p>
                    <p class="text-xs text-gray-500">Definition Code: AutoPosition-PageCode</p>
                </div>
            </form>

            <div class="flex-shrink-0 flex items-center justify-end gap-2 p-4 border-t bg-gray-50 rounded-b-lg">
                <button type="button" id="edit-filetype-cancel" class="btn btn-outline btn-sm">Cancel</button>
                <button type="button" id="edit-filetype-save" class="btn btn-sm bg-emerald-600 hover:bg-emerald-700 text-white flex items-center gap-1">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    <span>Save Classification</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let documentPages = [];
let currentFileMeta = null;
let currentPageIndex = 0;
let zoomLevel = 100;
let rotation = 0;

const thumbnailPlaceholder = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none"><rect width="64" height="64" rx="8" fill="#F3F4F6"/><path d="M20 18h24v28H20z" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M24 26h16M24 32h10" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round"/></svg>');

function clearDocumentViewerData() {
    if (typeof exitQcEditMode === 'function') exitQcEditMode();
    documentPages = [];
    currentFileMeta = null;
    currentPageIndex = 0;
    zoomLevel = 100;
    rotation = 0;
}

window.clearDocumentViewerData = clearDocumentViewerData;

/**
 * Move the file currently open in the viewer to another registry.
 *
 * The registry is part of every document's folder path, so a move relocates the
 * pages being viewed — the viewer is closed and the page reloaded afterwards
 * rather than left showing URLs that no longer resolve.
 */
document.addEventListener('DOMContentLoaded', function () {
    const moveBtn = document.getElementById('viewer-move-registry');
    if (!moveBtn) return;

    moveBtn.addEventListener('click', function () {
        if (!currentFileMeta || !currentFileMeta.id) {
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: 'No file open', text: 'Open a file in the viewer first.', timer: 2500 });
            }
            return;
        }

        EdmsRegistryTransfer.open(currentFileMeta.id, currentFileMeta.file_number, function () {
            document.getElementById('close-viewer')?.click();
            window.location.reload();
        });
    });
});

function loadDocumentPages(fileMeta, pages) {
    currentFileMeta = fileMeta || null;
    documentPages = Array.isArray(pages) ? pages : [];
    currentPageIndex = 0;
    zoomLevel = 100;
    rotation = 0;

    const fileTitleEl = document.getElementById('viewer-file-title');
    const fileNumberEl = document.getElementById('viewer-file-number');
    if (fileTitleEl) {
        fileTitleEl.textContent = currentFileMeta && currentFileMeta.file_title ? currentFileMeta.file_title : '-';
    }
    if (fileNumberEl) {
        fileNumberEl.textContent = currentFileMeta && currentFileMeta.file_number ? currentFileMeta.file_number : '-';
    }

    renderPagesList();

    if (documentPages.length > 0) {
        selectPage(0);
    } else {
        showEmptyState();
    }
}

function renderPagesList() {
    const pagesList = document.getElementById('pages-list');
    if (!pagesList) {
        return;
    }

    pagesList.innerHTML = '';

    if (!documentPages.length) {
        pagesList.innerHTML = '<div class="p-4 text-center text-sm text-gray-500">No pages available.</div>';
        updatePageIndicator();
        return;
    }

    documentPages.forEach((page, index) => {
        const pageItem = document.createElement('div');
        pageItem.className = 'p-2 border rounded-md cursor-pointer hover:bg-gray-50 page-item';
        if (index === 0) {
            if (page.cover_type && page.cover_type.code === 'BC') {
                pageItem.classList.add('bg-green-50', 'border-green-200');
            } else {
                pageItem.classList.add('bg-blue-50', 'border-blue-200');
            }
        }

        const thumbnail = page.thumbnail_url || thumbnailPlaceholder;
        const primaryLabel = page.page_code || (page.page_type ? page.page_type.name : 'Untitled');
        const secondaryParts = [];
        if (page.page_type && page.page_type.name) {
            secondaryParts.push(`<span class="text-emerald-600">${page.page_type.name}</span>`);
        }
        if (page.page_subtype && page.page_subtype.name) {
            secondaryParts.push(`<span class="text-emerald-600">${page.page_subtype.name}</span>`);
        }
        if (page.typed_by && page.typed_by.name) {
            secondaryParts.push(`<span class="text-orange-500">Typed by: ${page.typed_by.name}</span>`);
        }
        const secondaryLabel = secondaryParts.join(' · ');

        pageItem.innerHTML = `
            <div class="flex items-center gap-2">
                <div class="w-10 h-10 bg-gray-100 rounded overflow-hidden flex items-center justify-center">
                    <img src="${thumbnail}" alt="Page ${index + 1}" class="w-full h-full object-cover" onerror="this.src='${thumbnailPlaceholder}'" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold truncate flex items-center gap-1 text-indigo-700">
                        ${primaryLabel}
                    </div>
                    <div class="text-xs truncate">
                        ${secondaryLabel || '<span class="text-gray-400">Not typed</span>'}
                    </div>
                </div>
            </div>
        `;

        pageItem.addEventListener('click', () => selectPage(index));
        pagesList.appendChild(pageItem);
    });

    updatePageIndicator();
}

function selectPage(index) {
    if (index < 0 || index >= documentPages.length) {
        return;
    }

    currentPageIndex = index;

    const pageItems = document.querySelectorAll('.page-item');
    pageItems.forEach((item, i) => {
        item.classList.remove('bg-blue-50', 'border-blue-200', 'bg-green-50', 'border-green-200');
        const page = documentPages[i];
        if (i === index) {
            if (page.cover_type && page.cover_type.code === 'BC') {
                item.classList.add('bg-green-50', 'border-green-200');
            } else {
                item.classList.add('bg-blue-50', 'border-blue-200');
            }
        }
    });

    const page = documentPages[index];
    const documentImage = document.getElementById('document-image');
    const documentPdf = document.getElementById('document-pdf');
    const documentPlaceholder = document.getElementById('document-placeholder');

    if (documentImage) {
        documentImage.classList.add('hidden');
        documentImage.removeAttribute('src');
    }
    if (documentPdf) {
        documentPdf.classList.add('hidden');
        documentPdf.src = '';
    }
    if (documentPlaceholder) {
        documentPlaceholder.style.display = 'none';
    }

    if (page.media_type === 'image' && page.viewer_url) {
        if (documentImage) {
            documentImage.src = page.viewer_url;
            documentImage.classList.remove('hidden');
            documentImage.onerror = function() {
                if (documentPlaceholder) {
                    documentPlaceholder.style.display = 'flex';
                }
                this.classList.add('hidden');
            };
        }
    } else if (page.viewer_url) {
        if (documentPdf) {
            let viewerUrl = page.viewer_url;
            if (page.media_type === 'pdf') {
                const baseUrl = viewerUrl.split('#')[0];
                const hashParts = [];
                hashParts.push('toolbar=0');
                const pdfTargetPage = page.pdf_page_number || page.page_number;
                if (pdfTargetPage) {
                    hashParts.push('page=' + pdfTargetPage);
                }
                viewerUrl = baseUrl + '#' + hashParts.join('&');
            }

            if (documentPdf.src !== viewerUrl) {
                documentPdf.src = '';
            }
            documentPdf.src = viewerUrl;
            documentPdf.classList.remove('hidden');
        }
    } else if (documentPlaceholder) {
        documentPlaceholder.style.display = 'flex';
    }

    const pageInfo = document.getElementById('page-info');
    if (pageInfo) {
        const codeLabel = page.page_code || (page.page_type ? page.page_type.name : 'Untitled');
        const detailParts = [];
        if (page.page_type && page.page_type.name) {
            detailParts.push(page.page_type.name);
        }
        if (page.page_subtype && page.page_subtype.name) {
            detailParts.push(page.page_subtype.name);
        }

        pageInfo.innerHTML = `
            <span class="font-semibold text-indigo-700">${codeLabel}</span>
            ${detailParts.length ? `<span> - </span><span class="text-emerald-700 font-medium">${detailParts.join('</span> · <span class="text-emerald-700 font-medium">')}</span>` : ''}
            ${page.typed_by && page.typed_by.name ? `<span> · </span><span class="text-orange-600 font-medium">Typed by: ${page.typed_by.name}</span>` : ''}
        `;
    }

    updatePageIndicator();
    updateTransform();
    refreshQcUi();
}

function showEmptyState(message = 'No pages available for this document.') {
    const documentImage = document.getElementById('document-image');
    const documentPdf = document.getElementById('document-pdf');
    const documentPlaceholder = document.getElementById('document-placeholder');
    const pageInfo = document.getElementById('page-info');

    if (documentImage) {
        documentImage.classList.add('hidden');
        documentImage.removeAttribute('src');
    }
    if (documentPdf) {
        documentPdf.classList.add('hidden');
        documentPdf.src = '';
    }
    if (documentPlaceholder) {
        documentPlaceholder.style.display = 'flex';
        const placeholderText = documentPlaceholder.querySelector('p');
        if (placeholderText) {
            placeholderText.textContent = message;
        }
    }
    if (pageInfo) {
        pageInfo.innerHTML = `<span class="font-medium text-gray-500">${message}</span>`;
    }

    currentPageIndex = 0;
    updatePageIndicator();

    const zoomLabel = document.getElementById('zoom-level');
    if (zoomLabel) {
        zoomLabel.textContent = '100%';
    }
}

function updatePageIndicator() {
    const pageIndicator = document.getElementById('page-indicator');
    if (!pageIndicator) {
        return;
    }

    if (!documentPages.length) {
        pageIndicator.textContent = 'Page 0 of 0';
    } else {
        pageIndicator.textContent = 'Page ' + (currentPageIndex + 1) + ' of ' + documentPages.length;
    }
}

function updateTransform() {
    const content = document.getElementById('current-page-content');
    const zoomLabel = document.getElementById('zoom-level');
    if (!content || !zoomLabel) {
        return;
    }

    const currentPage = documentPages[currentPageIndex];

    if (currentPage && currentPage.media_type === 'image') {
        content.style.transform = 'scale(' + (zoomLevel / 100) + ') rotate(' + rotation + 'deg)';
        zoomLabel.textContent = zoomLevel + '%';
    } else {
        content.style.transform = 'scale(1) rotate(' + rotation + 'deg)';
        zoomLabel.textContent = '100%';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const prevButton = document.getElementById('prev-page');
    const nextButton = document.getElementById('next-page');
    const zoomInButton = document.getElementById('zoom-in');
    const zoomOutButton = document.getElementById('zoom-out');
    const rotateButton = document.getElementById('rotate');
    const closeButton = document.getElementById('close-viewer');

    if (prevButton) {
        prevButton.addEventListener('click', function() {
            if (currentPageIndex > 0) {
                selectPage(currentPageIndex - 1);
            }
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', function() {
            if (currentPageIndex < documentPages.length - 1) {
                selectPage(currentPageIndex + 1);
            }
        });
    }

    if (zoomInButton) {
        zoomInButton.addEventListener('click', function() {
            zoomLevel = Math.min(zoomLevel + 25, 200);
            updateTransform();
        });
    }

    if (zoomOutButton) {
        zoomOutButton.addEventListener('click', function() {
            zoomLevel = Math.max(zoomLevel - 25, 50);
            updateTransform();
        });
    }

    if (rotateButton) {
        rotateButton.addEventListener('click', function() {
            rotation = (rotation + 90) % 360;
            updateTransform();
        });
    }

    if (closeButton) {
        closeButton.addEventListener('click', function() {
            $('#document-viewer-dialog').fadeOut('fast');
            clearDocumentViewerData();
        });
    }

    document.addEventListener('keydown', function(e) {
        const viewer = document.getElementById('document-viewer-dialog');
        if (!viewer || viewer.style.display === 'none') {
            return;
        }

        switch (e.key) {
            case 'ArrowLeft':
                if (currentPageIndex > 0) {
                    selectPage(currentPageIndex - 1);
                }
                break;
            case 'ArrowRight':
                if (currentPageIndex < documentPages.length - 1) {
                    selectPage(currentPageIndex + 1);
                }
                break;
            case 'Escape':
                $('#document-viewer-dialog').fadeOut('fast');
                clearDocumentViewerData();
                break;
            case '+':
            case '=':
                zoomLevel = Math.min(zoomLevel + 25, 200);
                updateTransform();
                break;
            case '-':
                zoomLevel = Math.max(zoomLevel - 25, 50);
                updateTransform();
                break;
            case 'r':
            case 'R':
                rotation = (rotation + 90) % 360;
                updateTransform();
                break;
        }
    });
});

/* ============================================================
 * Quality Control — in-viewer editing (rotate/enhance/replace)
 * + pass/fail review. Edits overwrite the image in place; the
 * stored filename/path never changes (server backs up original).
 * ============================================================ */
let qcEditMode = false;
let qcDirty = false;
let qcBaseImage = null;          // HTMLImageElement of the current (or replacement) image
let qcRotation = 0;              // 0/90/180/270
let qcBrightness = 100;          // percent
let qcContrast = 100;            // percent
let qcCropping = false;          // crop selection in progress/armed
let qcCropStart = null;          // {x, y} viewport coords of drag start
let qcCropRect = null;           // {left, top, w, h} viewport coords of selection

function qcEndpoint(kind) {
    const toolbar = document.getElementById('qc-toolbar');
    const page = documentPages[currentPageIndex];
    if (!toolbar || !page || !page.pagetyping_id) return null;
    const attr = kind === 'apply' ? 'data-apply-endpoint'
        : kind === 'delete' ? 'data-delete-endpoint'
        : 'data-qc-endpoint';
    const tpl = toolbar.getAttribute(attr);
    return tpl ? tpl.replace('__ID__', page.pagetyping_id) : null;
}

function qcCsrf() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function refreshQcUi() {
    const page = documentPages[currentPageIndex];
    // Any page change leaves edit mode without saving.
    if (qcEditMode) exitQcEditMode();

    const badge = document.getElementById('qc-status-badge');
    const toggle = document.getElementById('qc-edit-toggle');
    const meta = document.getElementById('qc-review-meta');
    const editable = !!(page && page.is_editable);

    if (toggle) {
        toggle.disabled = !editable;
        toggle.classList.toggle('opacity-50', !editable);
        toggle.title = editable ? 'Quality control tools' : 'Only image pages can be edited';
    }

    if (badge) {
        const status = page ? (page.qc_status || 'pending') : 'pending';
        const map = {
            passed: { t: 'QC Passed', c: 'bg-green-100 text-green-700' },
            failed: { t: 'QC Failed', c: 'bg-red-100 text-red-700' },
            pending: { t: 'QC Pending', c: 'bg-gray-100 text-gray-600' },
        };
        const cfg = map[status] || map.pending;
        badge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ' + cfg.c;
        badge.textContent = cfg.t;
        badge.classList.remove('hidden');
    }

    if (meta) {
        if (page && page.qc_reviewed_by) {
            meta.textContent = 'Reviewed by ' + page.qc_reviewed_by + (page.qc_reviewed_at ? ' · ' + page.qc_reviewed_at : '');
        } else {
            meta.textContent = 'Not yet reviewed';
        }
    }
    const noteInput = document.getElementById('qc-note');
    if (noteInput) noteInput.value = (page && page.qc_override_note) ? page.qc_override_note : '';
}

function qcRenderCanvas() {
    const canvas = document.getElementById('qc-canvas');
    if (!canvas || !qcBaseImage) return;
    const ctx = canvas.getContext('2d');
    const w = qcBaseImage.naturalWidth;
    const h = qcBaseImage.naturalHeight;
    const swap = (qcRotation === 90 || qcRotation === 270);
    canvas.width = swap ? h : w;
    canvas.height = swap ? w : h;

    ctx.save();
    ctx.filter = 'brightness(' + qcBrightness + '%) contrast(' + qcContrast + '%)';
    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.rotate(qcRotation * Math.PI / 180);
    ctx.drawImage(qcBaseImage, -w / 2, -h / 2);
    ctx.restore();
}

function qcMarkDirty() {
    qcDirty = true;
    const save = document.getElementById('qc-save-btn');
    if (save) save.disabled = false;
}

function qcShowBrokenCanvas() {
    qcBaseImage = null; // nothing to rotate/crop/enhance until a Replace happens
    const canvas = document.getElementById('qc-canvas');
    if (!canvas) return;
    canvas.width = 800;
    canvas.height = 1035;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#f9fafb';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth = 3;
    ctx.strokeRect(12, 12, canvas.width - 24, canvas.height - 24);
    ctx.fillStyle = '#9ca3af';
    ctx.textAlign = 'center';
    ctx.font = 'bold 30px sans-serif';
    ctx.fillText('Image unavailable', canvas.width / 2, canvas.height / 2 - 12);
    ctx.font = '20px sans-serif';
    ctx.fillText('Click "Replace" to upload a new image for this page.', canvas.width / 2, canvas.height / 2 + 26);
    canvas.classList.remove('hidden');
    const save = document.getElementById('qc-save-btn');
    if (save) save.disabled = true; // stays disabled until a replacement is chosen
    qcToast('This image is broken or missing — use Replace to upload a new one.');
}

function enterQcEditMode() {
    const page = documentPages[currentPageIndex];
    if (!page || !page.is_editable) return;

    qcEditMode = true;
    qcDirty = false;
    qcRotation = 0;
    qcBrightness = 100;
    qcContrast = 100;

    document.getElementById('qc-toolbar').classList.remove('hidden');
    document.getElementById('qc-enhance-panel').classList.add('hidden');
    ['qc-brightness', 'qc-contrast'].forEach(id => { const el = document.getElementById(id); if (el) el.value = 100; });
    document.getElementById('qc-brightness-val').textContent = '100%';
    document.getElementById('qc-contrast-val').textContent = '100%';
    const save = document.getElementById('qc-save-btn'); if (save) save.disabled = true;

    // Neutralise the view-only transform while editing on the canvas.
    const content = document.getElementById('current-page-content');
    if (content) content.style.transform = 'scale(1) rotate(0deg)';

    const img = document.getElementById('document-image');
    const canvas = document.getElementById('qc-canvas');
    if (img) img.classList.add('hidden');

    // Broken/missing image (no source URL) → go straight to the replace canvas.
    if (!page.viewer_url || page.is_broken) {
        qcShowBrokenCanvas();
        return;
    }

    qcBaseImage = new Image();
    qcBaseImage.onload = function () {
        qcRenderCanvas();
        if (canvas) canvas.classList.remove('hidden');
    };
    qcBaseImage.onerror = function () {
        // The URL existed but the file won't decode — treat as broken so the
        // reviewer can still Replace it.
        qcShowBrokenCanvas();
    };
    // Same-origin public-disk image → canvas stays untainted for toBlob().
    qcBaseImage.src = (page.viewer_url || '').split('?')[0] + '?edit=' + Date.now();
}

function exitQcEditMode() {
    qcCancelCrop();
    qcEditMode = false;
    qcDirty = false;
    qcBaseImage = null;
    const toolbar = document.getElementById('qc-toolbar');
    if (toolbar) toolbar.classList.add('hidden');
    const canvas = document.getElementById('qc-canvas');
    if (canvas) canvas.classList.add('hidden');
    const img = document.getElementById('document-image');
    const page = documentPages[currentPageIndex];
    if (img && page && page.media_type === 'image' && page.viewer_url) {
        img.classList.remove('hidden');
    }
    updateTransform();
}

/* ---- Crop: drag a rectangle over the canvas, then apply ---- */
function qcCropOverlayEl() {
    let el = document.getElementById('qc-crop-rect');
    if (!el) {
        el = document.createElement('div');
        el.id = 'qc-crop-rect';
        el.className = 'fixed z-[70] border-2 border-dashed border-amber-500 bg-amber-400/20 pointer-events-none';
        el.style.display = 'none';
        document.body.appendChild(el);
    }
    return el;
}

function qcStartCropMode() {
    if (!qcEditMode || !qcBaseImage) return;
    qcCropping = true;
    qcCropStart = null;
    qcCropRect = null;
    const canvas = document.getElementById('qc-canvas');
    if (canvas) canvas.style.cursor = 'crosshair';
    document.getElementById('qc-crop-apply').classList.remove('hidden');
    document.getElementById('qc-crop-cancel').classList.remove('hidden');
    document.getElementById('qc-crop-toggle').classList.add('hidden');
    qcToast('Drag a rectangle over the page, then "Apply crop".');
}

function qcCancelCrop() {
    qcCropping = false;
    qcCropStart = null;
    qcCropRect = null;
    const overlay = document.getElementById('qc-crop-rect');
    if (overlay) overlay.style.display = 'none';
    const canvas = document.getElementById('qc-canvas');
    if (canvas) canvas.style.cursor = '';
    ['qc-crop-apply', 'qc-crop-cancel'].forEach(id => { const el = document.getElementById(id); if (el) el.classList.add('hidden'); });
    const toggle = document.getElementById('qc-crop-toggle');
    if (toggle && qcEditMode) toggle.classList.remove('hidden');
}

function qcCropMouseDown(e) {
    if (!qcCropping) return;
    const canvas = document.getElementById('qc-canvas');
    const rect = canvas.getBoundingClientRect();
    if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) return;
    e.preventDefault();
    qcCropStart = { x: e.clientX, y: e.clientY };
    qcCropRect = null;
}

function qcCropMouseMove(e) {
    if (!qcCropping || !qcCropStart) return;
    const overlay = qcCropOverlayEl();
    const left = Math.min(qcCropStart.x, e.clientX);
    const top = Math.min(qcCropStart.y, e.clientY);
    const w = Math.abs(e.clientX - qcCropStart.x);
    const h = Math.abs(e.clientY - qcCropStart.y);
    qcCropRect = { left, top, w, h };
    overlay.style.display = 'block';
    overlay.style.left = left + 'px';
    overlay.style.top = top + 'px';
    overlay.style.width = w + 'px';
    overlay.style.height = h + 'px';
}

function qcCropMouseUp() {
    if (!qcCropping) return;
    qcCropStart = null; // selection kept in qcCropRect until Apply
}

function qcApplyCrop() {
    const canvas = document.getElementById('qc-canvas');
    if (!canvas || !qcCropRect || qcCropRect.w < 8 || qcCropRect.h < 8) {
        qcToast('Draw a larger selection first.');
        return;
    }
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    let sx = Math.max(0, (qcCropRect.left - rect.left) * scaleX);
    let sy = Math.max(0, (qcCropRect.top - rect.top) * scaleY);
    let sw = Math.min(canvas.width - sx, qcCropRect.w * scaleX);
    let sh = Math.min(canvas.height - sy, qcCropRect.h * scaleY);
    if (sw < 1 || sh < 1) { qcToast('Selection is outside the page.'); return; }

    // Bake the currently displayed canvas (rotation + enhance already applied) into the crop.
    const tmp = document.createElement('canvas');
    tmp.width = Math.round(sw);
    tmp.height = Math.round(sh);
    tmp.getContext('2d').drawImage(canvas, sx, sy, sw, sh, 0, 0, tmp.width, tmp.height);

    const dataUrl = tmp.toDataURL('image/jpeg', 0.95);
    qcCancelCrop();
    // Flatten: cropped result becomes the new base; reset transform/filters.
    qcRotation = 0;
    qcBrightness = 100;
    qcContrast = 100;
    ['qc-brightness', 'qc-contrast'].forEach(id => { const el = document.getElementById(id); if (el) el.value = 100; });
    document.getElementById('qc-brightness-val').textContent = '100%';
    document.getElementById('qc-contrast-val').textContent = '100%';
    qcBaseImage = new Image();
    qcBaseImage.onload = function () { qcRenderCanvas(); qcMarkDirty(); };
    qcBaseImage.src = dataUrl;
}

function qcSave() {
    const canvas = document.getElementById('qc-canvas');
    const url = qcEndpoint('apply');
    if (!canvas || !url) return;

    const saveBtn = document.getElementById('qc-save-btn');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.querySelector('span').textContent = 'Saving…'; }

    canvas.toBlob(function (blob) {
        if (!blob) { alert('Could not read the edited image.'); return; }
        const form = new FormData();
        form.append('file', blob, 'qc-edit.jpg');

        fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': qcCsrf(), 'Accept': 'application/json' },
            body: form,
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Save failed');
            const page = documentPages[currentPageIndex];
            if (page && data.viewer_url) {
                page.viewer_url = data.viewer_url;
                page.thumbnail_url = data.viewer_url;
                page.media_type = 'image'; // a broken page is now a real image
                page.is_broken = false;
            }
            exitQcEditMode();
            // Re-render the (now overwritten) image + thumbnail with the cache-busted URL.
            renderPagesList();
            selectPage(currentPageIndex);
            qcToast('Document updated — filename unchanged.');
        })
        .catch(err => alert('Unable to save: ' + err.message))
        .finally(() => {
            if (saveBtn) { saveBtn.querySelector('span').textContent = 'Save changes'; }
        });
    }, 'image/jpeg', 0.95);
}

function qcSetStatus(status) {
    const url = qcEndpoint('qc');
    if (!url) return;
    const note = document.getElementById('qc-note');
    const form = new FormData();
    form.append('qc_status', status);
    if (note && note.value.trim()) form.append('qc_override_note', note.value.trim());
    if (status === 'failed') form.append('has_qc_issues', '1');

    fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': qcCsrf(), 'Accept': 'application/json' },
        body: form,
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error(data.message || 'Failed to save QC status');
        const page = documentPages[currentPageIndex];
        if (page) {
            page.qc_status = data.qc_status;
            page.qc_reviewed_by = data.qc_reviewed_by;
            page.qc_reviewed_at = data.qc_reviewed_at;
            page.qc_override_note = data.qc_override_note;
            page.has_qc_issues = data.has_qc_issues;
        }
        // Update the badge/meta without leaving edit mode.
        const badge = document.getElementById('qc-status-badge');
        const map = {
            passed: { t: 'QC Passed', c: 'bg-green-100 text-green-700' },
            failed: { t: 'QC Failed', c: 'bg-red-100 text-red-700' },
            pending: { t: 'QC Pending', c: 'bg-gray-100 text-gray-600' },
        };
        const cfg = map[data.qc_status] || map.pending;
        if (badge) { badge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ' + cfg.c; badge.textContent = cfg.t; badge.classList.remove('hidden'); }
        const meta = document.getElementById('qc-review-meta');
        if (meta && data.qc_reviewed_by) meta.textContent = 'Reviewed by ' + data.qc_reviewed_by + (data.qc_reviewed_at ? ' · ' + data.qc_reviewed_at : '');
        qcToast(status === 'passed' ? 'Marked as QC passed.' : 'Marked as QC failed.');
    })
    .catch(err => alert(err.message));
}

function qcDeletePage() {
    const page = documentPages[currentPageIndex];
    const url = qcEndpoint('delete');
    if (!page || !url) return;

    const label = page.page_code || (page.page_type && page.page_type.name) || ('Page ' + (page.page_number ?? (currentPageIndex + 1)));
    if (!confirm(`Delete "${label}"?\n\nThis permanently removes the page from the archive and cannot be undone.`)) {
        return;
    }

    const delBtn = document.getElementById('qc-delete-btn');
    if (delBtn) { delBtn.disabled = true; const s = delBtn.querySelector('span'); if (s) s.textContent = 'Deleting…'; }

    fetch(url, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': qcCsrf(),
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error(data.message || 'Delete failed');

        exitQcEditMode();

        // Drop the page from memory and re-render the list.
        const removedIndex = currentPageIndex;
        documentPages.splice(removedIndex, 1);

        if (!documentPages.length) {
            renderPagesList();
            showEmptyState('This document has no pages left.');
            qcToast('Page deleted.');
            return;
        }

        const nextIndex = Math.min(removedIndex, documentPages.length - 1);
        renderPagesList();
        selectPage(nextIndex);
        qcToast('Page deleted.');
    })
    .catch(err => alert('Unable to delete: ' + err.message))
    .finally(() => {
        if (delBtn) { delBtn.disabled = false; const s = delBtn.querySelector('span'); if (s) s.textContent = 'Delete page'; }
    });
}

function qcToast(msg) {
    let el = document.getElementById('qc-toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'qc-toast';
        el.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] bg-gray-900 text-white text-sm px-4 py-2 rounded-md shadow-lg transition-opacity';
        document.body.appendChild(el);
    }
    el.textContent = msg;
    el.style.opacity = '1';
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.style.opacity = '0'; }, 2500);
}

document.addEventListener('DOMContentLoaded', function () {
    const on = (id, evt, fn) => { const el = document.getElementById(id); if (el) el.addEventListener(evt, fn); };

    on('qc-edit-toggle', 'click', () => { qcEditMode ? exitQcEditMode() : enterQcEditMode(); if (window.lucide) lucide.createIcons(); });
    on('qc-cancel-btn', 'click', () => {
        if (qcDirty && !confirm('Discard unsaved edits?')) return;
        exitQcEditMode();
    });
    on('qc-rotate-left', 'click', () => { if (!qcEditMode) return; qcRotation = (qcRotation + 270) % 360; qcRenderCanvas(); qcMarkDirty(); });
    on('qc-rotate-right', 'click', () => { if (!qcEditMode) return; qcRotation = (qcRotation + 90) % 360; qcRenderCanvas(); qcMarkDirty(); });
    on('qc-enhance-toggle', 'click', () => { document.getElementById('qc-enhance-panel').classList.toggle('hidden'); });
    on('qc-enhance-reset', 'click', () => {
        qcBrightness = 100; qcContrast = 100;
        document.getElementById('qc-brightness').value = 100;
        document.getElementById('qc-contrast').value = 100;
        document.getElementById('qc-brightness-val').textContent = '100%';
        document.getElementById('qc-contrast-val').textContent = '100%';
        qcRenderCanvas(); qcMarkDirty();
    });
    on('qc-brightness', 'input', (e) => { qcBrightness = parseInt(e.target.value, 10); document.getElementById('qc-brightness-val').textContent = qcBrightness + '%'; qcRenderCanvas(); qcMarkDirty(); });
    on('qc-contrast', 'input', (e) => { qcContrast = parseInt(e.target.value, 10); document.getElementById('qc-contrast-val').textContent = qcContrast + '%'; qcRenderCanvas(); qcMarkDirty(); });

    on('qc-replace-btn', 'click', () => { const i = document.getElementById('qc-replace-input'); if (i) i.click(); });
    on('qc-replace-input', 'change', (e) => {
        const file = e.target.files && e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (ev) => {
            qcRotation = 0;
            qcBaseImage = new Image();
            qcBaseImage.onload = () => { qcRenderCanvas(); document.getElementById('qc-canvas').classList.remove('hidden'); qcMarkDirty(); };
            qcBaseImage.src = ev.target.result;
        };
        reader.readAsDataURL(file);
        e.target.value = '';
    });

    on('qc-crop-toggle', 'click', qcStartCropMode);
    on('qc-crop-apply', 'click', qcApplyCrop);
    on('qc-crop-cancel', 'click', qcCancelCrop);

    const canvasEl = document.getElementById('qc-canvas');
    if (canvasEl) canvasEl.addEventListener('mousedown', qcCropMouseDown);
    document.addEventListener('mousemove', qcCropMouseMove);
    document.addEventListener('mouseup', qcCropMouseUp);

    on('qc-save-btn', 'click', qcSave);
    on('qc-delete-btn', 'click', qcDeletePage);
    on('qc-pass-btn', 'click', () => qcSetStatus('passed'));
    on('qc-fail-btn', 'click', () => qcSetStatus('failed'));
});
</script>

<script>
/* ============================================================
 * Edit File Type — re-classify the current page from the viewer.
 * Reuses the same reference data + page-code format as pagetyping,
 * saving through /filearchive/pages/{id}/classification. It never
 * renames the stored file; only the classification columns change.
 * ============================================================ */
(function () {
    const dialog = document.getElementById('edit-filetype-dialog');
    if (!dialog) return;

    let typingData = null;              // { coverTypes, pageTypes, pageSubTypes }
    let typingDataPromise = null;
    let editingPageIndex = null;

    const $ = (id) => document.getElementById(id);
    const csrf = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };
    const isOther = (v) => ['other', 'others', 'oth'].includes(String(v).toLowerCase());
    const otherCode = (text) => (String(text || '').trim().substring(0, 4).toUpperCase() || 'OTH');

    async function loadTypingData() {
        if (typingData) return typingData;
        if (typingDataPromise) return typingDataPromise;

        typingDataPromise = fetch(dialog.dataset.typingDataUrl, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.message || 'Failed to load classification data');
                typingData = {
                    coverTypes: (data.cover_types || []).map(c => ({ id: String(c.id ?? c.Id), name: c.name ?? c.Name, code: c.code ?? c.Code ?? 'CV' })),
                    pageTypes: (data.page_types || []).map(p => ({ id: String(p.id ?? p.Id), name: p.name ?? p.Name, code: p.code ?? p.Code ?? 'PT' })),
                    pageSubTypes: {}
                };
                const raw = data.page_sub_types || {};
                Object.keys(raw).forEach(k => {
                    typingData.pageSubTypes[String(k)] = (raw[k] || []).map(s => ({ id: String(s.id ?? s.Id), name: s.name ?? s.Name, code: s.code ?? s.Code ?? 'ST' }));
                });
                return typingData;
            });
        return typingDataPromise;
    }

    function coverById(id) { return typingData.coverTypes.find(c => c.id == id); }
    function pageTypeById(id) { return typingData.pageTypes.find(p => p.id == id); }
    function subTypeById(typeId, subId) { return (typingData.pageSubTypes[String(typeId)] || []).find(s => s.id == subId); }

    function fillSelect(select, items, placeholder) {
        select.innerHTML = `<option value="">${placeholder}</option>`;
        items.forEach(it => {
            const opt = document.createElement('option');
            opt.value = it.id;
            opt.textContent = `${it.name} (${it.code})`;
            select.appendChild(opt);
        });
    }

    function populateSubtypes(typeId, selectedSubId) {
        const subs = typingData.pageSubTypes[String(typeId)] || [];
        fillSelect($('eft-page-subtype'), subs, 'Select page subtype');
        if (selectedSubId != null) $('eft-page-subtype').value = String(selectedSubId);
    }

    function toggleOther(selectId, inputId) {
        const input = $(inputId);
        if (isOther($(selectId).value)) {
            input.classList.remove('hidden');
        } else {
            input.classList.add('hidden');
        }
    }

    function updatePreview() {
        const page = documentPages[editingPageIndex] || {};
        const coverCode = coverById($('eft-cover-type').value)?.code || 'XX';

        const ptVal = $('eft-page-type').value;
        const pageCode = ptVal
            ? (isOther(ptVal) ? otherCode($('eft-page-type-other').value) : (pageTypeById(ptVal)?.code || 'XX'))
            : 'XX';

        const stVal = $('eft-page-subtype').value;
        let subCode = '';
        if (stVal) {
            subCode = isOther(stVal) ? otherCode($('eft-page-subtype-other').value) : (subTypeById(ptVal, stVal)?.code || 'XX');
        }

        const serial = ($('eft-serial').value || '').trim();
        const code = `${coverCode}-${pageCode}${subCode ? '-' + subCode : ''}-${serial}`;

        const position = page.definition != null ? page.definition : page.page_number;
        $('eft-code-preview').textContent = code;
        $('eft-definition-preview').textContent = `${position ?? ''}-${code}`;
    }

    async function openModal() {
        if (typeof documentPages === 'undefined' || !documentPages.length) {
            alert('Open a document and select a page first.');
            return;
        }
        editingPageIndex = currentPageIndex;
        const page = documentPages[editingPageIndex];
        if (!page || !page.pagetyping_id) {
            alert('This page cannot be re-classified.');
            return;
        }

        try {
            await loadTypingData();
        } catch (e) {
            alert('Could not load classification options: ' + e.message);
            return;
        }

        // Populate selects
        fillSelect($('eft-cover-type'), typingData.coverTypes, 'Select cover type');
        fillSelect($('eft-page-type'), typingData.pageTypes, 'Select page type');

        // Pre-select existing classification
        if (page.cover_type && page.cover_type.id != null) $('eft-cover-type').value = String(page.cover_type.id);

        const pt = page.page_type || {};
        const knownType = pt.id != null && pageTypeById(pt.id);
        if (knownType) {
            $('eft-page-type').value = String(pt.id);
            $('eft-page-type-other').value = '';
        } else if (pt.id != null && String(pt.id) !== '') {
            $('eft-page-type').value = 'other';
            $('eft-page-type-other').value = pt.name && pt.name !== 'Unknown Type' ? pt.name : String(pt.id);
        }
        toggleOther('eft-page-type', 'eft-page-type-other');

        // Subtypes depend on the chosen type
        const activeTypeId = $('eft-page-type').value;
        populateSubtypes(activeTypeId, null);
        const st = page.page_subtype;
        if (st && st.id != null) {
            const knownSub = subTypeById(activeTypeId, st.id);
            if (knownSub) {
                $('eft-page-subtype').value = String(st.id);
                $('eft-page-subtype-other').value = '';
            } else {
                $('eft-page-subtype').value = 'other';
                $('eft-page-subtype-other').value = st.name && st.name !== 'Unknown Subtype' ? st.name : String(st.id);
            }
        }
        toggleOther('eft-page-subtype', 'eft-page-subtype-other');

        $('eft-serial').value = page.serial_number != null ? String(page.serial_number) : '';
        $('eft-serial-auto').textContent = page.serial_number != null ? String(page.serial_number) : '0';
        $('eft-page-label').textContent = 'Page ' + (page.page_number ?? (editingPageIndex + 1));
        $('eft-file-number').textContent = (currentFileMeta && currentFileMeta.file_number) ? currentFileMeta.file_number : (document.getElementById('viewer-file-number')?.textContent || '-');

        updatePreview();

        dialog.style.display = 'flex';
        dialog.setAttribute('aria-hidden', 'false');
        if (window.lucide) lucide.createIcons();
    }

    function closeModal() {
        dialog.style.display = 'none';
        dialog.setAttribute('aria-hidden', 'true');
        editingPageIndex = null;
    }

    async function save() {
        const page = documentPages[editingPageIndex];
        if (!page || !page.pagetyping_id) return;

        const coverVal = $('eft-cover-type').value;
        const ptVal = $('eft-page-type').value;
        const stVal = $('eft-page-subtype').value;
        const serial = ($('eft-serial').value || '').trim();

        if (!coverVal) { alert('Please select a cover type.'); return; }
        if (!ptVal) { alert('Please select a page type.'); return; }
        if (isOther(ptVal) && !$('eft-page-type-other').value.trim()) { alert('Please specify the custom page type.'); return; }
        if (stVal && isOther(stVal) && !$('eft-page-subtype-other').value.trim()) { alert('Please specify the custom page subtype.'); return; }
        if (serial === '' || isNaN(parseInt(serial, 10))) { alert('Please enter a numeric serial number.'); return; }

        updatePreview();
        const pageCode = $('eft-code-preview').textContent;

        const payload = {
            cover_type_id: coverVal,
            page_type: ptVal,
            page_type_other: isOther(ptVal) ? $('eft-page-type-other').value.trim() : null,
            page_subtype: stVal || null,
            page_subtype_other: (stVal && isOther(stVal)) ? $('eft-page-subtype-other').value.trim() : null,
            serial_number: parseInt(serial, 10),
            page_code: pageCode,
            registry: $('eft-registry').value || null
        };

        const url = dialog.dataset.saveUrl.replace('__ID__', page.pagetyping_id);
        const saveBtn = $('edit-filetype-save');
        const label = saveBtn.querySelector('span');
        saveBtn.disabled = true;
        if (label) label.textContent = 'Saving…';

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Save failed');

            // Refresh in-memory page + re-render the list/detail in place.
            page.page_code = data.page_code;
            page.serial_number = data.serial_number;
            if (data.cover_type) page.cover_type = data.cover_type;
            if (data.page_type) page.page_type = data.page_type;
            page.page_subtype = data.page_subtype || null;

            if (typeof renderPagesList === 'function') renderPagesList();
            if (typeof selectPage === 'function') selectPage(editingPageIndex);

            closeModal();
            if (typeof qcToast === 'function') qcToast('Page classification updated.');
        } catch (err) {
            alert('Unable to save: ' + err.message);
        } finally {
            saveBtn.disabled = false;
            if (label) label.textContent = 'Save Classification';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const btn = $('edit-filetype-toggle');
        if (btn) btn.addEventListener('click', openModal);
        $('edit-filetype-close')?.addEventListener('click', closeModal);
        $('edit-filetype-cancel')?.addEventListener('click', closeModal);
        $('edit-filetype-save')?.addEventListener('click', save);

        dialog.addEventListener('click', (e) => { if (e.target === dialog) closeModal(); });

        $('eft-cover-type')?.addEventListener('change', updatePreview);
        $('eft-serial')?.addEventListener('input', updatePreview);
        $('eft-page-type')?.addEventListener('change', function () {
            toggleOther('eft-page-type', 'eft-page-type-other');
            populateSubtypes(this.value, null);
            toggleOther('eft-page-subtype', 'eft-page-subtype-other');
            updatePreview();
        });
        $('eft-page-subtype')?.addEventListener('change', function () {
            toggleOther('eft-page-subtype', 'eft-page-subtype-other');
            updatePreview();
        });
        $('eft-page-type-other')?.addEventListener('input', updatePreview);
        $('eft-page-subtype-other')?.addEventListener('input', updatePreview);
    });

    window.openEditFileTypeModal = openModal;
})();
</script>