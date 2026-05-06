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
                        </div>
                    </div>

                    <div class="flex-1 overflow-auto flex items-center justify-center bg-gray-100 p-4">
                        <div id="current-page-content" class="bg-white shadow-md rounded-md max-w-[900px] w-full mx-auto transition-transform" style="transform: scale(1) rotate(0deg);">
                            <div class="relative h-full w-full flex items-center justify-center">
                                <img src="" alt="Document page" id="document-image" class="w-full max-h-[85vh] object-contain hidden">
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

<script>
let documentPages = [];
let currentFileMeta = null;
let currentPageIndex = 0;
let zoomLevel = 100;
let rotation = 0;

const thumbnailPlaceholder = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none"><rect width="64" height="64" rx="8" fill="#F3F4F6"/><path d="M20 18h24v28H20z" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M24 26h16M24 32h10" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round"/></svg>');

function clearDocumentViewerData() {
    documentPages = [];
    currentFileMeta = null;
    currentPageIndex = 0;
    zoomLevel = 100;
    rotation = 0;
}

window.clearDocumentViewerData = clearDocumentViewerData;

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
</script>