<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
  $(document).ready(function () {
    console.log('[File Archive] Document ready, initializing');
    lucide.createIcons();

    const $viewerDialog = $('#document-viewer-dialog');
    console.log('[File Archive] $viewerDialog found:', $viewerDialog.length > 0, 'Element:', $viewerDialog[0]);
    let documentRequestToken = 0;
    const statusColorClasses = ['bg-green-600', 'bg-red-600'];
    const defaultHeaderState = buildRegistryState();

    $('.dialog-backdrop').hide();

    initFileTrackerStatusColors();

    $('.file-card').on('click', function () {
      const $card = $(this);
      const pagesUrl = $card.attr('data-pages-url');
      const meta = {
        number: $card.attr('data-file-number') || '-',
        title: $card.attr('data-file-title') || '-'
      };
      if (!pagesUrl) {
        console.warn('No document pages URL found for file card.');
        return;
      }

      openDocumentViewer(pagesUrl, false, meta);
    });

    $('.dialog-backdrop').on('click', function (e) {
      if ($(e.target).hasClass('dialog-backdrop')) {
        $(this).fadeOut('fast');
      }
    });

    $('#close-details, #close-viewer').on('click', function () {
      $(this).closest('.dialog-backdrop').fadeOut('fast');
    });

    $('#toggle-star').on('click', function () {
      $(this).find('i').toggleClass('fill-yellow-400 text-yellow-400');
    });

    function initFileTrackerStatusColors() {
      const $cards = $('.file-card');
      if (!$cards.length) {
        return;
      }

      const fileNumbers = [...new Set(
        $cards.map(function () {
          const value = $(this).data('fileNumber');
          return typeof value === 'string' ? value.trim() : '';
        })
          .get()
          .filter(function (value) {
            return value.length > 0;
          })
      )];

      if (!fileNumbers.length) {
        return;
      }

      fetchFileTrackerStatuses(fileNumbers)
        .done(function (response) {
          const statusMap = buildStatusMapFromResponse(response);
          applyTrackerStatuses($cards, statusMap);
        })
        .fail(function (error) {
          console.error('Failed to fetch file tracker statuses', error);
        });
    }

    function fetchFileTrackerStatuses(fileNumbers) {
      return $.ajax({
        url: '/api/file-trackers',
        method: 'GET',
        data: {
          'file_numbers': fileNumbers,
          'per_page': Math.max(fileNumbers.length, 15),
          'sort_by': 'updated_at',
          'sort_order': 'desc'
        },
        cache: false
      });
    }

    function buildStatusMapFromResponse(response) {
      const trackers = normalizeTrackerArray(response);
      return trackers.reduce(function (acc, tracker) {
        if (!tracker || !tracker.file_number) {
          return acc;
        }

        const normalizedFileNumber = normalizeFileNumber(tracker.file_number);
        const derivedStatus = deriveTrackerStatus(tracker);

        if (normalizedFileNumber && derivedStatus) {
          acc[normalizedFileNumber] = derivedStatus;
        }

        return acc;
      }, {});
    }

    function normalizeFileNumber(value) {
      if (typeof value !== 'string') {
        return '';
      }

      return value.trim().toUpperCase();
    }

    function normalizeTrackerArray(response) {
      if (!response) {
        return [];
      }

      const payload = response.data || response;

      if (Array.isArray(payload)) {
        return payload;
      }

      if (payload && Array.isArray(payload.data)) {
        return payload.data;
      }

      return [];
    }

    function getLatestMovementEntry(movementLog) {
      if (!Array.isArray(movementLog) || !movementLog.length) {
        return null;
      }

      return movementLog.reduce(function (latest, entry) {
        if (!entry) {
          return latest;
        }

        if (!latest) {
          return entry;
        }

        const currentTimestamp = parseMovementTimestamp(entry);
        const latestTimestamp = parseMovementTimestamp(latest);

        return currentTimestamp >= latestTimestamp ? entry : latest;
      }, null);
    }

    function parseMovementTimestamp(entry) {
      if (!entry) {
        return 0;
      }

      if (entry.timestamp) {
        const parsedTimestamp = Date.parse(entry.timestamp);
        if (!Number.isNaN(parsedTimestamp)) {
          return parsedTimestamp;
        }
      }

      const datePart = entry.log_in_date || entry.log_out_date;
      if (datePart) {
        const timePart = entry.log_in_time || entry.log_out_time || '00:00';
        const parsedDate = Date.parse(`${datePart}T${timePart}`);
        if (!Number.isNaN(parsedDate)) {
          return parsedDate;
        }
      }

      return 0;
    }

    function deriveTrackerStatus(tracker) {
      if (!tracker) {
        return buildRegistryState();
      }

      const trackerStatus = String(tracker.status || '').trim().toUpperCase();
      const movementLog = Array.isArray(tracker.movement_log) ? tracker.movement_log : [];
      const hasOpenMovement = movementLog.some(function (entry) {
        return isOpenMovementEntry(entry);
      });

      if (hasOpenMovement) {
        return buildTransitState();
      }

      const latestMovement = getLatestMovementEntry(movementLog);
      if (latestMovement && isOpenMovementEntry(latestMovement)) {
        return buildTransitState();
      }

      if (trackerStatus === 'ACTIVE') {
        const currentMovement = tracker.current_movement;
        if (currentMovement && isOpenMovementEntry(currentMovement)) {
          return buildTransitState();
        }
      }

      return buildRegistryState();
    }

    function isOpenMovementEntry(entry) {
      if (!entry) {
        return false;
      }

      const status = String(entry.status || '').trim().toUpperCase();
      const hasLoggedIn = Boolean(entry.log_in_time || entry.log_in_date);
      const hasLoggedOut = Boolean(entry.log_out_time || entry.log_out_date);

      if (hasLoggedIn && !hasLoggedOut) {
        return true;
      }

      return ['ACTIVE', 'PENDING_ACCEPTANCE', 'IN_PROGRESS'].includes(status);
    }

    function buildTransitState() {
      return {
        colorClass: 'bg-red-600',
        label: 'File in Transit',
        status: 'in_transit'
      };
    }

    function buildRegistryState() {
      return {
        colorClass: 'bg-green-600',
        label: 'File in the Registry',
        status: 'registry'
      };
    }

    function applyTrackerStatuses($cards, statusMap) {
      $cards.each(function () {
        const $card = $(this);
        const fileNumber = normalizeFileNumber($card.data('fileNumber'));
        const $header = $card.find('.file-card-status-header');

        if (!$header.length) {
          return;
        }

        const mapping = statusMap[fileNumber];
        updateHeaderColor($header, mapping);
      });
    }

    function updateHeaderColor($header, mapping) {
      const appliedState = mapping || defaultHeaderState;
      $header.removeClass(statusColorClasses.join(' '));
      $header.addClass(appliedState.colorClass);

      if (appliedState.label) {
        $header.attr('data-status-label', appliedState.label);
      } else {
        $header.removeAttr('data-status-label');
      }

      if (appliedState.status) {
        $header.attr('data-file-status', appliedState.status);
      } else {
        $header.removeAttr('data-file-status');
      }
    }

    function openDocumentViewer(pagesUrl, triggeredFromDetails = false, meta = null) {
      console.log('[Document Viewer] openDocumentViewer called with:', { pagesUrl, triggeredFromDetails, meta });
      const requestToken = ++documentRequestToken;
      console.log('[Document Viewer] Request token:', requestToken);

      resetDocumentViewerState(true, meta);
      console.log('[Document Viewer] Viewer state reset');
      
      console.log('[Document Viewer] Attempting to show modal. $viewerDialog:', $viewerDialog, '$viewerDialog.length:', $viewerDialog.length);
      $viewerDialog.fadeIn('fast');
      console.log('[Document Viewer] Modal fadeIn initiated');

      console.log('[Document Viewer] Starting AJAX request to:', pagesUrl);
      $.ajax({
        url: pagesUrl,
        method: 'GET',
        data: { _: Date.now() },
        cache: false
      })
        .done(function (response) {
          console.log('[Document Viewer] AJAX success. Response:', response);
          if (documentRequestToken !== requestToken) {
            console.warn('[Document Viewer] Request token mismatch, ignoring response');
            return;
          }

          if (response.success) {
            console.log('[Document Viewer] Response success. Loading', response.pages?.length || 0, 'pages');
            loadDocumentPages(response.file, response.pages || []);
            $('#viewer-file-number').text(response.file?.file_number || (meta?.number ?? '-'));
            $('#viewer-file-title').text(response.file?.file_title || (meta?.title ?? '-'));
            if (triggeredFromDetails) {
              $('#file-details-dialog').fadeOut('fast');
            }
          } else {
            console.error('[Document Viewer] Response not successful:', response.message);
            showViewerError(response.message || 'Unable to load document pages.');
          }
        })
        .fail(function (xhr) {
          console.error('[Document Viewer] AJAX failed. Status:', xhr.status, 'StatusText:', xhr.statusText, 'Response:', xhr.responseText);
          if (documentRequestToken !== requestToken) {
            console.warn('[Document Viewer] Request token mismatch on fail, ignoring');
            return;
          }

          showViewerError('Failed to load document pages. Status: ' + xhr.status);
        });
    }

    function resetDocumentViewerState(isLoading = false, meta = null) {
      if (typeof window.clearDocumentViewerData === 'function') {
        window.clearDocumentViewerData();
      }

      $('#pages-list').html(isLoading ? '<div class="p-4 text-sm text-gray-500">Loading document pages...</div>' : '');
      $('#page-info').html(`<span class="font-medium">${isLoading ? 'Loading document...' : 'Select a page'}</span>`);
      $('#page-indicator').text('Page 0 of 0');

      if (meta) {
        $('#viewer-file-number').text(meta.number || '-');
        $('#viewer-file-title').text(meta.title || '-');
      } else {
        $('#viewer-file-number').text('-');
        $('#viewer-file-title').text('-');
      }

      $('#document-image').attr('src', '').addClass('hidden');
      $('#document-pdf').attr('src', '').addClass('hidden');
      $('#document-placeholder').css('display', 'none');

      if (typeof updateTransform === 'function') {
        updateTransform();
      }
    }

    function showViewerError(message) {
      if (typeof window.clearDocumentViewerData === 'function') {
        window.clearDocumentViewerData();
      }
      $('#pages-list').html(`<div class="p-4 text-sm text-red-600">${message}</div>`);
      $('#page-info').html(`<span class="text-red-600">${message}</span>`);
      $('#page-indicator').text('Page 0 of 0');
      $('#viewer-file-number').text('-');
      $('#viewer-file-title').text('-');
      $('#document-image').attr('src', '').addClass('hidden');
      $('#document-pdf').attr('src', '').addClass('hidden');
      $('#document-placeholder').css('display', 'flex');
      const placeholderText = $('#document-placeholder').find('p');
      if (placeholderText.length) {
        placeholderText.text(message);
      }
    }

    window.openDocumentViewer = openDocumentViewer;
    console.log('[File Archive] window.openDocumentViewer exported:', typeof window.openDocumentViewer);
  });
</script>