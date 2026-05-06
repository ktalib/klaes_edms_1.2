
  // Lookup data from DB
  const dbLandUseOptions = [];
  const dbDistrictOptions = [];

  // Mock data for the application
  const monthlyData = [
    { month: "Jan", searches: 18, revenue: 270000 },
    { month: "Feb", searches: 22, revenue: 330000 },
    { month: "Mar", searches: 25, revenue: 375000 },
    { month: "Apr", searches: 20, revenue: 300000 },
    { month: "May", searches: 28, revenue: 420000 },
    { month: "Jun", searches: 32, revenue: 480000 },
    { month: "Jul", searches: 35, revenue: 525000 },
    { month: "Aug", searches: 30, revenue: 450000 },
    { month: "Sep", searches: 26, revenue: 390000 },
    { month: "Oct", searches: 22, revenue: 330000 },
    { month: "Nov", searches: 20, revenue: 300000 },
    { month: "Dec", searches: 24, revenue: 360000 }
  ];

  // Helper to generate registration numbers in XX/XX/YYY format
  function generateRegNumber() {
    const prefix = Math.floor(Math.random() * 90) + 10; // 10-99
    const suffix = Math.floor(Math.random() * 300) + 1; // 1-300
    return `${prefix}/${prefix}/${suffix}`;
  }

  // DOM Elements
  const searchModal = document.getElementById('search-modal');
  const searchRecordsBtn = document.getElementById('search-records-btn');
  const filterSelector = document.getElementById('filter-selector');
  const filtersContainer = document.getElementById('filters-container');
  const toggleFiltersBtn = document.getElementById('toggle-filters-btn');
  const filtersChevron = document.getElementById('filters-chevron');
  const filtersPanel = document.getElementById('filters-panel');
  const resetSearchBtn = document.getElementById('reset-search-btn');
  const searchLoading = document.getElementById('search-loading');
  const noResultsMessage = document.getElementById('no-results-message');
  const tableResults = document.getElementById('table-results');
  const tableResultsBody = document.getElementById('table-results-body');
  const cardResults = document.getElementById('card-results');
  const fileDetailsView = document.getElementById('file-details-view');
  const resultsCount = document.getElementById('results-count');
  const viewTabs = document.querySelectorAll('.tab');
  const dashboardView = document.getElementById('dashboard-view');
  const fileHistoryView = document.getElementById('file-history-view');
  const reportsView = document.getElementById('reports-view');

  const deleteConfirmDialog = document.getElementById('delete-confirm-dialog');
  const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
  const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
  const newSearchFromDetailsBtn = document.getElementById('new-search-from-details-btn');
  const legalSearchReportView = document.getElementById('legal-search-report-view');
  const backToFileDetailsBtn = document.getElementById('back-to-file-details-btn');
  const printReportBtn = document.getElementById('print-report-btn');
  const fileNumberInput = document.getElementById('fileNumber');
  const openFilenoSelectorBtn = document.getElementById('open-fileno-selector-btn');
  const clearFilenoBtn = document.getElementById('clear-fileno-btn');
  const searchNowBtn = document.getElementById('search-now-btn');
  const filenoInfoBadge = document.getElementById('fileno-info-badge');
  const filenoTypeLabel = document.getElementById('fileno-type-label');
  const filenoOwnerLabel = document.getElementById('fileno-owner-label');
  const activeFilterCount = document.getElementById('active-filter-count');
  // Filter label map for display
  const filterLabelMap = {
    guarantorName: 'Party 1',
    guaranteeName: 'Party 2',
    lga: 'LGA',
    district: 'District',
    location: 'Location',
    plotNumber: 'Plot Number',
    planNumber: 'Plan Number',
    size: 'Size',
    caveat: 'Caveat'
  };

  // Debug mode elements
  const toggleDebugBtn = document.getElementById('toggle-debug-btn');
  const sourceBadges = document.getElementById('source-badges');
  let lastApiResponse = null;

  // Debug statements
  console.log("Search modal element:", searchModal);
  console.log("Search records button:", searchRecordsBtn);
  console.log("Search modal class list:", searchModal ? searchModal.classList : "Modal not found");

  // Add document ready event to ensure DOM is fully loaded
  document.addEventListener('DOMContentLoaded', () => {
    console.log("DOM fully loaded, initializing search elements");

    // Initialize GlobalFileNoModal
    if (typeof GlobalFileNoModal !== 'undefined' && typeof GlobalFileNoModal.init === 'function') {
      GlobalFileNoModal.init();
      console.log('GlobalFileNoModal initialized for Legal Search');
    }
    
    // Re-query elements to ensure they're available
    const searchModalRecheck = document.getElementById('search-modal');
    const searchRecordsBtnRecheck = document.getElementById('search-records-btn');
    
    console.log("Search modal element (recheck):", searchModalRecheck);
    console.log("Search records button (recheck):", searchRecordsBtnRecheck);
    
    // Add click handler directly to the button element
    if (searchRecordsBtnRecheck) {
      searchRecordsBtnRecheck.onclick = function() {
        console.log("Search records button clicked via direct onclick");
        if (searchModalRecheck) {
          searchModalRecheck.classList.remove('hidden');
          console.log("Modal hidden class removed, current classes:", searchModalRecheck.classList);
        } else {
          console.error("Search modal element not found when trying to show it");
        }
      };
    }
  });

  // State variables
  let currentView = 'table';
  let selectedFile = null;
  let transactionToDelete = null;
  let searchResults = [];

  // Initialize the search trends chart
  const initializeChart = () => {
    const ctx = document.getElementById('searchTrendsChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: monthlyData.map(d => d.month),
        datasets: [{
          label: 'Searches',
          data: monthlyData.map(d => d.searches),
          borderColor: '#3B82F6',
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'top',
          },
          title: {
            display: true,
            text: 'Monthly Search Volume'
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Number of Searches'
            }
          },
          x: {
            title: {
              display: true,
              text: 'Month'
            }
          }
        }
      }
    });
  };

  // Initialize the chart when the page loads
  document.addEventListener('DOMContentLoaded', initializeChart);

  // Event Listeners
  if (searchRecordsBtn) {
    searchRecordsBtn.addEventListener('click', () => {
      console.log("Search records button clicked");
      searchModal.classList.remove('hidden');
    });
  } else {
    console.error("Search records button not found");
  }

  if (newSearchFromDetailsBtn) {
    newSearchFromDetailsBtn.addEventListener('click', () => {
      console.log("New search from details button clicked");
      searchModal.classList.remove('hidden');
    });
  }

  // Close modal when clicking outside
  if (searchModal) {
    searchModal.addEventListener('click', (e) => {
      if (e.target === searchModal) {
        searchModal.classList.add('hidden');
      }
    });
  }

  // ── Dropdown-based Additional Filters ─────────────────────────
  const activeFilters = new Set();

  const updateFilterCount = () => {
    const count = activeFilters.size;
    if (activeFilterCount) {
      if (count > 0) {
        activeFilterCount.textContent = count;
        activeFilterCount.classList.remove('hidden');
      } else {
        activeFilterCount.classList.add('hidden');
      }
    }
  };

  const addFilterRow = (filterId) => {
    if (activeFilters.has(filterId)) return;
    const tpl = document.getElementById('tpl-' + filterId);
    if (!tpl) return;

    activeFilters.add(filterId);

    // Hide option in selector
    const opt = filterSelector.querySelector('option[value="' + filterId + '"]');
    if (opt) opt.disabled = true;
    filterSelector.value = '';

    // Build grid cell
    const row = document.createElement('div');
    row.className = 'flex flex-col gap-1';
    row.setAttribute('data-filter-row', filterId);

    // Header: label + remove button
    const header = document.createElement('div');
    header.className = 'flex items-center justify-between';

    const label = document.createElement('span');
    label.className = 'text-xs font-medium text-gray-500';
    label.textContent = filterLabelMap[filterId] || filterId;
    header.appendChild(label);

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'p-0.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition';
    removeBtn.title = 'Remove filter';
    removeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
    removeBtn.addEventListener('click', () => removeFilterRow(filterId));
    header.appendChild(removeBtn);

    row.appendChild(header);

    const clone = tpl.content.cloneNode(true);
    // Make input/select full width inside cell
    const el = clone.querySelector('input, select');
    if (el) { el.classList.remove('flex-1'); el.classList.add('w-full'); }
    row.appendChild(clone);

    filtersContainer.appendChild(row);
    updateFilterCount();

    // Focus the new input
    const input = row.querySelector('input, select');
    if (input) input.focus();
  };

  const removeFilterRow = (filterId) => {
    const row = filtersContainer.querySelector('[data-filter-row="' + filterId + '"]');
    if (row) row.remove();
    activeFilters.delete(filterId);

    // Re-enable option in selector
    const opt = filterSelector.querySelector('option[value="' + filterId + '"]');
    if (opt) opt.disabled = false;

    updateFilterCount();
  };

  const removeAllFilterRows = () => {
    filtersContainer.innerHTML = '';
    activeFilters.clear();
    // Re-enable all options
    filterSelector.querySelectorAll('option').forEach(opt => opt.disabled = false);
    filterSelector.value = '';
    updateFilterCount();
    // Collapse panel
    if (filtersPanel) filtersPanel.classList.add('hidden');
    if (filtersChevron) filtersChevron.classList.remove('rotate-90');
  };

  // Toggle Additional Filters panel
  if (toggleFiltersBtn && filtersPanel && filtersChevron) {
    toggleFiltersBtn.addEventListener('click', () => {
      const open = !filtersPanel.classList.contains('hidden');
      filtersPanel.classList.toggle('hidden', open);
      filtersChevron.classList.toggle('rotate-90', !open);
    });
  }

  if (filterSelector) {
    filterSelector.addEventListener('change', () => {
      const val = filterSelector.value;
      if (val) addFilterRow(val);
    });
  }

  // ── Global File Number Selector integration ──────────────────────
  if (openFilenoSelectorBtn) {
    openFilenoSelectorBtn.addEventListener('click', () => {
      if (typeof window.GlobalFileNoModal === 'undefined' || typeof window.GlobalFileNoModal.open !== 'function') {
        console.warn('GlobalFileNoModal not available, selector button disabled');
        return;
      }
      window.GlobalFileNoModal.open({
        autoPopulateGenericFields: false,
        callback: function(fileData) {
          if (fileData && fileData.fileNumber) {
            fileNumberInput.value = fileData.fileNumber;

            // Enable search button
            if (searchNowBtn) {
              searchNowBtn.disabled = false;
              searchNowBtn.classList.remove('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
              searchNowBtn.classList.add('bg-gray-900', 'text-white', 'hover:bg-gray-800');
            }

            // Show clear button
            if (clearFilenoBtn) clearFilenoBtn.classList.remove('hidden');

            // Show info badge
            if (filenoInfoBadge) {
              filenoInfoBadge.classList.remove('hidden');
              filenoInfoBadge.classList.add('flex');
              if (filenoTypeLabel) filenoTypeLabel.textContent = (fileData.tab || fileData.system || '').toUpperCase();
              if (filenoOwnerLabel && fileData.record) {
                const ownerName = fileData.record.file_name || fileData.record.FileName || fileData.record.applicant_name || fileData.record.owner_name || '';
                filenoOwnerLabel.textContent = ownerName ? '— ' + ownerName : '';
              }
            }

            // Auto-trigger search
            performSearch();
          }
        }
      });
    });
  }

  // Clear file number
  if (clearFilenoBtn) {
    clearFilenoBtn.addEventListener('click', () => {
      fileNumberInput.value = '';
      clearFilenoBtn.classList.add('hidden');
      if (filenoInfoBadge) {
        filenoInfoBadge.classList.add('hidden');
        filenoInfoBadge.classList.remove('flex');
      }
      // Disable search button
      if (searchNowBtn) {
        searchNowBtn.disabled = true;
        searchNowBtn.classList.remove('bg-gray-900', 'text-white', 'hover:bg-gray-800');
        searchNowBtn.classList.add('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
      }
    });
  }

  // Show/hide clear button on manual typing
  if (fileNumberInput) {
    fileNumberInput.addEventListener('input', () => {
      if (fileNumberInput.value.trim()) {
        if (clearFilenoBtn) clearFilenoBtn.classList.remove('hidden');
      } else {
        if (clearFilenoBtn) clearFilenoBtn.classList.add('hidden');
        if (filenoInfoBadge) {
          filenoInfoBadge.classList.add('hidden');
          filenoInfoBadge.classList.remove('flex');
        }
      }
    });
    // Search on Enter key
    fileNumberInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        performSearch();
      }
    });
  }

  // Search Now button
  if (searchNowBtn) {
    searchNowBtn.addEventListener('click', () => {
      performSearch();
    });
  }

  // Reset search
  const resetSearch = () => {
    // Clear file number
    document.getElementById('fileNumber').value = '';
    if (clearFilenoBtn) clearFilenoBtn.classList.add('hidden');
    if (filenoInfoBadge) {
      filenoInfoBadge.classList.add('hidden');
      filenoInfoBadge.classList.remove('flex');
    }
    // Disable search button
    if (searchNowBtn) {
      searchNowBtn.disabled = true;
      searchNowBtn.classList.remove('bg-gray-900', 'text-white', 'hover:bg-gray-800');
      searchNowBtn.classList.add('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
    }

    // Clear additional filters
    removeAllFilterRows();

    // Reset results
    searchResults = [];
    resultsCount.textContent = '0';
    tableResultsBody.innerHTML = '';
    cardResults.innerHTML = '';
    
    // Hide results views
    tableResults.classList.add('hidden');
    cardResults.classList.add('hidden');
    fileDetailsView.classList.add('hidden');
    noResultsMessage.classList.add('hidden');
    
    // Clear debug state
    if (sourceBadges) sourceBadges.classList.add('hidden');
    lastApiResponse = null;

    // Reset selected file
    selectedFile = null;
  };

  if (resetSearchBtn) {
    resetSearchBtn.addEventListener('click', resetSearch);
  }

  // Perform search based on filter values
  const performSearch = () => {
    // Get all filter values
    const filters = {
      fileNumber: document.getElementById('fileNumber').value,
      guarantorName: document.getElementById('guarantorName') ? document.getElementById('guarantorName').value : '',
      guaranteeName: document.getElementById('guaranteeName') ? document.getElementById('guaranteeName').value : '',
      lga: document.getElementById('lga') ? document.getElementById('lga').value : '',
      district: document.getElementById('district') ? document.getElementById('district').value : '',
      location: document.getElementById('location') ? document.getElementById('location').value : '',
      plotNumber: document.getElementById('plotNumber') ? document.getElementById('plotNumber').value : '',
      planNumber: document.getElementById('planNumber') ? document.getElementById('planNumber').value : '',
      size: document.getElementById('size') ? document.getElementById('size').value : '',
      caveat: document.getElementById('caveat') ? document.getElementById('caveat').value : ''
    };

    // Check if at least one search parameter has a value
    const hasSearchCriteria = Object.values(filters).some(value => value && value.trim() !== '');
    
    if (!hasSearchCriteria) {
      searchResults = [];
      resultsCount.textContent = '0';
      tableResults.classList.add('hidden');
      cardResults.classList.add('hidden');
      noResultsMessage.classList.add('hidden');
      return;
    }

    // Show loading
    searchLoading.classList.remove('hidden');
    tableResults.classList.add('hidden');
    cardResults.classList.add('hidden');
    noResultsMessage.classList.add('hidden');
    fileDetailsView.classList.add('hidden');

    // Prepare data for AJAX call
    const searchData = {
      _token: '{{ csrf_token() }}',
      query: filters.fileNumber || '',
      guarantorName: filters.guarantorName || '',
      guaranteeName: filters.guaranteeName || '',
      lga: filters.lga || '',
      district: filters.district || '',
      location: filters.location || '',
      plotNumber: filters.plotNumber || '',
      planNumber: filters.planNumber || '',
      size: filters.size || '',
      caveat: filters.caveat || ''
    };

    // AJAX call to the server
    $.ajax({
      url: '{{ route($moduleConfig["searchRouteName"] ?? "legalsearch.search") }}',
      type: 'POST',
      data: searchData,
      success: function(data) {
        // Hide loading
        searchLoading.classList.add('hidden');

        // Store raw response for debug view
        lastApiResponse = data;

        // The API returns a unified chronological array
        searchResults = data.transactions || [];

        // Attach file_indexings data to all results
        const _apiFileTitle = data.file_title || null;
        const _apiFileDistrict = data.file_district || null;
        const _apiFileLga = data.file_lga || null;
        const _apiFileLandUse = data.file_land_use || null;
        const _apiFilePlotNo = data.file_plot_number || null;
        const _apiFileTpNo = data.file_tp_no || null;
        const _apiRelatedFileno = data.file_related_fileno || null;
        const _apiIndexFileNumber = data.file_index_number || null;
        const _apiFileSize = data.file_size || null;
        searchResults.forEach(r => {
          if (_apiFileTitle) r._file_title = _apiFileTitle;
          if (_apiFileDistrict) r._file_district = _apiFileDistrict;
          if (_apiFileLga) r._file_lga = _apiFileLga;
          if (_apiFileLandUse) r._file_land_use = _apiFileLandUse;
          if (_apiFilePlotNo) r._file_plot_number = _apiFilePlotNo;
          if (_apiFileTpNo) r._file_tp_no = _apiFileTpNo;
          if (_apiRelatedFileno) r._file_related_fileno = _apiRelatedFileno;
          if (_apiIndexFileNumber) r._file_index_number = _apiIndexFileNumber;
          if (_apiFileSize) r._file_size = _apiFileSize;
        });

        console.log('=== STAGING TABLE SEARCH RESULTS ===');
        console.log('File History:', data.file_history_count);
        console.log('CofO:', data.cofo_count);
        console.log('PRA:', data.pra_count);
        console.log('Deed:', data.deed_count);
        console.log('Total:', data.total_count);
        console.log('Sample results:', searchResults.slice(0, 3));

        // Update results count
        resultsCount.textContent = searchResults.length;

        // Update source breakdown badges
        updateSourceBadges(data);

        // Show appropriate view
        if (searchResults.length === 0) {
          noResultsMessage.classList.remove('hidden');
        } else {
          // Update active filters summary
          const activeFilters = Object.entries(filters)
            .filter(([_, value]) => value && value.trim() !== '')
            .map(([key, value]) => {
              const filterLabels = {
                fileNumber: 'File Number',
                guarantorName: 'Party 1',
                guaranteeName: 'Party 2',
                lga: 'LGA',
                district: 'District',
                location: 'Location',
                plotNumber: 'Plot Number',
                planNumber: 'Plan Number',
                size: 'Size',
                caveat: 'Caveat'
              };
              return `${filterLabels[key]}: ${value}`;
            })
            .join(', ');
          
          document.getElementById('active-filters-summary').textContent = activeFilters;
          
          renderSearchResults();
        }

        // After a cleanup action (Drop/Match/Remove), re-render file history
        if (_pendingCleanupRefresh && fileHistoryView && !fileHistoryView.classList.contains('hidden')) {
          _pendingCleanupRefresh = false;
          if (searchResults.length > 0 && selectedFile) {
            selectedFile = searchResults[0];
            renderFileHistory();
          }
        }
      },
      error: function(error) {
        // Hide loading
        searchLoading.classList.add('hidden');
        noResultsMessage.classList.remove('hidden');
        console.error('Error performing search:', error);
      }
    });
  };

  // Helper function to remove .0 from values
  const cleanNumericValue = (value) => {
    if (!value || value === '-') return value;
    
    // Convert to string if it's a number
    let stringValue = value.toString();
    
    // Remove .0 from the end if present
    if (stringValue.endsWith('.0')) {
      stringValue = stringValue.replace('.0', '');
    }
    
    return stringValue;
  };

  // ─── Debug mode toggle ──────────────────────────────────────────
  if (toggleDebugBtn) {
    toggleDebugBtn.addEventListener('click', () => {
      if (!lastApiResponse || !lastApiResponse.transactions || lastApiResponse.transactions.length === 0) {
        alert('No search results to debug. Run a search first.');
        return;
      }
      openDebugTab(lastApiResponse);
    });
  }

  // ─── Source badges updater ──────────────────────────────────────
  const updateSourceBadges = (data) => {
    if (!sourceBadges) return;
    sourceBadges.classList.remove('hidden');

    const badges = [
      { id: 'badge-file-history', count: data.file_history_count || 0, label: 'FH' },
      { id: 'badge-cofo', count: data.cofo_count || 0, label: 'CofO' },
      { id: 'badge-pra', count: data.pra_count || 0, label: 'PRA' },
      { id: 'badge-deed', count: data.deed_count || 0, label: 'Deed' },
    ];

    badges.forEach(b => {
      const el = document.getElementById(b.id);
      if (el) {
        el.textContent = `${b.label}: ${b.count}`;
        el.classList.toggle('hidden', b.count === 0);
      }
    });
  };

  // ─── Open debug results in a new tab ────────────────────────────
  const openDebugTab = (data) => {
    const rows = data.transactions || [];
    const sourceColors = {
      'File History': '#eef2ff',
      'CofO': '#f0fdf4',
      'PRA': '#fffbeb',
      'Deed Registration': '#faf5ff',
    };

    let tableRows = '';
    rows.forEach((r, i) => {
      const bg = sourceColors[r.source_table] || '#fff';
      tableRows += `<tr style="background:${bg};border-bottom:1px solid #e5e7eb;">
        <td>${i + 1}</td>
        <td><b>${e(r.source_table)}</b></td>
        <td><b>${e(r.file_number)}</b></td>
        <td>${e(r.mlsFNo)}</td>
        <td>${e(r.fileno)}</td>
        <td>${e(r.kangisFileNo)}</td>
        <td>${e(r.NewKANGISFileno)}</td>
        <td>${e(r.transaction_type)}</td>
        <td style="white-space:nowrap">${e(r.transaction_date)}</td>
        <td>${e(r.sort_date)}</td>
        <td>${e(r.party_1)}</td>
        <td>${e(r.party_2)}</td>
        <td>${e(r.party_3)}</td>
        <td>${e(r.party_4)}</td>
        <td>${e(r.land_use)}</td>
        <td>${e(r.location)}</td>
        <td>${e(r.registration)}</td>
        <td>${e(r.regNo)}</td>
        <td>${e(r.serial_no)}</td>
        <td>${e(r.page_no)}</td>
        <td>${e(r.volume_no)}</td>
        <td>${e(r.plot_no)}</td>
        <td>${e(r.size)}</td>
        <td>${e(r.caveat)}</td>
        <td>${e(r.prop_id)}</td>
        <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis" title="${e(r.comments)}">${e(r.comments)}</td>
      </tr>`;
    });

    const html = `<!DOCTYPE html>
<html><head><meta charset="utf-8">
<title>DEV MODE — Raw API Response</title>
<style>
  body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 20px; background: #f9fafb; color: #111; }
  .header { background: #1f2937; color: #fff; padding: 16px 24px; border-radius: 8px; margin-bottom: 16px; }
  .header h1 { margin: 0 0 8px; font-size: 18px; }
  .stats { display: flex; gap: 12px; flex-wrap: wrap; }
  .stat { padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: 600; }
  .stat-fh { background: #eef2ff; color: #4338ca; }
  .stat-cofo { background: #f0fdf4; color: #15803d; }
  .stat-pra { background: #fffbeb; color: #b45309; }
  .stat-deed { background: #faf5ff; color: #7e22ce; }
  .stat-total { background: #f3f4f6; color: #111; }
  table { width: 100%; border-collapse: collapse; font-size: 12px; font-family: 'Cascadia Code', 'Fira Code', monospace, sans-serif; }
  th { background: #1f2937; color: #fff; padding: 6px 8px; text-align: left; position: sticky; top: 0; z-index: 1; white-space: nowrap; }
  td { padding: 4px 8px; vertical-align: top; }
  tr:hover td { background: #e5e7eb !important; }
  .na { color: #d1d5db; }
  .wrap { border-radius: 8px; overflow: auto; border: 1px solid #e5e7eb; background: #fff; max-height: calc(100vh - 140px); }
  .ts { font-size: 11px; color: #9ca3af; margin-top: 8px; }
</style>
</head><body>
<div class="header">
  <h1>DEV MODE — Legal Search Raw API Response (Chronological)</h1>
  <div class="stats">
    <span class="stat stat-total">Total: ${rows.length}</span>
    <span class="stat stat-fh">File History: ${data.file_history_count || 0}</span>
    <span class="stat stat-cofo">CofO: ${data.cofo_count || 0}</span>
    <span class="stat stat-pra">PRA: ${data.pra_count || 0}</span>
    <span class="stat stat-deed">Deed: ${data.deed_count || 0}</span>
  </div>
</div>
<div class="wrap">
<table>
<thead><tr>
  <th>#</th><th>Source</th><th>File Number</th><th>MLS FNo</th><th>FileNo</th>
  <th>KANGIS</th><th>NewKANGIS</th><th>Txn Type</th><th>Txn Date</th><th>Sort Date</th>
  <th>Party 1</th><th>Party 2</th><th>Party 3</th><th>Party 4</th><th>Land Use</th><th>Location</th>
  <th>Registration</th><th>RegNo</th><th>Serial</th><th>Page</th><th>Volume</th>
  <th>Plot No</th><th>Size</th><th>Caveat</th><th>Prop ID</th><th>Comments</th>
</tr></thead>
<tbody>${tableRows}</tbody>
</table>
</div>
<div class="ts">Generated: ${new Date().toLocaleString()}</div>
</body></html>`;

    const w = window.open('', '_blank');
    if (w) {
      w.document.write(html);
      w.document.close();
    } else {
      alert('Pop-up blocked. Please allow pop-ups for this site.');
    }
  };

  // HTML-escape helper for debug output
  const e = (val) => {
    if (val === null || val === undefined) return '<span class="na">null</span>';
    const str = String(val);
    if (str === '-') return '<span class="na">-</span>';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  };

  // Helper function to identify file number type by pattern
  const identifyFileNumberType = (value) => {
    if (!value || value === '-' || value === null || value === undefined) {
      return 'unknown';
    }
    
    const cleanValue = cleanNumericValue(value.toString().trim());
    const normalized = cleanValue.replace(/\//g, '-');
    
    // ST File Number patterns: ST-RES-2024-01-001, ST-COM-2024-02-002, ST-IND-2024-03-009
    if (/^ST-(RES|COM|IND|AG)-\d{4}-\d+-\d+$/i.test(normalized)) {
      return 'st';
    }
    
    // Parent File Number (NP) patterns: ST-RES-2024-01, ST-COM-2024-02, ST-IND-2024-03
    if (/^ST-(RES|COM|IND|AG)-\d{4}-\d+$/i.test(normalized)) {
      return 'parent';
    }
    
    // MLS File Number patterns: allow dash or slash separators
    if (/^(COM|RES|IND|AG|CON-COM|CON-RES|CON-AG|CON-IND)-\d{4}-\d+$/i.test(normalized) ||
        /^(COM|RES|IND|AG|CON-COM|CON-RES|CON-AG|CON-IND)-\d+$/i.test(normalized)) {
      return 'mls';
    }
    
    // KANGIS File Number patterns: allow 3-6 digits after 4-letter prefix
    if (/^[A-Z]{4}\s?\d{3,6}$/i.test(cleanValue)) {
      return 'kangis';
    }
    
    // New KANGIS File Number patterns: KN followed by 2-6 digits
    if (/^KN\d{2,6}$/i.test(cleanValue)) {
      return 'new_kangis';
    }
    
    return 'unknown';
  };

  // Helper function to extract correct file numbers from a file record - prefer backend-computed fields and broaden patterns
  const extractFileNumbers = (file) => {
    console.log('Extracting file numbers from:', file);
    
    const result = {
      st: '-',
      parent: '-',
      mls: '-',
      kangis: '-',
      new_kangis: '-'
    };
    
    // 1) Prefer backend-computed aliases when present
    if (file.STFileNo && file.STFileNo !== '-') {
      result.st = cleanNumericValue(file.STFileNo);
    }
    if (file.ParentFileNo && file.ParentFileNo !== '-') {
      result.parent = cleanNumericValue(file.ParentFileNo);
    }
    if (file.MLSFileNo && file.MLSFileNo !== '-') {
      result.mls = cleanNumericValue(file.MLSFileNo);
    }
    if (file.KANGISFileNo && file.KANGISFileNo !== '-') {
      result.kangis = cleanNumericValue(file.KANGISFileNo);
    }
    if (file.NewKANGISFileNo && file.NewKANGISFileNo !== '-') {
      result.new_kangis = cleanNumericValue(file.NewKANGISFileNo);
    }
    
    // 2) Fallbacks if aliases are missing depending on table
    if (result.st === '-') {
      const stValues = [file.StFileNo, file.st_file_no, file.sub_fileno].filter(v => v && v !== '-');
      if (stValues.length) result.st = cleanNumericValue(stValues[0]);
    }
    
    if (result.parent === '-') {
      const parentValues = [file.parent_fileNo, file.np_fileno, file.mother_np_fileno].filter(v => v && v !== '-');
      if (parentValues.length) result.parent = cleanNumericValue(parentValues[0]);
    }
    
    if (result.mls === '-') {
      const mlsValues = [file.mlsFNo, file.fileNo, file.fileno, file.mother_fileno].filter(v => v && v !== '-');
      if (mlsValues.length) result.mls = cleanNumericValue(mlsValues[0]);
    }
    
    if (result.kangis === '-') {
      const kangisValues = [file.kangisFileNo, file.KAGISFileNO].filter(v => v && v !== '-');
      if (kangisValues.length) result.kangis = cleanNumericValue(kangisValues[0]);
    }
    
    if (result.new_kangis === '-') {
      const newKangisValues = [file.NewKANGISFileno, file.new_kangis_file_no].filter(v => v && v !== '-');
      if (newKangisValues.length) result.new_kangis = cleanNumericValue(newKangisValues[0]);
    }
    
    // 3) As a last resort, scan all possible values and categorize by pattern
    if (result.st === '-' || result.parent === '-' || result.mls === '-' || result.kangis === '-' || result.new_kangis === '-') {
      const allPossibleValues = [
        file.STFileNo, file.StFileNo, file.st_file_no, file.sub_fileno,
        file.ParentFileNo, file.parent_fileNo, file.np_fileno, file.mother_np_fileno,
        file.MLSFileNo, file.mlsFNo, file.fileNo, file.fileno, file.mother_fileno,
        file.KANGISFileNo, file.kangisFileNo, file.KAGISFileNO,
        file.NewKANGISFileNo, file.NewKANGISFileno, file.new_kangis_file_no
      ].filter(val => val && val !== '-' && val !== null && val !== undefined);
      
      allPossibleValues.forEach(value => {
        const cleanValue = cleanNumericValue(value);
        const type = identifyFileNumberType(cleanValue);
        if (type !== 'unknown' && result[type] === '-') {
          result[type] = cleanValue;
        }
      });
    }
    
    console.log('Final extracted file numbers:', result);
    return result;
  };

  // Parse related_fileno text that may be stored like "['CON-COM-2014-82']"
  const parseRelatedFilenoValue = (raw) => {
    if (!raw) return '-';
    let text = cleanNumericValue(String(raw));
    if (!text || text === '-') return '-';

    text = text.replace(/^\[/, '').replace(/\]$/, '');
    const parts = text
      .split(/[,;|]/)
      .map(p => cleanNumericValue(p).replace(/^['\"]+|['\"]+$/g, '').trim())
      .filter(Boolean);

    if (!parts.length) return '-';
    const mlsCandidate = parts.find(p => identifyFileNumberType(p) === 'mls');
    return cleanNumericValue(mlsCandidate || parts[0]);
  };

  // Render search results based on current view
  const renderSearchResults = () => {
    if (currentView === 'table') {
      renderTableResults();
      tableResults.classList.remove('hidden');
      cardResults.classList.add('hidden');
    } else {
      renderCardResults();
      cardResults.classList.remove('hidden');
      tableResults.classList.add('hidden');
    }
  };

  // Render table results - UPDATED FOR NEW FILE NUMBER STRUCTURE AND COLUMN ORDER
  const renderTableResults = () => {
    tableResultsBody.innerHTML = '';
    
    searchResults.forEach((file, index) => {
      const fileNumbers = extractFileNumbers(file);
      const row = document.createElement('tr');
      row.className = 'hover:bg-gray-50 transition-colors';
      row.innerHTML = `
        <td class="p-2 text-sm">${fileNumbers.st}</td>
        <td class="p-2 text-sm">${fileNumbers.parent}</td>
        <td class="p-2 text-sm">${fileNumbers.mls}</td>
        <td class="p-2 text-sm">${fileNumbers.kangis}</td>
        <td class="p-2 text-sm">${fileNumbers.new_kangis}</td>
        <td class="p-2 text-sm">${toProperCase(file.party_1 || '-')}</td>
        <td class="p-2 text-sm">${toProperCase(file.party_2 || '-')}</td>
        <td class="p-2 text-sm">${toProperCase(file.party_3 || '-')}</td>
        <td class="p-2 text-sm">${toProperCase(getMappedValue(file, 'lga'))}</td>
        <td class="p-2 text-sm">${file.property_house_no && file.property_plot_no && file.property_street_name && file.property_district && file.property_lga ? toProperCase(`${file.property_house_no},${file.property_plot_no},${file.property_street_name},${file.property_district},${file.property_lga}`) : toProperCase(getMappedValue(file, 'location'))}</td>
        <td class="p-2 text-sm">${getMappedValue(file, 'plotNo')}</td>
        <td class="p-2 text-sm">${toProperCase(getMappedValue(file, 'transactionType'))}</td>
        <td class="p-2 text-sm">${getMappedValue(file, 'size')}</td>
        <td class="p-2 text-sm font-medium ${file.caveat === 'Yes' ? 'text-red-600' : ''}">${file.caveat || '-'}</td>
        <td class="p-2 text-sm">
          <button class="view-file-btn inline-flex items-center px-2 py-1 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50" data-index="${index}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m0  5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z" />
            </svg>
            View Records
          </button>
        </td>
      `;
      
      tableResultsBody.appendChild(row);
    });
    
    // Party 3 is always visible in search results

    // Add event listeners to view buttons
    document.querySelectorAll('.view-file-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const index = parseInt(btn.getAttribute('data-index'));
        console.log('View button clicked for index:', index);
        
        selectedFile = searchResults[index];
        console.log('Selected file:', selectedFile);
        
        // Close search modal
        searchModal.classList.add('hidden');
        
        // Show file history view directly instead of file details
        dashboardView.classList.add('hidden');
        fileHistoryView.classList.remove('hidden');
        
        // Populate file details
        renderFileHistory();
      });
    });
  };

  // Helper function to get mapped field value
  const getMappedValue = (item, fieldType) => {
    const fieldMappings = {
      // Date fields - enhanced for applications
      date: [
        'cofo_date', 'transaction_date', 'deeds_date', 'certificateDate', 
        'instrumentDate', 'approval_date', 'planning_approval_date',
        'receipt_date', 'payment_date', 'accountant_signature_date',
        'created_at', 'updated_at'
      ],
      
      // Transaction type fields - enhanced for applications
      transactionType: [
        'transaction_type', 'instrument_type', 'title_type', 'typeForm', 
        'landUseType', 'application_status', 'deeds_status',
        'planning_recommendation_status', 'land_use', 'landUse'
      ],
      
      // Grantor/From party fields - enhanced for applications
      grantor: [
        'owner_fullname', 'mother_owner_fullname', 
        'first_name', 'corporate_name', 'multiple_owners_names',
        'Assignor', 'assignor', 'assignorName',
        'Grantor', 'Mortgagor', 'mortgagor', 
        'Lessor', 'lessor', 'Surrenderor', 'surrenderor',
        'originalAllottee', 'surrenderingPartyName',
        'applicant_title'
      ],
      
      // Grantee/To party fields - enhanced for applications
      grantee: [
        'sub_owner_fullname', 'multiple_owners_names', 'owner_fullname',
        'Assignee', 'assignee', 'Grantee', 
        'Mortgagee', 'mortgagee', 'Lessee', 'lessee',
        'Surrenderee', 'surrenderee', 'currentAllottee',
        'receivingPartyName', 'releaseeName',
        'first_name', 'corporate_name'
      ],
      
      // Registration number fields - enhanced
      serialNo: [
        'serialNo', 'serial_no', 'oldTitleSerialNo', 
        'rootRegistrationNumber', 'particularsRegistrationNumber',
        'volume_no', 'page_no'
      ],
      pageNo: ['pageNo', 'page_no', 'oldTitlePageNo'],
      volumeNo: ['volumeNo', 'volume_no', 'oldTitleVolumeNo'],
      
      // Size fields - enhanced
      size: ['size', 'plot_size', 'NoOfUnits', 'NoOfSections', 'NoOfBlocks'],
      
      // Comments fields - enhanced for applications
      comments: [
        'comments', 'additional_comments', 'recomm_comments', 
        'director_comments', 'application_comment', 'planning_recomm_comments'
      ],
      
      // Time fields
      time: ['deeds_time', 'transaction_time'],

      // Reg Date fields (for registration date/time display)
      regDate: ['deeds_date', 'reg_date', 'transaction_date'],
      regTime: ['deeds_time', 'reg_time', 'transaction_time'],
      
      // Plot number fields - enhanced for applications
      plotNo: [
        'plot_no', 'plotNo', 'plotNumber', 'property_plot_no', 
        'address_plot_no', 'scheme_no'
      ],
      
      // LGA fields - enhanced for applications
      lga: [
        'property_lga', 'address_lga', 'lga', 'lgaName', 
        'lgsaOrCity'
      ],
      
      // District fields - enhanced for applications
      district: [
        'property_district', 'address_district', 'district', 
        'districtName'
      ],
      
      // Location/Address fields - enhanced for applications
      location: [
        'location', 'propertyAddress', 'propertyDescription', 
        'plotDescription', 'property_location', 'address',
        'property_street_name', 'address_street_name',
        'property_house_no', 'address_house_no'
      ],
      
      // Land use fields - enhanced for applications
      landUse: [
        'land_use', 'landUse', 'landUseType', 'residential_type',
        'commercial_type', 'industrial_type', 'mixed_type'
      ]
    };
    
    const fields = fieldMappings[fieldType] || [];
    for (const field of fields) {
      if (item[field] && item[field] !== null && item[field] !== '') {
        let value = item[field];
        // Remove .0 from numeric values that are actually integers
        if (typeof value === 'number' && value % 1 === 0) {
          value = Math.floor(value).toString();
        } else if (typeof value === 'string' && value.endsWith('.0')) {
          value = value.replace('.0', '');
        }
        return value;
      }
    }
    return '-';
  };

  // Helper: format reg particulars with 0/0/0 fallback
  const formatRegParticulars = (serialNo, pageNo, volumeNo) => {
    const s = cleanNumericValue(serialNo);
    const p = cleanNumericValue(pageNo);
    const v = cleanNumericValue(volumeNo);
    const hasAny = (s && s !== '-') || (p && p !== '-') || (v && v !== '-');
    if (!hasAny) return '0/0/0';
    return `${(s && s !== '-') ? s : '0'}/${(p && p !== '-') ? p : '0'}/${(v && v !== '-') ? v : '0'}`;
  };

  // Helper: format reg date for display
  const formatRegDate = (item) => {
    const raw = item.deeds_date || item.reg_date || null;
    if (!raw || raw === '-') return '-';
    try {
      const d = new Date(raw);
      if (isNaN(d.getTime())) return raw;
      return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch(e) { return raw; }
  };

  // Helper: format reg time for display
  const formatRegTime = (item) => {
    const raw = item.deeds_time || item.reg_time || null;
    if (!raw || raw === '-') return '-';
    try {
      // Handle time-only strings like "10:30 AM" or "14:30:00"
      const str = String(raw).trim();
      if (/^\d{1,2}:\d{2}/.test(str)) {
        const d = new Date('2000-01-01 ' + str);
        if (!isNaN(d.getTime())) return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
      }
      return str;
    } catch(e) { return raw; }
  };

  // Helper function to convert text to proper case
  const toProperCase = (text) => {
    if (!text || text === '-') return text;
    return text.toString().toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
  };

  // Keep Aggregate/source tabs complete, but dedupe merged outputs (Timeline/Report)
  // by preferring File History over PRA when the same logical record appears in both.
  // Enhanced: reg particulars bonus (+2) makes records WITH reg data preferred.
  const dedupeTransactionsForTimelineAndReport = (transactions) => {
    if (!Array.isArray(transactions) || transactions.length === 0) {
      window._weightingData = [];
      return [];
    }

    const normalize = (value) => String(value || '')
      .trim()
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .replace(/[.,]/g, '');

    const sourceBaseScore = (row) => {
      const source = String(row?.source_table || '').trim();
      if (source === 'File History') return 3;
      if (source === 'PRA') return 2;
      return 1;
    };

    const sourceLabel = (row) => {
      const source = String(row?.source_table || '').trim();
      if (source === 'File History') return 'FH';
      if (source === 'PRA') return 'PRA';
      if (source === 'CofO') return 'CofO';
      if (source === 'Deed Registration') return 'Deed';
      return source || '?';
    };

    // Reg particulars bonus: +2 if record has non-empty reg particulars
    const regBonus = (row) => {
      const serialNo = cleanNumericValue(getMappedValue(row, 'serialNo'));
      const pageNo = cleanNumericValue(getMappedValue(row, 'pageNo'));
      const volumeNo = cleanNumericValue(getMappedValue(row, 'volumeNo'));
      const hasReg = (serialNo && serialNo !== '-' && serialNo !== '0') ||
                     (pageNo && pageNo !== '-' && pageNo !== '0') ||
                     (volumeNo && volumeNo !== '-' && volumeNo !== '0');
      return hasReg ? 2 : 0;
    };

    const totalScore = (row) => sourceBaseScore(row) + regBonus(row);

    // Fingerprint: exclude reg particulars (used as scoring factor instead)
    const recordKey = (row) => {
      const source = String(row?.source_table || '').trim();
      if (source !== 'File History' && source !== 'PRA') return null;

      const transType = normalize(getMappedValue(row, 'transactionType'));
      const party1 = normalize(row.party_1 || '');
      const party2 = normalize(row.party_2 || '');
      const party3 = normalize(row.party_3 || '');
      const party4 = normalize(row.party_4 || '');
      const date = normalize(getMappedValue(row, 'date'));

      const hasSignal = [transType, party1, party2, date].some(Boolean);
      if (!hasSignal) return null;

      return [transType, party1, party2, party3, party4, date].join('|');
    };

    // Pass 1: build deduped set with scoring
    const deduped = [];
    const keyToIndex = new Map();
    const keyToAllRows = new Map(); // fingerprint -> [rows] for weighting data

    transactions.forEach((row) => {
      const key = recordKey(row);
      if (!key) {
        deduped.push(row);
        return;
      }

      // Track all rows per fingerprint
      if (!keyToAllRows.has(key)) keyToAllRows.set(key, []);
      keyToAllRows.get(key).push(row);

      const existingIndex = keyToIndex.get(key);
      if (existingIndex === undefined) {
        keyToIndex.set(key, deduped.length);
        deduped.push(row);
        return;
      }

      const existing = deduped[existingIndex];
      if (totalScore(row) > totalScore(existing)) {
        deduped[existingIndex] = row;
      }
    });

    // Pass 2: tag every record in the original transactions with _dedup_status
    // Build a set of winner IDs
    const winnerIds = new Set();
    deduped.forEach(row => {
      const uid = (row.source_table || '') + '::' + (row.id || '');
      winnerIds.add(uid);
    });

    // Build weighting data for the transparency table
    const weightingData = [];

    keyToAllRows.forEach((rows, fingerprint) => {
      const isDuplicated = rows.length > 1;
      rows.forEach(row => {
        const uid = (row.source_table || '') + '::' + (row.id || '');
        const isWinner = winnerIds.has(uid);
        const base = sourceBaseScore(row);
        const bonus = regBonus(row);
        const total = base + bonus;
        const status = isDuplicated ? (isWinner ? 'preferred' : 'duplicate') : 'unique';

        // Tag the original row
        row._dedup_status = status;
        row._dedup_score = total;

        // Build fingerprint display
        const transType = toProperCase(getMappedValue(row, 'transactionType')) || '-';
        const p1 = toProperCase(row.party_1 || '-');
        const p2 = toProperCase(row.party_2 || '-');
        const date = getMappedValue(row, 'date') || '-';
        const serialNo = cleanNumericValue(getMappedValue(row, 'serialNo'));
        const pageNo = cleanNumericValue(getMappedValue(row, 'pageNo'));
        const volumeNo = cleanNumericValue(getMappedValue(row, 'volumeNo'));
        const regParts = formatRegParticulars(serialNo, pageNo, volumeNo);

        weightingData.push({
          fingerprint: `${transType} / ${p1}→${p2} / ${date}`,
          source: sourceLabel(row),
          baseScore: base,
          regBonus: bonus,
          totalScore: total,
          status: status,
          summary: `${transType}, ${regParts}`,
        });
      });
    });

    // Tag non-FH/PRA records as unique (they don't participate in dedup)
    transactions.forEach(row => {
      if (!row._dedup_status) {
        row._dedup_status = 'unique';
        row._dedup_score = sourceBaseScore(row) + regBonus(row);
      }
    });

    window._weightingData = weightingData;

    return deduped;
  };

  // Render card results - UPDATED FOR NEW FILE NUMBER STRUCTURE
  const renderCardResults = () => {
    cardResults.innerHTML = '';
    
    searchResults.forEach((file, index) => {
      const fileNumbers = extractFileNumbers(file);
      const card = document.createElement('div');
      card.className = 'bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow cursor-pointer';
      card.setAttribute('data-index', index);
      card.innerHTML = `
        <div class="p-4">
          <div class="flex justify-between items-start mb-3">
            <div>
              <div class="font-medium flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m0 5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z" />
                </svg>
                ${fileNumbers.mls}
              </div>
              <div class="text-sm text-gray-500 mt-1">
                ST Unit: ${fileNumbers.st} | NP: ${fileNumbers.parent} | KANGIS: ${fileNumbers.kangis} | New KANGIS: ${fileNumbers.new_kangis}
              </div>
            </div>
          </div>
        </div>
      `;
      
      card.addEventListener('click', () => {
        const cardIndex = parseInt(card.getAttribute('data-index'));
        selectedFile = searchResults[cardIndex];
        searchModal.classList.add('hidden');
        dashboardView.classList.add('hidden');
        fileHistoryView.classList.remove('hidden');
        renderFileHistory();
      });
      
      cardResults.appendChild(card);
    });
  };

  // Render file history (the side-by-side layout shown in the screenshot)
  const renderFileHistory = () => {
    if (!selectedFile) {
      console.log('No selected file in renderFileHistory');
      return;
    }
    
    console.log('Rendering file history for:', selectedFile);
    
    // Reset any file info override from previous selection
    _originalFileInfo = null;
    const resetBtn = document.getElementById('reset-file-info-btn');
    if (resetBtn) resetBtn.classList.add('hidden');
    const subtitle = document.getElementById('file-info-subtitle');
    if (subtitle) subtitle.textContent = 'Details about the selected file';

    const fileNumbers = extractFileNumbers(selectedFile);
    const relatedMls = parseRelatedFilenoValue(selectedFile._file_related_fileno || selectedFile.related_fileno || null);
    const isMlsActuallyKangis = identifyFileNumberType(fileNumbers.mls) === 'kangis';
    const mlsDisplay = (relatedMls !== '-' && (isMlsActuallyKangis || fileNumbers.mls === '-')) ? relatedMls : fileNumbers.mls;
    
    // Update file reference in subtitle (with .0 fix)
    let fileRef = mlsDisplay !== '-' ? mlsDisplay : (selectedFile.mlsFNo || selectedFile.MLSFileNo || selectedFile.fileNo || selectedFile.fileno || '-');
    document.getElementById('file-reference').textContent = fileRef;
    
    // Update file information fields (with .0 fix and better field mapping)
    document.getElementById('file-number-value').textContent = mlsDisplay;
    document.getElementById('kangis-file-number-value').textContent = fileNumbers.kangis;
    document.getElementById('new-kangis-file-number-value').textContent = fileNumbers.new_kangis;
    
    // File Title - look up from file_indexings via search response
    const fileTitleValue = selectedFile._file_title || '-';
    document.getElementById('file-title-value').textContent = fileTitleValue;

    // Plot No - from file_indexings
    const plotNoValue = selectedFile._file_plot_number || selectedFile.plot_no || selectedFile.plotNo || '-';
    document.getElementById('plot-no-value').textContent = plotNoValue;

    // Size - prefer aggregated file_size, fallback to transaction data
    const sizeValue = selectedFile._file_size || selectedFile.size || selectedFile.plot_size || '-';
    document.getElementById('size-value').textContent = sizeValue;

    // TP No - from file_indexings
    const tpnoValue = selectedFile._file_tp_no || selectedFile.tp_no || '-';
    document.getElementById('tpno-value').textContent = tpnoValue;
    
    // LGA - prefer file_indexings, fallback to transaction data
    const lgaValue = selectedFile._file_lga || selectedFile.property_lga || selectedFile.address_lga || 
                    selectedFile.lgsaOrCity || selectedFile.lga || selectedFile.lgaName || '-';
    document.getElementById('lga-value').textContent = lgaValue;
    
    // District - prefer file_indexings, fallback to transaction data
    const districtValue = selectedFile._file_district || selectedFile.property_district || selectedFile.address_district || 
                         selectedFile.district || selectedFile.districtName || '-';
    document.getElementById('district-value').textContent = districtValue;
    
    // Land Use - prefer file_indexings, fallback to transaction data
    const landUseValue = selectedFile._file_land_use || selectedFile.land_use || selectedFile.landUse || 
                             selectedFile.landUseType || selectedFile.title_type || 
                             selectedFile.instrument_type || selectedFile.Type || '-';
    document.getElementById('property-type-value').textContent = landUseValue;
    
    // Enhanced last transaction mapping
    const lastTransactionValue = selectedFile.transaction_type || selectedFile.instrument_type || 
                                selectedFile.application_status || selectedFile.deeds_status || 
                                selectedFile.planning_recommendation_status || '-';
    document.getElementById('last-transaction-value').textContent = lastTransactionValue;
    
    // Render the transactions tables
    renderTransactionTables();
    
    // Update Timeline button with current file's prop_id / file number
    const tlBtn = document.getElementById('view-property-timeline-btn');
    if (tlBtn) {
      const tlPropId = String(selectedFile.prop_id || '');
      const tlFileNo = fileRef;
      if (tlPropId || tlFileNo) {
        tlBtn.setAttribute('data-prop-id', tlPropId);
        tlBtn.setAttribute('data-file-number', tlFileNo);
        tlBtn.classList.remove('hidden');
      } else {
        tlBtn.classList.add('hidden');
      }
    }
    
    // Default to file history tab
    switchTab('transaction-history');
  };

  // Get related transactions for a selected file - UPDATED FOR HIERARCHICAL SEARCH
  const getRelatedTransactions = (file) => {
    console.log('=== getRelatedTransactions called (HIERARCHICAL) ===');
    console.log('Selected file:', file);
    console.log('Search results available:', searchResults);
    console.log('Total search results count:', searchResults ? searchResults.length : 0);
    
    if (!searchResults || searchResults.length === 0 || !file) {
      console.log('No search results or file available, returning empty array');
      return [];
    }
    
    // Since the backend now returns hierarchical results that are already filtered
    // by the hierarchical logic, we should return ALL search results
    console.log('Returning all search results due to hierarchical backend filtering');
    return searchResults;
  };

  // Toggle Party 3/4 column visibility based on whether any record has data
  const togglePartyColumns = (records, containerSelector) => {
    const hasParty3 = records.some(r => r.party_3 && r.party_3 !== '-' && r.party_3.trim() !== '');
    const hasParty4 = records.some(r => r.party_4 && r.party_4 !== '-' && r.party_4.trim() !== '');
    const container = document.querySelector(containerSelector) || document;
    container.querySelectorAll('.party-3-col').forEach(el => el.classList.toggle('hidden', !hasParty3));
    container.querySelectorAll('.party-4-col').forEach(el => el.classList.toggle('hidden', !hasParty4));
  };

  // Dedup status dot indicator for source tab rows
  const dedupDot = (item) => {
    const status = item._dedup_status || '';
    if (status === 'preferred') {
      return '<span title="Preferred — this record is used in Timeline/Report/Print" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:4px;vertical-align:middle;"></span>';
    }
    if (status === 'duplicate') {
      return '<span title="Duplicate — a better version exists from another source" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;margin-right:4px;vertical-align:middle;"></span>';
    }
    return '';
  };

  // Render weighting transparency table
  const renderWeightingTable = () => {
    const tbody = document.getElementById('weighting-table');
    if (!tbody) return;
    const data = window._weightingData || [];
    if (data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-gray-400 py-4">No duplicate groups detected</td></tr>';
      return;
    }
    tbody.innerHTML = data.map((row, i) => {
      const statusBg = row.status === 'preferred' ? 'background:#dcfce7;color:#166534;' :
                        row.status === 'duplicate' ? 'background:#fef3c7;color:#92400e;' : '';
      const statusLabel = row.status === 'preferred' ? '&#x2713; Preferred' : '&#x2717; Duplicate';
      return `<tr>
        <td>${i + 1}</td>
        <td style="font-family:monospace;font-size:11px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${row.fingerprint}">${row.fingerprint}</td>
        <td>${row.source}</td>
        <td class="text-center">${row.baseScore}</td>
        <td class="text-center">${row.regBonus > 0 ? '+' + row.regBonus : '0'}</td>
        <td class="text-center font-semibold">${row.totalScore}</td>
        <td style="${statusBg}padding:2px 8px;border-radius:4px;font-size:12px;font-weight:500;text-align:center;">${statusLabel}</td>
        <td style="font-size:12px;">${row.summary}</td>
      </tr>`;
    }).join('');
  };

  // Wire up weighting section show/collapse toggle
  document.addEventListener('DOMContentLoaded', () => {
    const showBtn = document.getElementById('show-weighting-btn');
    const section = document.getElementById('weighting-section');
    const collapseBtn = document.getElementById('toggle-weighting-btn');
    if (showBtn && section) {
      showBtn.addEventListener('click', () => {
        section.classList.toggle('hidden');
        if (!section.classList.contains('hidden')) {
          renderWeightingTable();
          section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    }
    if (collapseBtn && section) {
      collapseBtn.addEventListener('click', () => {
        section.classList.add('hidden');
      });
    }
  });

  // Render all transaction tables - UPDATED FOR HIERARCHICAL SEARCH
  const renderTransactionTables = () => {
    // Get related transactions for the selected file
    const relatedTransactions = getRelatedTransactions(selectedFile);
    
    // Store both raw aggregate data and deduped merged data.
    window._allRelatedTransactions = relatedTransactions;
    window._preferredRelatedTransactions = dedupeTransactionsForTimelineAndReport(relatedTransactions);
    window._currentPropId = selectedFile.prop_id || selectedFile.propId || '';
    window._currentFileNumber = selectedFile.mlsFNo || selectedFile.MLSFileNo || selectedFile.fileNo || selectedFile.fileno || '';
    
    console.log('Rendering transaction tables with:', relatedTransactions);
    
    // Separate records by their source_table label (set by normalizeRow in backend)
    const propertyRecords = relatedTransactions.filter(item => 
      item.source_table === 'PRA'
    );
    
    const fileHistoryRecords = relatedTransactions.filter(item => 
      item.source_table === 'File History'
    );
    
    const instrumentRecords = relatedTransactions.filter(item => 
      item.source_table === 'Deed Registration'
    );
    
    const cofoRecords = relatedTransactions.filter(item => 
      item.source_table === 'CofO'
    );
    
    console.log('Property Record (PRA):', propertyRecords.length);
    console.log('File History (FH):', fileHistoryRecords.length);
    console.log('Instrument Registration (Deed):', instrumentRecords.length);
    console.log('CofO records:', cofoRecords.length);

    // Update colored tab counts in detailed records view
    const tabCountTransactionHistory = document.getElementById('tab-count-transaction-history');
    const tabCountPropertyRecord = document.getElementById('tab-count-property-record');
    const tabCountInstrument = document.getElementById('tab-count-instrument');
    const tabCountCofo = document.getElementById('tab-count-cofo');
    if (tabCountTransactionHistory) tabCountTransactionHistory.textContent = fileHistoryRecords.length;
    if (tabCountPropertyRecord) tabCountPropertyRecord.textContent = propertyRecords.length;
    if (tabCountInstrument) tabCountInstrument.textContent = instrumentRecords.length;
    if (tabCountCofo) tabCountCofo.textContent = cofoRecords.length;

    // Helper: source_table label -> actual DB table name
    const sourceToDbTable = (label) => {
      const map = { 'PRA': 'pra', 'File History': 'file_history_staging', 'CofO': 'CofO_staging', 'Deed Registration': 'deed_registrations' };
      return map[label] || label;
    };

    // Helper: build checkbox cell HTML (hidden by default, shown in cleanup mode)
    const checkboxCell = (item) => `<td class="cleanup-col hidden text-center"><input type="checkbox" class="row-checkbox" data-id="${item.id}" data-table="${sourceToDbTable(item.source_table)}" data-prop-id="${item.prop_id || ''}"></td>`;
    
    // Property Record tab (pra)
    const propertyRecordTable = document.getElementById('property-record-table');
    propertyRecordTable.innerHTML = '';
    
    if (propertyRecords.length > 0) {
      propertyRecords.forEach((item, index) => {
        console.log('Processing property record:', item);
        
        const date = getMappedValue(item, 'date');
        const transactionType = toProperCase(getMappedValue(item, 'transactionType'));
        const party1 = toProperCase(item.party_1 || '-');
        const party2 = toProperCase(item.party_2 || '-');
        const party3 = toProperCase(item.party_3 || '-');
        const party4 = toProperCase(item.party_4 || '-');
        const serialNo = getMappedValue(item, 'serialNo');
        const pageNo = getMappedValue(item, 'pageNo');
        const volumeNo = getMappedValue(item, 'volumeNo');
        const regParticulars = formatRegParticulars(serialNo, pageNo, volumeNo);
        const regDate = formatRegDate(item);
        const regTime = formatRegTime(item);
        const size = getMappedValue(item, 'size');
        const comments = toProperCase((item.is_caveated == 1 && item.caveated_comment) ? item.caveated_comment : getMappedValue(item, 'comments'));
        
        const row = document.createElement('tr');
        row.dataset.id = item.id;
        row.dataset.table = sourceToDbTable(item.source_table);
        row.dataset.propId = item.prop_id || '';
        row.innerHTML = `
          <td>${dedupDot(item)}${index + 1}</td>
          <td>${transactionType}</td>
          <td>${party1}</td>
          <td>${party2}</td>
          <td>${party3}</td>
          <td>${regParticulars}</td>
          <td>${date}</td>
          <td>${regTime}</td>
          <td>${regDate}</td>
          <td>${size}</td>
          <td class="${item.caveat === 'Yes' ? 'text-red-600 font-medium' : ''}">${item.caveat || '-'}</td>
          <td>${comments}</td>
          <td>
            <div class="flex space-x-2">
              <button class="edit-action" data-id="${item.id}" data-table="${sourceToDbTable(item.source_table)}">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
              </button>
              <button class="delete-action" data-id="${item.id}" data-table="${sourceToDbTable(item.source_table)}">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 6h18"></path>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                  <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
              </button>
            </div>
          </td>
        `;
        propertyRecordTable.appendChild(row);
      });
    } else {
      propertyRecordTable.innerHTML = `
        <tr>
          <td colspan="13" class="text-center py-4 text-gray-500">No property records found.</td>
        </tr>
      `;
    }

    // File History tab (file_history_staging table)
    const transactionHistoryTable = document.getElementById('transaction-history-table');
    transactionHistoryTable.innerHTML = '';

    if (fileHistoryRecords.length > 0) {
      fileHistoryRecords.forEach((item, index) => {
        const date = getMappedValue(item, 'date');
        const transactionType = toProperCase(getMappedValue(item, 'transactionType'));
        const party1 = toProperCase(item.party_1 || '-');
        const party2 = toProperCase(item.party_2 || '-');
        const party3 = toProperCase(item.party_3 || '-');
        const party4 = toProperCase(item.party_4 || '-');
        const serialNo = getMappedValue(item, 'serialNo');
        const pageNo = getMappedValue(item, 'pageNo');
        const volumeNo = getMappedValue(item, 'volumeNo');
        const regParticulars = formatRegParticulars(serialNo, pageNo, volumeNo);
        const regDate = formatRegDate(item);
        const regTime = formatRegTime(item);
        const size = getMappedValue(item, 'size');
        const comments = toProperCase((item.is_caveated == 1 && item.caveated_comment) ? item.caveated_comment : getMappedValue(item, 'comments'));

        const row = document.createElement('tr');
        row.dataset.id = item.id;
        row.dataset.table = sourceToDbTable(item.source_table);
        row.dataset.propId = item.prop_id || '';
        row.innerHTML = `
          <td>${dedupDot(item)}${index + 1}</td>
          <td>${transactionType}</td>
          <td>${party1}</td>
          <td>${party2}</td>
          <td>${party3}</td>
          <td>${regParticulars}</td>
          <td>${date}</td>
          <td>${regTime}</td>
          <td>${regDate}</td>
          <td>${size}</td>
          <td class="${item.caveat === 'Yes' ? 'text-red-600 font-medium' : ''}">${item.caveat || '-'}</td>
          <td>${comments}</td>
          <td>
            <div class="flex space-x-2">
              <button class="edit-action" data-id="${item.id}" data-table="${sourceToDbTable(item.source_table)}">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
              </button>
              <button class="delete-action" data-id="${item.id}" data-table="${sourceToDbTable(item.source_table)}">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 6h18"></path>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                  <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
              </button>
            </div>
          </td>
        `;
        transactionHistoryTable.appendChild(row);
      });
    } else {
      transactionHistoryTable.innerHTML = `
        <tr>
          <td colspan="13" class="text-center py-4 text-gray-500">No file history records found.</td>
        </tr>
      `;
    }
    
    // Instrument Registration tab (deed_registrations table)
    const instrumentRegistrationTable = document.getElementById('instrument-registration-table');
    instrumentRegistrationTable.innerHTML = '';
    
    if (instrumentRecords.length > 0) {
      console.log('=== RENDERING INSTRUMENT RECORDS ===');
      console.log('Total instrument records:', instrumentRecords.length);
      
      instrumentRecords.forEach((registration, index) => {
        console.log(`Processing instrument record ${index + 1}:`, registration);
        
        const date = getMappedValue(registration, 'date');
        const time = getMappedValue(registration, 'time');
        const transactionType = toProperCase(getMappedValue(registration, 'transactionType'));
        const party1 = toProperCase(registration.party_1 || '-');
        const party2 = toProperCase(registration.party_2 || '-');
        const party3 = toProperCase(registration.party_3 || '-');
        const party4 = toProperCase(registration.party_4 || '-');
        
        // Fix Registration Particulars to show full format (Serial/Page/Volume)
        const serialNo = getMappedValue(registration, 'serialNo');
        const pageNo = getMappedValue(registration, 'pageNo');
        const volumeNo = getMappedValue(registration, 'volumeNo');
        const regNumber = formatRegParticulars(serialNo, pageNo, volumeNo);
        
        // Enhanced logging for ST Fragmentation records
        if (transactionType.toLowerCase().includes('fragmentation') || 
            transactionType.toLowerCase().includes('st fragmentation')) {
          console.log('*** ST FRAGMENTATION RECORD FOUND ***');
          console.log('Transaction Type:', transactionType);
          console.log('Date:', date);
          console.log('Party 1:', party1);
          console.log('Party 2:', party2);
          console.log('Registration Number:', regNumber);
        }
        
        const row = document.createElement('tr');
        row.dataset.id = registration.id;
        row.dataset.table = sourceToDbTable(registration.source_table);
        row.dataset.propId = registration.prop_id || '';
        // Add special styling for ST Fragmentation records
        const isSTFragmentation = transactionType.toLowerCase().includes('fragmentation') || 
                                 transactionType.toLowerCase().includes('st fragmentation');
        
        if (isSTFragmentation) {
          row.className = 'bg-yellow-50 border-l-4 border-l-yellow-400';
        }
        
        row.innerHTML = `
          <td>${index + 1}</td>
          <td class="${isSTFragmentation ? 'font-semibold text-yellow-800' : ''}">${transactionType}</td>
          <td>${party1}</td>
          <td>${party2}</td>
          <td>${party3}</td>
          <td>${regNumber}</td>
          <td>
            <div>${date}</div>
            <div class="text-xs text-gray-600">${time}</div>
          </td>
          <td>${toProperCase(registration.registered_by_name || '-')}</td>
          <td>
            <div class="flex space-x-2">
              <button class="edit-action" data-id="${registration.id}" data-table="${sourceToDbTable(registration.source_table)}">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
              </button>
              <button class="delete-action" data-id="${registration.id}" data-table="${sourceToDbTable(registration.source_table)}">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 6h18"></path>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                  <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
              </button>
            </div>
          </td>
        `;
        instrumentRegistrationTable.appendChild(row);
      });
      
      // Log summary of ST Fragmentation records found
      const stFragmentationCount = instrumentRecords.filter(record => {
        const transactionType = getMappedValue(record, 'transactionType').toLowerCase();
        return transactionType.includes('fragmentation') || transactionType.includes('st fragmentation');
      }).length;
      
      console.log(`=== ST FRAGMENTATION SUMMARY ===`);
      console.log(`Total ST Fragmentation records displayed: ${stFragmentationCount}`);
      
    } else {
      instrumentRegistrationTable.innerHTML = `
        <tr>
          <td colspan="9" class="text-center py-4 text-gray-500">No instrument registration records found.</td>
        </tr>
      `;
    }
    
    // Certificate of Occupancy (only CofO table)
    const cofoTable = document.getElementById('cofo-table');
    cofoTable.innerHTML = '';
    
    if (cofoRecords.length > 0) {
      const hasMultipleCofo = cofoRecords.length > 1;
      cofoRecords.forEach((cofo, index) => {
        const serialNo = getMappedValue(cofo, 'serialNo');
        const pageNo = getMappedValue(cofo, 'pageNo');
        const volumeNo = getMappedValue(cofo, 'volumeNo');
        const cofoRegParticulars = formatRegParticulars(serialNo, pageNo, volumeNo);

        const date = getMappedValue(cofo, 'date');
        const grantee = toProperCase(cofo.party_2 || getMappedValue(cofo, 'grantee'));
        const landUse = toProperCase(getMappedValue(cofo, 'landUse'));
        
        const row = document.createElement('tr');
        row.dataset.id = cofo.id;
        row.dataset.table = sourceToDbTable(cofo.source_table);
        row.dataset.propId = cofo.prop_id || '';
        row.dataset.cofoComment = cofo.cofo_comment || '';
        row.dataset.cofoFallbackComment = cofo.comments || '';
        row.innerHTML = `
          <td>${index + 1}</td>
          <td>${cofoRegParticulars}</td>
          <td>
            <div>${date}</div>
          </td>
          <td>${grantee}</td>
          <td>${landUse}</td>
          <td>${cofo.Period || cofo.term || cofo.occupancy || '-'}</td>
          <td>
            <div class="flex space-x-2">
              ${hasMultipleCofo ? `
              <div class="relative">
                <button type="button" class="cofo-action-toggle inline-flex items-center justify-center p-1.5 border border-gray-200 rounded hover:bg-gray-50" title="More actions" data-id="${cofo.id}">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="1"></circle>
                    <circle cx="19" cy="12" r="1"></circle>
                    <circle cx="5" cy="12" r="1"></circle>
                  </svg>
                </button>
                <div class="cofo-action-menu hidden absolute right-0 mt-1 w-40 bg-white border border-gray-200 rounded-md shadow-lg z-20">
                  <button type="button" class="cofo-add-comment-action w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-gray-50" data-id="${cofo.id}">
                    Add Comment
                  </button>
                </div>
              </div>` : ''}
              <button class="edit-action" data-id="${cofo.id}" data-table="${sourceToDbTable(cofo.source_table)}">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
              </button>
              <button class="delete-action" data-id="${cofo.id}" data-table="${sourceToDbTable(cofo.source_table)}">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 6h18"></path>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                  <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
              </button>
            </div>
          </td>
        `;
        cofoTable.appendChild(row);
      });
    } else {
      cofoTable.innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-4 text-gray-500">No Certificate of Occupancy records found.</td>
        </tr>
      `;
    }

    // Render timeline
    renderTimeline();

    // Load editable comments and show/hide sections based on data
    loadComments(window._currentFileNumber);
    showCommentSections(window._allRelatedTransactions || []);

    // Party 3 is always visible, Party 4 removed
  };
  
  // Switch between tabs in the file details view
  const switchTab = (tabName) => {
    // Update active tab
    document.querySelectorAll('.tab').forEach(t => {
      if (t.getAttribute('data-tab') === tabName) {
        t.classList.add('active');
      } else {
        t.classList.remove('active');
      }
    });
    
    // Update visible content
    document.querySelectorAll('.tab-content').forEach(content => {
      content.classList.remove('active');
    });
    document.getElementById(`${tabName}-tab`).classList.add('active');
  };

  // Back to dashboard from file history view
  const backToDashboardBtn = document.getElementById('back-to-dashboard-btn');
  if (backToDashboardBtn) {
    backToDashboardBtn.addEventListener('click', () => {
      fileHistoryView.classList.add('hidden');
      dashboardView.classList.remove('hidden');
    });
  }

  // Timeline button in file history view
  const viewPropertyTimelineBtn = document.getElementById('view-property-timeline-btn');
  if (viewPropertyTimelineBtn) {
    viewPropertyTimelineBtn.addEventListener('click', () => {
      const propId = viewPropertyTimelineBtn.getAttribute('data-prop-id') || '';
      const fileNo = viewPropertyTimelineBtn.getAttribute('data-file-number') || '';
      if (typeof openPropertyTimeline === 'function') {
        openPropertyTimeline(propId, fileNo);
      } else if (typeof window.openTailwindModal === 'function') {
        var params = new URLSearchParams();
        if (propId) params.set('prop_id', propId);
        if (fileNo) params.set('file_number', fileNo);
        params.set('mode', 'partial');
        window.openTailwindModal('/property-search/timeline?' + params.toString(), 'xl');
      }
    });
  }

  // Generate random time strings
  const generateRandomTime = () => {
    const hours = Math.floor(Math.random() * 12) + 1; // 1-12
    const minutes = Math.floor(Math.random() * 60); // 0-59
    const ampm = Math.random() > 0.5 ? 'AM' : 'PM';
    return `${hours}:${minutes.toString().padStart(2, '0')} ${ampm}`;
  };

  // Render legal search report
  const renderLegalSearchReport = () => {
    if (!selectedFile) return;

    // Get related transactions for the selected file
    const relatedTransactions = dedupeTransactionsForTimelineAndReport(getRelatedTransactions(selectedFile));

    // Helper to get Registration Particulars for each transaction
    function getRegistrationParticulars(transaction) {
      // property_records table
      if (
        transaction.hasOwnProperty('serialNo') &&
        transaction.hasOwnProperty('pageNo') &&
        transaction.hasOwnProperty('volumeNo')
      ) {
        return formatRegParticulars(transaction.serialNo, transaction.pageNo, transaction.volumeNo);
      }
      // registered_instruments table
      if (
        transaction.hasOwnProperty('instrument_type') ||
        transaction.hasOwnProperty('rootRegistrationNumber')
      ) {
        return formatRegParticulars(transaction.serial_no, transaction.page_no, transaction.volume_no);
      }
      return '0/0/0';
    }

    // Create combined array of all transactions
    const allTransactions = [];

    relatedTransactions.forEach(transaction => {
      let regNo = getRegistrationParticulars(transaction);
      
      allTransactions.push({
        date: transaction.transaction_date || transaction.cofo_date || transaction.deeds_date || transaction.certificateDate || transaction.approval_date || '-',
        time: transaction.deeds_time || generateRandomTime(),
        transactionType: transaction.transaction_type || transaction.instrument_type || transaction.title_type || 'Record',
        party1: transaction.party_1 || '-',
        party2: transaction.party_2 || '-',
        party3: transaction.party_3 || '-',
        party4: transaction.party_4 || '-',
        regNo: regNo,
        size: transaction.size || transaction.plot_size || '-',
        caveat: transaction.caveat === 'Yes' ? 'Yes' : 'NO',
        comments: transaction.comments || transaction.additional_comments || '-',
        originalRecord: transaction
      });
    });

    // Sort by date (oldest first)
    allTransactions.sort((a, b) => new Date(a.date) - new Date(b.date));

    const fileNumbers = extractFileNumbers(selectedFile);

    // Determine if search was made with primary file numbers
    const searchQuery = document.getElementById('fileNumber').value.trim();
    const isPrimaryFileSearch = searchQuery && (
      // Check if search query matches primary file number patterns
      identifyFileNumberType(searchQuery) === 'parent' ||  // NP FileNO pattern
      identifyFileNumberType(searchQuery) === 'mls' ||     // MLS File No pattern
      identifyFileNumberType(searchQuery) === 'kangis' ||  // KANGIS File No pattern
      identifyFileNumberType(searchQuery) === 'new_kangis' // New KANGIS pattern
    );

    // Update the report content
    document.getElementById('report-file-reference').textContent = fileNumbers.mls;
    
    // Build file numbers display - hide Unit Filno for primary file searches
    let fileNumbersDisplay = `NP FileNo: ${fileNumbers.parent}`;
    
    // Only show Unit Filno if:
    // 1. It's a valid ST file number (subapplication), AND
    // 2. The search was NOT made with primary file numbers
    if (fileNumbers.st !== '-' && 
        fileNumbers.st.match(/^ST-(RES|COM|IND|AG)-\d{4}-\d+-\d+$/i) && 
        !isPrimaryFileSearch) {
      fileNumbersDisplay += `  |  Unit Filno: ${fileNumbers.st}`;
    }
    
    fileNumbersDisplay += `  |  MLS File No: ${fileNumbers.mls}  |  KANGIS File No: ${fileNumbers.kangis}  |  New KANGIS: ${fileNumbers.new_kangis}`;
    
    document.getElementById('report-file-numbers').textContent = fileNumbersDisplay;
    document.getElementById('report-plot-number').textContent = selectedFile.plot_no || selectedFile.plotNo || "GP No. 1067/1 & 1067/2";
    document.getElementById('report-plan-number').textContent = selectedFile.planNumber || "LKN/RES/2021/3006";
    document.getElementById('report-plot-description').textContent = `${selectedFile.district || selectedFile.districtName || "Niger Street Nassarawa District"}, ${selectedFile.lgsaOrCity || selectedFile.lga || selectedFile.lgaName || "Nassarawa"} LGA`;
    
    // Update timestamp
    const now = new Date();
    document.getElementById('report-timestamp').textContent = `These details are as at ${now.toLocaleDateString()} ${now.toLocaleTimeString()}`;
    document.getElementById('report-date').textContent = `Date: ${now.toLocaleDateString()}`;
    document.getElementById('report-time').textContent = `Time: ${now.toLocaleTimeString()}`;
    
    // Populate transactions table
    const transactionsTable = document.getElementById('report-transactions-table');
    transactionsTable.innerHTML = '';
    
    allTransactions.forEach((transaction, index) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="border border-gray-300 px-3 py-2">${index + 1}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.party1}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.party2}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.transactionType}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.date}<br><small>${transaction.time}</small></td>
        <td class="border border-gray-300 px-3 py-2">${transaction.regNo}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.size}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.caveat}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.comments}</td>
      `;
      transactionsTable.appendChild(row);
    });
    
    // Update QR code
    const qrCodeImg = document.getElementById('report-qr-code');
    if (qrCodeImg) {
      const fileInfo = `File Number: MLSF: ${fileNumbers.mls} | KANGIS: ${fileNumbers.kangis} | New KANGIS: ${fileNumbers.new_kangis}`;
      qrCodeImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(fileInfo)}`;
    }
  };

  // Switch between table and card view

  // ================================================================
  // SECTION: Timeline Rendering
  // ================================================================
  const timelineSourceToDbTable = (label) => {
    const map = {
      'PRA': 'pra',
      'File History': 'file_history_staging',
      'CofO': 'CofO_staging',
      'Deed Registration': 'deed_registrations',
    };
    return map[label] || label;
  };

  const getTimelinePropId = () => String(selectedFile?.prop_id || '').trim();

  const loadTimelineArrangement = async (propId) => {
    if (!propId) return {};

    const res = await cleanupAjax('/legal_search/get-arrangement', { prop_id: propId });
    if (!res || !res.success) return {};

    return (res.data && res.data.arrangement) ? res.data.arrangement : {};
  };

  const sortTimelineByArrangement = (transactions, arrangementMap) => {
    if (!arrangementMap || Object.keys(arrangementMap).length === 0) {
      return transactions;
    }

    const indexed = transactions.map((item, idx) => ({ item, idx }));

    indexed.sort((a, b) => {
      const keyA = `${timelineSourceToDbTable(a.item.source_table)}:${a.item.id}`;
      const keyB = `${timelineSourceToDbTable(b.item.source_table)}:${b.item.id}`;
      const orderA = arrangementMap[keyA];
      const orderB = arrangementMap[keyB];
      const hasA = Number.isInteger(orderA);
      const hasB = Number.isInteger(orderB);

      if (hasA && hasB) return orderA - orderB;
      if (hasA && !hasB) return -1;
      if (!hasA && hasB) return 1;
      return a.idx - b.idx;
    });

    return indexed.map(x => x.item);
  };

  const parseTimelineDateValue = (value) => {
    if (!value || value === '-') return null;

    const text = String(value).trim();
    if (!text) return null;

    // dd/mm/yyyy or dd-mm-yyyy
    const dmy = text.match(/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/);
    if (dmy) {
      const day = Number(dmy[1]);
      const month = Number(dmy[2]);
      const year = Number(dmy[3]);
      const d = new Date(year, month - 1, day);
      return Number.isNaN(d.getTime()) ? null : d.getTime();
    }

    const parsed = new Date(text);
    return Number.isNaN(parsed.getTime()) ? null : parsed.getTime();
  };

  const getTransactionTimestamp = (item) => {
    const candidates = [
      item.transaction_date,
      item.deeds_date,
      item.reg_date,
      item.cofo_date,
      item.certificateDate,
      item.approval_date,
      item.date,
    ];

    for (const candidate of candidates) {
      const ts = parseTimelineDateValue(candidate);
      if (ts !== null) return ts;
    }

    return null;
  };

  const sortTimelineChronologically = (transactions) => {
    return [...transactions].sort((a, b) => {
      const ta = getTransactionTimestamp(a);
      const tb = getTransactionTimestamp(b);

      if (ta === null && tb === null) {
        return (Number(a.id) || 0) - (Number(b.id) || 0);
      }
      if (ta === null) return 1;
      if (tb === null) return -1;
      if (ta !== tb) return ta - tb;

      return (Number(a.id) || 0) - (Number(b.id) || 0);
    });
  };

  const renderTimeline = async () => {
    let transactions = window._preferredRelatedTransactions || window._allRelatedTransactions || [];
    const timelineTable = document.getElementById('timeline-table');
    if (!timelineTable) return;
    timelineTable.innerHTML = '';

    if (transactions.length === 0) {
      timelineTable.innerHTML = '<tr><td colspan="16" class="text-center py-4 text-gray-500">No transactions to display.</td></tr>';
      return;
    }

    // Timeline view must be chronological by Transaction Date.
    transactions = sortTimelineChronologically(transactions);

    const sourceBadgeClass = (label) => {
      const map = { 'PRA': 'source-badge-pra', 'File History': 'source-badge-fh', 'Deed Registration': 'source-badge-deed', 'CofO': 'source-badge-cofo' };
      return map[label] || '';
    };

    transactions.forEach((item, idx) => {
      const date = getMappedValue(item, 'date');
      const transType = toProperCase(getMappedValue(item, 'transactionType'));
      const party1 = toProperCase(item.party_1 || '-');
      const party2 = toProperCase(item.party_2 || '-');
      const party3 = toProperCase(item.party_3 || '-');
      const party4 = toProperCase(item.party_4 || '-');
      const serialNo = getMappedValue(item, 'serialNo');
      const pageNo = getMappedValue(item, 'pageNo');
      const volumeNo = getMappedValue(item, 'volumeNo');
      const regParticulars = formatRegParticulars(serialNo, pageNo, volumeNo);
      const regDate = formatRegDate(item);
      const regTime = formatRegTime(item);
      const size = getMappedValue(item, 'size');
      const comments = toProperCase((item.is_caveated == 1 && item.caveated_comment) ? item.caveated_comment : getMappedValue(item, 'comments'));

      const row = document.createElement('tr');
      row.dataset.id = item.id;
      row.dataset.table = timelineSourceToDbTable(item.source_table);
      row.dataset.originalIndex = idx;
      row.innerHTML = `
        <td class="cleanup-col hidden text-center"><input type="checkbox" class="row-checkbox" data-id="${item.id}" data-table="${timelineSourceToDbTable(item.source_table)}" data-prop-id="${item.prop_id || ''}"></td>
        <td class="arrange-col hidden text-center font-mono text-xs text-gray-400">${idx + 1}</td>
        <td class="text-center text-xs text-gray-500">${idx + 1}</td>
        <td><span class="source-badge ${sourceBadgeClass(item.source_table)}">${item.source_table}</span></td>
        <td>${transType}</td>
        <td>${party1}</td>
        <td>${party2}</td>
        <td>${party3}</td>
        <td>${regParticulars}</td>
        <td>${date}</td>
        <td>${regTime}</td>
        <td>${regDate}</td>
        <td>${size}</td>
        <td class="${item.caveat === 'Yes' ? 'text-red-600 font-medium' : ''}">${item.caveat || '-'}</td>
        <td style="max-width:200px;word-break:break-word;overflow-wrap:break-word;">${comments}</td>
        <td class="text-center">
          <button class="override-file-info-btn inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200" data-row-index="${idx}" title="Use this row's data for File Information panel">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
          </button>
        </td>
      `;
      timelineTable.appendChild(row);
    });

    // Party 3 is always visible in timeline

    // Wire up override File Info buttons
    timelineTable.querySelectorAll('.override-file-info-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const rowIndex = parseInt(btn.dataset.rowIndex, 10);
        overrideFileInfoFromRow(rowIndex);
      });
    });
  };

  // ================================================================
  // SECTION: Override File Information from Timeline Row
  // ================================================================

  // Store original file info so we can reset
  let _originalFileInfo = null;

  const captureOriginalFileInfo = () => {
    if (_originalFileInfo) return; // already captured
    _originalFileInfo = {
      fileNumber: document.getElementById('file-number-value')?.textContent || '',
      kangis: document.getElementById('kangis-file-number-value')?.textContent || '',
      newKangis: document.getElementById('new-kangis-file-number-value')?.textContent || '',
      fileTitle: document.getElementById('file-title-value')?.textContent || '',
      plotNo: document.getElementById('plot-no-value')?.textContent || '',
      size: document.getElementById('size-value')?.textContent || '',
      tpno: document.getElementById('tpno-value')?.textContent || '',
      district: document.getElementById('district-value')?.textContent || '',
      lga: document.getElementById('lga-value')?.textContent || '',
      landUse: document.getElementById('property-type-value')?.textContent || '',
      lastTransaction: document.getElementById('last-transaction-value')?.textContent || '',
      subtitle: document.getElementById('file-info-subtitle')?.textContent || '',
    };
  };

  const overrideFileInfoFromRow = (rowIndex) => {
    const transactions = window._preferredRelatedTransactions || window._allRelatedTransactions || [];
    const sorted = sortTimelineChronologically([...transactions]);
    const item = sorted[rowIndex];
    if (!item) return;

    // Capture originals on first override
    captureOriginalFileInfo();

    // Map timeline row data to File Information panel
    const tc = (v) => v && v !== '-' ? v.replace(/\b\w/g, c => c.toUpperCase()) : '-';

    document.getElementById('file-number-value').textContent = item.mlsFNo || item.file_number || item.fileno || '-';
    document.getElementById('kangis-file-number-value').textContent = item.kangisFileNo || '-';
    document.getElementById('new-kangis-file-number-value').textContent = item.NewKANGISFileno || '-';
    document.getElementById('file-title-value').textContent = tc(item.party_2 || item.party_1 || '-');
    document.getElementById('plot-no-value').textContent = item.plot_no || item.plotNo || '-';
    document.getElementById('size-value').textContent = getMappedValue(item, 'size') || '-';
    document.getElementById('tpno-value').textContent = item.tp_no || '-';
    document.getElementById('district-value').textContent = tc(item.districtName || item.district || '-');
    document.getElementById('lga-value').textContent = tc(item.lgsaOrCity || item.lga || '-');
    document.getElementById('property-type-value').textContent = tc(item.land_use || '-');
    document.getElementById('last-transaction-value').textContent = tc(item.transaction_type || '-');

    // Show override indicator
    const subtitle = document.getElementById('file-info-subtitle');
    if (subtitle) {
      subtitle.innerHTML = `<span class="inline-flex items-center text-amber-700"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" /></svg>Overridden from row ${rowIndex + 1} (${item.source_table})</span>`;
    }

    // Highlight the selected row
    const timelineTable = document.getElementById('timeline-table');
    if (timelineTable) {
      timelineTable.querySelectorAll('tr').forEach(tr => tr.classList.remove('bg-blue-50', 'ring-1', 'ring-blue-300'));
      const targetRow = timelineTable.querySelector(`tr[data-original-index="${rowIndex}"]`);
      if (targetRow) targetRow.classList.add('bg-blue-50', 'ring-1', 'ring-blue-300');
    }

    // Show reset button
    const resetBtn = document.getElementById('reset-file-info-btn');
    if (resetBtn) resetBtn.classList.remove('hidden');
  };

  const resetFileInfo = () => {
    if (!_originalFileInfo) return;

    document.getElementById('file-number-value').textContent = _originalFileInfo.fileNumber;
    document.getElementById('kangis-file-number-value').textContent = _originalFileInfo.kangis;
    document.getElementById('new-kangis-file-number-value').textContent = _originalFileInfo.newKangis;
    document.getElementById('file-title-value').textContent = _originalFileInfo.fileTitle;
    document.getElementById('plot-no-value').textContent = _originalFileInfo.plotNo;
    document.getElementById('size-value').textContent = _originalFileInfo.size;
    document.getElementById('tpno-value').textContent = _originalFileInfo.tpno;
    document.getElementById('district-value').textContent = _originalFileInfo.district;
    document.getElementById('lga-value').textContent = _originalFileInfo.lga;
    document.getElementById('property-type-value').textContent = _originalFileInfo.landUse;
    document.getElementById('last-transaction-value').textContent = _originalFileInfo.lastTransaction;

    const subtitle = document.getElementById('file-info-subtitle');
    if (subtitle) subtitle.textContent = _originalFileInfo.subtitle;

    // Remove row highlighting
    const timelineTable = document.getElementById('timeline-table');
    if (timelineTable) {
      timelineTable.querySelectorAll('tr').forEach(tr => tr.classList.remove('bg-blue-50', 'ring-1', 'ring-blue-300'));
    }

    // Hide reset button
    const resetBtn = document.getElementById('reset-file-info-btn');
    if (resetBtn) resetBtn.classList.add('hidden');

    _originalFileInfo = null;
  };

  // Wire up reset button
  document.addEventListener('DOMContentLoaded', () => {
    const resetBtn = document.getElementById('reset-file-info-btn');
    if (resetBtn) {
      resetBtn.addEventListener('click', resetFileInfo);
    }
  });

  // ================================================================
    // SECTION: Add Record / Add CofO buttons
    // ================================================================
    const updateAddRecordButtonVisibility = () => {
      const activePraTab = document.querySelector('[data-tab="property-record"].active');
      const activeCofoTab = document.querySelector('[data-tab="cofo"].active');
      const praBtn = document.getElementById('add-pra-record-btn');
      const cofoBtn = document.getElementById('add-cofo-record-btn');

      if (praBtn) praBtn.style.display = activePraTab ? 'inline-flex' : 'none';
      if (cofoBtn) cofoBtn.style.display = activeCofoTab ? 'inline-flex' : 'none';
    };

    const openPropertyRecordDialog = (targetTable, recordMode = 'property') => {
      const modal = document.getElementById('property-form-dialog');
      const form = document.getElementById('property-record-form');
      if (!modal || !form) {
        console.error('Property Record dialog elements not found', {
          hasModal: !!modal,
          hasForm: !!form,
        });
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'error',
            title: 'Dialog Not Available',
            text: 'Unable to open the Add Record dialog. Please refresh and try again.',
            confirmButtonText: 'OK'
          });
        }
        return;
      }

      form.reset();

      const targetInput = form.querySelector('input[name="target_table"]') || (() => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'target_table';
        form.appendChild(inp);
        return inp;
      })();
      targetInput.value = targetTable;

      const recordModeInput = form.querySelector('[data-model="formMode"]') || form.querySelector('[name="record_mode"]');
      if (recordModeInput) recordModeInput.value = recordMode;

      modal.classList.remove('hidden');
      modal.classList.add('show');
      modal.style.display = 'flex';
    };

    // Listen for tab changes
    document.addEventListener('click', (e) => {
      if (e.target.closest('.tab')) {
        setTimeout(updateAddRecordButtonVisibility, 50);
      }
    });

    // Delegated click binding survives dynamic re-rendering and early script execution
    document.addEventListener('click', (e) => {
      const praTrigger = e.target.closest('#add-pra-record-btn');
      if (praTrigger) {
        e.preventDefault();
        openPropertyRecordDialog('pra', 'property');
        return;
      }

      const cofoTrigger = e.target.closest('#add-cofo-record-btn');
      if (cofoTrigger) {
        e.preventDefault();
        openPropertyRecordDialog('CofO_staging', 'property');
      }
    });

    // Handle form submission for adding records
    const setupRecordFormSubmission = () => {
      const form = document.getElementById('property-record-form');
      if (!form) return;
        if (form.dataset.legalSearchSubmitBound === '1') return;
        form.dataset.legalSearchSubmitBound = '1';

      form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const targetTable = form.querySelector('input[name="target_table"]')?.value || 'pra';
        const formData = new FormData(form);

        try {
          const res = await fetch('/legal_search/create-record', {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
              'Accept': 'application/json',
            },
            body: formData,
          });

          const result = await res.json();

          if (result.success) {
            // Close modal
            const modal = document.getElementById('property-form-dialog');
            if (modal) {
              modal.classList.remove('show');
              modal.classList.add('hidden');
              modal.style.display = 'none';
            }

            // Show success message
            if (typeof Swal !== 'undefined') {
              Swal.fire({
                icon: 'success',
                title: 'Record Created',
                text: `New ${targetTable === 'pra' ? 'PRA' : 'CofO'} record has been created successfully.`,
                confirmButtonText: 'OK'
              }).then(() => {
                // Refresh search results
                performSearch();
              });
            } else {
              alert('Record created successfully. Refreshing...');
              performSearch();
            }
          } else {
            const errorMsg = result.message || `Failed to create ${targetTable === 'pra' ? 'PRA' : 'CofO'} record.`;
            if (typeof Swal !== 'undefined') {
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMsg,
                confirmButtonText: 'OK'
              });
            } else {
              alert(errorMsg);
            }
          }
        } catch (err) {
          console.error('Form submission error:', err);
          const errorMsg = `Error: ${err.message || 'Unknown error occurred'}`;
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'error',
              title: 'Request Failed',
              text: errorMsg,
              confirmButtonText: 'OK'
            });
          } else {
            alert(errorMsg);
          }
        }
      });
    };

    document.addEventListener('DOMContentLoaded', () => {
      setupRecordFormSubmission();
      updateAddRecordButtonVisibility();

      document.getElementById('close-property-form')?.addEventListener('click', () => {
        const modal = document.getElementById('property-form-dialog');
        if (!modal) return;
        modal.classList.remove('show');
        modal.classList.add('hidden');
        modal.style.display = 'none';
      });
    });

    // ================================================================
    // SECTION: Editable Comments / Remarks
  // ================================================================
  const loadComments = (fileNumber) => {
    if (!fileNumber) return;
    fetch(`/legal_search/comments?file_number=${encodeURIComponent(fileNumber)}`, {
      headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(res => {
      if (res.success && res.data) {
        const gr = res.data.ground_rent;
        document.getElementById('comment-ground_rent-amount').value = gr?.amount ?? '';
        document.getElementById('comment-ground_rent-text').value = gr?.comment ?? '';

        if (res.data.no_cofo?.comment) {
          document.getElementById('comment-no_cofo-text').value = res.data.no_cofo.comment;
        }
        if (res.data.encumbrance?.comment) {
          document.getElementById('comment-encumbrance-text').value = res.data.encumbrance.comment;
        }
      } else {
        document.getElementById('comment-ground_rent-amount').value = '';
        document.getElementById('comment-ground_rent-text').value = '';
      }
    })
    .catch(() => {});
  };

  const showCommentSections = (transactions) => {
    const types = transactions.map(t => (t.transaction_type || '').toLowerCase());
    const hasCofo = types.some(t =>
      t.includes('certificate of occupancy') || t.includes('c of o') ||
      t.includes('cofo') || t.includes('c.of.o') || t.includes('c/o/o')
    );
    const hasCaveat = transactions.some(t => t.is_caveated == 1);
    const hasMortgage = types.some(t => t.includes('deed of mortgage'));
    const hasRelease = types.some(t => t.includes('deed of surrender and release'));
    const mortgageCaveat = hasMortgage && !hasRelease;
    const isClear = hasCofo && !hasCaveat && !mortgageCaveat;

    // No CoFO section: show when file has no CoFO
    const noCofoSection = document.getElementById('no-cofo-comment-section');
    if (noCofoSection) {
      noCofoSection.classList.toggle('hidden', hasCofo);
    }

    // Encumbrance section: show when file is clear (has CoFO, no caveat, no mortgage issue)
    const encSection = document.getElementById('encumbrance-comment-section');
    if (encSection) {
      encSection.classList.toggle('hidden', !isClear);
    }
  };

  document.querySelectorAll('.save-comment-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const commentType = btn.dataset.type;
      const fileNumber = window._currentFileNumber || document.getElementById('file-number-value')?.textContent?.trim();
      if (!fileNumber) return alert('No file selected.');

      const amount = document.getElementById(`comment-${commentType}-amount`)?.value || null;
      const comment = document.getElementById(`comment-${commentType}-text`)?.value || '';
      const statusEl = document.querySelector(`.comment-status[data-type="${commentType}"]`);

      fetch('/legal_search/comments', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        },
        body: JSON.stringify({
          file_number: fileNumber,
          prop_id: window._currentPropId || '',
          comment_type: commentType,
          amount: amount,
          comment: comment
        })
      })
      .then(r => r.json())
      .then(res => {
        if (statusEl) {
          statusEl.textContent = res.success ? 'Saved successfully.' : (res.message || 'Failed to save.');
          statusEl.classList.remove('hidden', 'text-red-600', 'text-green-600');
          statusEl.classList.add(res.success ? 'text-green-600' : 'text-red-600');
          setTimeout(() => statusEl.classList.add('hidden'), 3000);
        }
      })
      .catch(() => {
        if (statusEl) {
          statusEl.textContent = 'Network error.';
          statusEl.classList.remove('hidden', 'text-green-600');
          statusEl.classList.add('text-red-600');
        }
      });
    });
  });

  // ================================================================
  // SECTION: Cleanup Mode Logic
  // ================================================================
  let cleanupModeActive = false;

  const getSelectedRecords = () => {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    const byTable = {};
    checked.forEach(cb => {
      const table = cb.dataset.table;
      const id = parseInt(cb.dataset.id, 10);
      if (!byTable[table]) byTable[table] = [];
      byTable[table].push(id);
    });
    return byTable;
  };

  const getSelectedCount = () => document.querySelectorAll('.row-checkbox:checked').length;

  const updateSelectionCount = () => {
    const count = getSelectedCount();
    const countEl = document.getElementById('cleanup-selection-count');
    if (countEl) {
      countEl.textContent = `${count} selected`;
      countEl.classList.toggle('hidden', count === 0);
    }
  };

  const toggleCleanupMode = (active) => {
    cleanupModeActive = active;
    const toggle = document.getElementById('cleanup-mode-toggle');
    const dot = document.getElementById('cleanup-toggle-dot');
    const buttons = ['cleanup-match-btn', 'cleanup-drop-btn', 'cleanup-remove-btn', 'cleanup-edit-btn'];

    if (active) {
      toggle.classList.remove('bg-gray-200');
      toggle.classList.add('bg-black');
      dot.classList.remove('translate-x-0');
      dot.classList.add('translate-x-5');
      toggle.setAttribute('aria-checked', 'true');
    } else {
      toggle.classList.add('bg-gray-200');
      toggle.classList.remove('bg-black');
      dot.classList.add('translate-x-0');
      dot.classList.remove('translate-x-5');
      toggle.setAttribute('aria-checked', 'false');
      // Uncheck all
      document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = false; });
      document.querySelectorAll('.select-all-checkbox').forEach(cb => { cb.checked = false; });
      document.querySelectorAll('tr.row-selected').forEach(tr => tr.classList.remove('row-selected'));
    }

    // Show/hide checkbox columns
    document.querySelectorAll('.cleanup-col').forEach(el => {
      el.classList.toggle('hidden', !active);
    });

    // Enable/disable action buttons
    buttons.forEach(id => {
      const btn = document.getElementById(id);
      if (btn) btn.disabled = !active;
    });

    updateSelectionCount();
  };

  // Toggle event
  const cleanupToggle = document.getElementById('cleanup-mode-toggle');
  if (cleanupToggle) {
    cleanupToggle.addEventListener('click', () => {
      toggleCleanupMode(!cleanupModeActive);
    });
  }

  // Checkbox events (delegated)
  document.addEventListener('change', (e) => {
    if (e.target.classList.contains('row-checkbox')) {
      const tr = e.target.closest('tr');
      if (tr) tr.classList.toggle('row-selected', e.target.checked);
      updateSelectionCount();
    }
    if (e.target.classList.contains('select-all-checkbox')) {
      const tableId = e.target.dataset.table;
      const tbody = document.getElementById(tableId);
      if (tbody) {
        tbody.querySelectorAll('.row-checkbox').forEach(cb => {
          cb.checked = e.target.checked;
          const tr = cb.closest('tr');
          if (tr) tr.classList.toggle('row-selected', cb.checked);
        });
      }
      updateSelectionCount();
    }
  });

  // CSRF token helper
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  const cleanupAjax = async (url, body) => {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      body: JSON.stringify(body),
    });
    return res.json();
  };

  const swalAlert = async (message, icon = 'info', title = '') => {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      await window.Swal.fire({
        title,
        text: message,
        icon,
        confirmButtonText: 'OK',
      });
      return;
    }
    alert(message);
  };

  const swalConfirm = async (message, title = 'Are you sure?', icon = 'warning') => {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      const result = await window.Swal.fire({
        title,
        text: message,
        icon,
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
      });
      return !!result.isConfirmed;
    }
    return confirm(message);
  };

  // Re-run the current search to refresh results after cleanup
  let _pendingCleanupRefresh = false;
  const refreshAfterCleanup = (droppedRecords) => {
    // Remove dropped/removed records from in-memory data and re-render
    if (droppedRecords && fileHistoryView && !fileHistoryView.classList.contains('hidden')) {
      // Build a set of "table:id" keys to remove
      const removeKeys = new Set();
      for (const [table, ids] of Object.entries(droppedRecords)) {
        ids.forEach(id => removeKeys.add(`${table}:${id}`));
      }

      // Filter out dropped records from searchResults
      searchResults = searchResults.filter(r => {
        const key = `${timelineSourceToDbTable(r.source_table)}:${r.id}`;
        return !removeKeys.has(key);
      });

      // Update selectedFile to first remaining result
      if (searchResults.length > 0) {
        selectedFile = searchResults[0];
      }

      // Re-render file history (source tabs + timeline)
      renderFileHistory();

      // Uncheck all and update selection count
      document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = false; });
      document.querySelectorAll('.select-all-checkbox').forEach(cb => { cb.checked = false; });
      document.querySelectorAll('tr.row-selected').forEach(tr => tr.classList.remove('row-selected'));
      updateSelectionCount();
      return;
    }

    // Fallback: re-run search
    _pendingCleanupRefresh = true;
    if (typeof performSearch === 'function') {
      performSearch();
    }
  };

  // MATCH button
  const matchBtn = document.getElementById('cleanup-match-btn');
  if (matchBtn) {
    matchBtn.addEventListener('click', async () => {
      const byTable = getSelectedRecords();
      const tables = Object.keys(byTable);
      if (tables.length === 0) {
        await swalAlert('Select at least one record.', 'warning', 'No Selection');
        return;
      }

      // Find records with prop_id (targets) and without (orphans)
      const allChecked = document.querySelectorAll('.row-checkbox:checked');
      let targetPropId = null;
      const orphanSelections = {};

      allChecked.forEach(cb => {
        const pid = cb.dataset.propId;
        if (pid && pid.trim() !== '') {
          targetPropId = pid.trim();
        } else {
          const table = cb.dataset.table;
          const id = parseInt(cb.dataset.id, 10);
          if (!orphanSelections[table]) orphanSelections[table] = [];
          orphanSelections[table].push(id);
        }
      });

      if (!targetPropId) {
        await swalAlert('Select at least one record WITH a prop_id as the target.', 'warning', 'Target Required');
        return;
      }
      if (Object.keys(orphanSelections).length === 0) {
        await swalAlert('Select at least one orphan record (without prop_id) to match.', 'warning', 'Orphan Required');
        return;
      }

      const proceed = await swalConfirm(`Match selected orphan records to prop_id: ${targetPropId}?`, 'Confirm Match', 'question');
      if (!proceed) return;

      for (const [table, ids] of Object.entries(orphanSelections)) {
        await cleanupAjax('/legal_search/match', { table, ids, prop_id: targetPropId });
      }
      await swalAlert('Records matched successfully.', 'success', 'Completed');
      refreshAfterCleanup();
    });
  }

  // DROP button
  const dropBtn = document.getElementById('cleanup-drop-btn');
  if (dropBtn) {
    dropBtn.addEventListener('click', async () => {
      const byTable = getSelectedRecords();
      if (Object.keys(byTable).length === 0) {
        await swalAlert('Select at least one record.', 'warning', 'No Selection');
        return;
      }
      const proceed = await swalConfirm('Drop selected records from their prop_id group? (They will become orphan records.)', 'Confirm Drop', 'warning');
      if (!proceed) return;

      for (const [table, ids] of Object.entries(byTable)) {
        await cleanupAjax('/legal_search/drop', { table, ids });
      }
      await swalAlert('Records dropped successfully.', 'success', 'Completed');
      refreshAfterCleanup(byTable);
    });
  }

  // REMOVE button
  const removeBtn = document.getElementById('cleanup-remove-btn');
  if (removeBtn) {
    removeBtn.addEventListener('click', async () => {
      const byTable = getSelectedRecords();
      if (Object.keys(byTable).length === 0) {
        await swalAlert('Select at least one record.', 'warning', 'No Selection');
        return;
      }
      const proceed = await swalConfirm('Remove (soft-delete) selected records? They will no longer appear in results.', 'Confirm Remove', 'warning');
      if (!proceed) return;

      for (const [table, ids] of Object.entries(byTable)) {
        await cleanupAjax('/legal_search/remove', { table, ids });
      }
      await swalAlert('Records removed successfully.', 'success', 'Completed');
      refreshAfterCleanup(byTable);
    });
  }

  // ================================================================
  // SECTION: Edit Modal
  // ================================================================
  // Field definitions per DB table for the edit form
  const editFieldDefs = {
    'file_history_staging': [
      // { key: 'kangisFileNo', label: 'KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'fileno', label: 'File No', readonly: true },
      // { key: 'mlsFNo', label: 'MLS File No', type: 'fileno' },  // hidden — using file number selector
      // { key: 'NewKANGISFileno', label: 'New KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'serialNo', label: 'Serial No', type: 'particular', sectionStart: 'Registration Number' },
      { key: 'pageNo', label: 'Page No', type: 'particular' },
      { key: 'volumeNo', label: 'Volume No', type: 'particular' },
      { key: 'regNo', label: 'Reg No', readonly: true, sectionEnd: true },
      { key: 'reg_date', label: 'Reg Date', type: 'date' },
      { key: 'reg_time', label: 'Reg Time' },
      { key: 'transaction_type', label: 'Instrument Type', type: 'select', optionSource: 'transaction_type' },
      { key: 'transaction_date', label: 'Transaction Date', type: 'date' },
      { key: 'party_1', label: 'Party 1' },
      { key: 'party_2', label: 'Party 2' },
      { key: 'party_3', label: 'Party 3' },
      { key: 'party_4', label: 'Party 4' },
      { key: 'land_use', label: 'Land Use', type: 'select', optionSource: 'land_use' },
      { key: 'plot_no', label: 'Plot No' },
      { key: 'plot_size', label: 'Plot Size' },
      { key: 'districtName', label: 'District', type: 'select', optionSource: 'district' },
      { key: 'lgsaOrCity', label: 'LGA/City', type: 'select', optionSource: 'lga' },
      { key: 'location', label: 'Property Location' },
      { key: 'comments', label: 'Comments', type: 'textarea' }, { key: 'remarks', label: 'Remarks', type: 'textarea' },
    ],
    'CofO_staging': [
      // { key: 'kangisFileNo', label: 'KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'fileno', label: 'File No', readonly: true },
      // { key: 'mlsFNo', label: 'MLS File No', type: 'fileno' },  // hidden — using file number selector
      // { key: 'NewKANGISFileno', label: 'New KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'np_fileno', label: 'NP File No', type: 'fileno' },
      { key: 'serialNo', label: 'Serial No', type: 'particular', sectionStart: 'Registration Number' },
      { key: 'pageNo', label: 'Page No', type: 'particular' },
      { key: 'volumeNo', label: 'Volume No', type: 'particular' },
      { key: 'regNo', label: 'Reg No', readonly: true, sectionEnd: true },
      { key: 'reg_date', label: 'Reg Date', type: 'date' },
      { key: 'reg_time', label: 'Reg Time' },
      { key: 'transaction_type', label: 'Instrument Type', type: 'select', optionSource: 'transaction_type' },
      { key: 'transaction_date', label: 'Transaction Date', type: 'date' },
      { key: 'Grantor', label: 'Party 1' },
      { key: 'Grantee', label: 'Party 2' },
      { key: 'party_3', label: 'Party 3' },
      { key: 'party_4', label: 'Party 4' },
      { key: 'land_use', label: 'Land Use', type: 'select', optionSource: 'land_use' },
      { key: 'plot_no', label: 'Plot No' },
      { key: 'lgsaOrCity', label: 'LGA/City', type: 'select', optionSource: 'lga' },
      { key: 'location', label: 'Property Location' },
      { key: 'period', label: 'Period' }, { key: 'period_unit', label: 'Period Unit' },
      { key: 'comments', label: 'Comments', type: 'textarea' }, { key: 'remarks', label: 'Remarks', type: 'textarea' },
    ],
    'pra': [
      // { key: 'kangisFileNo', label: 'KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'fileno', label: 'File No', readonly: true },
      // { key: 'mlsFNo', label: 'MLS File No', type: 'fileno' },  // hidden — using file number selector
      // { key: 'NewKANGISFileno', label: 'New KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'serialNo', label: 'Serial No', type: 'particular', sectionStart: 'Registration Number' },
      { key: 'pageNo', label: 'Page No', type: 'particular' },
      { key: 'volumeNo', label: 'Volume No', type: 'particular' },
      { key: 'regNo', label: 'Reg No', readonly: true, sectionEnd: true },
      { key: 'reg_date', label: 'Reg Date', type: 'date' },
      { key: 'reg_time', label: 'Reg Time' },
      { key: 'transaction_type', label: 'Instrument Type', type: 'select', optionSource: 'transaction_type' },
      { key: 'transaction_date', label: 'Transaction Date', type: 'date' },
      { key: 'party_1', label: 'Party 1' }, { key: 'party_2', label: 'Party 2' },
      { key: 'party_3', label: 'Party 3' }, { key: 'party_4', label: 'Party 4' },
      { key: 'land_use', label: 'Land Use', type: 'select', optionSource: 'land_use' },
      { key: 'plot_no', label: 'Plot No' },
      { key: 'plot_size', label: 'Plot Size' },
      { key: 'districtName', label: 'District', type: 'select', optionSource: 'district' },
      { key: 'lgsaOrCity', label: 'LGA/City', type: 'select', optionSource: 'lga' },
      { key: 'location', label: 'Property Location' },
      { key: 'comments', label: 'Comments', type: 'textarea' }, { key: 'remarks', label: 'Remarks', type: 'textarea' },
    ],
    'deed_registrations': [
      { key: 'fileno', label: 'File No', readonly: true },
      { key: 'parent_fileno', label: 'MLS/NP File No', type: 'fileno' },
      { key: 'serial_no', label: 'Serial No', type: 'particular', sectionStart: 'Registration Number' },
      { key: 'page_no', label: 'Page No', type: 'particular' },
      { key: 'volume_no', label: 'Volume No', type: 'particular' },
      { key: 'registration_number', label: 'Reg No', readonly: true, sectionEnd: true },
      { key: 'instrument_type', label: 'Instrument Type', type: 'select', optionSource: 'transaction_type' },
      { key: 'deeds_date', label: 'Deeds Date', type: 'date' }, { key: 'deeds_time', label: 'Deeds Time' },
      { key: 'instrument_date', label: 'Instrument Date', type: 'date' },
      { key: 'grantor', label: 'Party 1' }, { key: 'grantee', label: 'Party 2' },
      { key: 'lga', label: 'LGA' }, { key: 'district', label: 'District' },
      { key: 'plot_number', label: 'Plot Number' }, { key: 'size', label: 'Size' },
      { key: 'property_description', label: 'Property Description', type: 'textarea' },
    ],
  };

  const firstFilled = (...values) => values.find(v => v !== null && v !== undefined && String(v).trim() !== '');

  const updateRegNoFromParticulars = () => {
    const serialInput = document.querySelector('#edit-modal-body [name="serialNo"], #edit-modal-body [name="serial_no"]');
    const pageInput = document.querySelector('#edit-modal-body [name="pageNo"], #edit-modal-body [name="page_no"]');
    const volumeInput = document.querySelector('#edit-modal-body [name="volumeNo"], #edit-modal-body [name="volume_no"]');
    const regNoInput = document.querySelector('#edit-modal-body [name="regNo"], #edit-modal-body [name="registration_number"]');

    if (!serialInput || !pageInput || !volumeInput || !regNoInput) return;

    const serial = String(serialInput.value || '').trim() || '0';
    const page = String(pageInput.value || '').trim() || '0';
    const volume = String(volumeInput.value || '').trim() || '0';
    regNoInput.value = `${serial}/${page}/${volume}`;
  };

  const bindParticularsToRegNo = () => {
    const particulars = document.querySelectorAll('#edit-modal-body [name="serialNo"], #edit-modal-body [name="pageNo"], #edit-modal-body [name="volumeNo"], #edit-modal-body [name="serial_no"], #edit-modal-body [name="page_no"], #edit-modal-body [name="volume_no"]');
    particulars.forEach(el => {
      el.addEventListener('input', updateRegNoFromParticulars);
    });
    updateRegNoFromParticulars();
  };

  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  const getModalSelectOptions = (source, record, fieldKey) => {
    const unique = new Set();

    const add = (v) => {
      const val = String(v ?? '').trim();
      if (val !== '') unique.add(val);
    };

    if (source === 'land_use') {
      dbLandUseOptions.forEach(add);
      add(record?.land_use);
    }

    if (source === 'lga') {
      const lgaSelect = document.getElementById('lga');
      if (lgaSelect) {
        Array.from(lgaSelect.options).forEach(opt => add(opt.value));
      }
      add(record?.lgsaOrCity);
      add(record?.lga);
      (searchResults || []).forEach(r => add(r?.lgsaOrCity || r?.lga));
    }

    if (source === 'district') {
      dbDistrictOptions.forEach(add);
      add(record?.districtName);
      add(record?.district);
    }

    if (source === 'transaction_type') {
      [
        'Certificate of Occupancy',
        'Customary Right of Occupancy',
        'Occupancy Permit (OP)',
        'Deed of Assignment',
        'ST Assignment',
        'Deed of Mortgage',
        'Tripartite Mortgage',
        'Deed of Lease',
        'Deed of Transfer',
        'Deed of Surrender',
        'Power of Attorney',
        'Release of Mortgage',
        'Fragmentation',
        'ST Fragmentation',
        'Other'
      ].forEach(add);

      add(record?.transaction_type);
      add(record?.instrument_type);
      add(record?.title_type);
      (searchResults || []).forEach(r => {
        add(r?.transaction_type);
        add(r?.instrument_type);
        add(r?.title_type);
      });
    }

    // If no source options are available, still keep current value selectable
    if (unique.size === 0) {
      add(record?.[fieldKey]);
    }

    return Array.from(unique).sort((a, b) => a.localeCompare(b));
  };

  let editingRecord = { table: null, id: null };

  const getEditFieldSpanClass = (field) => {
    const fullWidthKeys = ['location', 'property_description', 'comments', 'remarks'];
    if (field.type === 'textarea' || fullWidthKeys.includes(field.key)) {
      return 'lg:col-span-12';
    }
    if (field.type === 'particular') {
      return 'lg:col-span-4';
    }
    return 'lg:col-span-6';
  };

  const openEditModal = async (table, id) => {
    editingRecord = { table, id };
    const modal = document.getElementById('edit-record-modal');
    const body = document.getElementById('edit-modal-body');
    body.innerHTML = '<div class="text-center py-8 text-gray-500">Loading...</div>';
    modal.classList.remove('hidden');

    const res = await cleanupAjax('/legal_search/get-record', { table, id });
    if (!res.success) {
      body.innerHTML = `<div class="text-center py-8 text-red-500">${res.message || 'Failed to load record.'}</div>`;
      return;
    }

    const record = res.data;
    const fields = editFieldDefs[table] || [];
    
    let html = '<div class="grid grid-cols-1 lg:grid-cols-12 gap-4">';
    let inSection = false;
    fields.forEach(f => {
      let val = record[f.key] ?? '';
      // For the readonly fileno field, fall back to any available file number
      if (f.key === 'fileno' && !val) {
        val = record.mlsFNo || record.file_number || record.kangisFileNo || record.NewKANGISFileno || '';
      }
      // Section grouping (Registration Number)
      if (f.sectionStart) {
        html += `<div class="lg:col-span-12 border border-gray-200 rounded-lg p-4 mt-1">
          <label class="block text-xs font-semibold uppercase tracking-wide text-blue-700 mb-3">${f.sectionStart}</label>
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">`;
        inSection = true;
      }
      if (f.type === 'textarea') {
        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <textarea name="${f.key}" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-black focus:border-black">${val}</textarea>
          </div>`;
      } else if (f.type === 'date') {
        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <input type="date" name="${f.key}" value="${val ? val.substring(0, 10) : ''}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-black focus:border-black">
          </div>`;
      } else if (f.type === 'fileno') {
        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <div class="flex gap-2">
              <input type="text" name="${f.key}" value="${String(val).replace(/"/g, '&quot;')}" class="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-black focus:border-black">
              <button type="button" class="edit-fileno-picker inline-flex items-center px-2.5 py-2 text-xs font-medium border border-gray-300 rounded-md bg-gray-50 hover:bg-gray-100" data-target="${f.key}">Pick</button>
            </div>
          </div>`;
      } else if (f.type === 'select') {
        const options = getModalSelectOptions(f.optionSource, record, f.key);
        const optionsHtml = options.map(opt => {
          const selected = String(val) === String(opt) ? 'selected' : '';
          return `<option value="${escapeHtml(opt)}" ${selected}>${escapeHtml(opt)}</option>`;
        }).join('');

        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <select name="${f.key}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-black focus:border-black">
              <option value="">Select ${escapeHtml(f.label)}</option>
              ${optionsHtml}
            </select>
          </div>`;
      } else {
        const readOnlyAttr = f.readonly ? 'readonly' : '';
        const readOnlyClass = f.readonly ? 'bg-gray-50' : '';
        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <input type="text" name="${f.key}" value="${String(val).replace(/"/g, '&quot;')}" ${readOnlyAttr} class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-black focus:border-black ${readOnlyClass}">
          </div>`;
      }
      // Close section grouping
      if (f.sectionEnd && inSection) {
        html += '</div></div>';
        inSection = false;
      }
    });
    html += '</div>';
    body.innerHTML = html;
    bindParticularsToRegNo();
  };

  const closeEditModal = () => {
    document.getElementById('edit-record-modal').classList.add('hidden');
    editingRecord = { table: null, id: null };
  };

  const saveEditModal = async () => {
    const body = document.getElementById('edit-modal-body');
    const fields = {};
    body.querySelectorAll('input, textarea, select').forEach(el => {
      if (el.name) fields[el.name] = el.value;
    });

    const saveBtn = document.getElementById('edit-modal-save');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    const res = await cleanupAjax('/legal_search/update', {
      table: editingRecord.table,
      id: editingRecord.id,
      fields,
    });

    saveBtn.disabled = false;
    saveBtn.textContent = 'Save Changes';

    if (res.success) {
      closeEditModal();
      refreshAfterCleanup();
    } else {
      alert(res.message || 'Failed to save.');
    }
  };

  // Edit modal events
  document.getElementById('edit-modal-close')?.addEventListener('click', closeEditModal);
  document.getElementById('edit-modal-cancel')?.addEventListener('click', closeEditModal);
  document.getElementById('edit-modal-backdrop')?.addEventListener('click', closeEditModal);
  document.getElementById('edit-modal-save')?.addEventListener('click', saveEditModal);

  // EDIT button (cleanup toolbar) - opens edit for first selected record
  const editBtn = document.getElementById('cleanup-edit-btn');
  if (editBtn) {
    editBtn.addEventListener('click', () => {
      const firstChecked = document.querySelector('.row-checkbox:checked');
      if (!firstChecked) return alert('Select a record to edit.');
      openEditModal(firstChecked.dataset.table, parseInt(firstChecked.dataset.id, 10));
    });
  }

  // Inline edit-action buttons on each row (delegated)
  document.addEventListener('click', (e) => {
    const editBtn = e.target.closest('.edit-action');
    if (editBtn) {
      const table = editBtn.dataset.table;
      const id = parseInt(editBtn.dataset.id, 10);
      if (table && id) openEditModal(table, id);
    }
  });

  // CofO action menu toggle
  document.addEventListener('click', (e) => {
    const toggleBtn = e.target.closest('.cofo-action-toggle');
    if (toggleBtn) {
      e.stopPropagation();
      const wrapper = toggleBtn.closest('.relative');
      const menu = wrapper?.querySelector('.cofo-action-menu');
      if (!menu) return;

      document.querySelectorAll('.cofo-action-menu').forEach(m => {
        if (m !== menu) m.classList.add('hidden');
      });
      menu.classList.toggle('hidden');
      return;
    }

    if (!e.target.closest('.cofo-action-menu')) {
      document.querySelectorAll('.cofo-action-menu').forEach(m => m.classList.add('hidden'));
    }
  });

  // CofO add comment action
  document.addEventListener('click', async (e) => {
    const addCommentBtn = e.target.closest('.cofo-add-comment-action');
    if (!addCommentBtn) return;

    const cofoId = parseInt(addCommentBtn.dataset.id, 10);
    if (!cofoId) return;

    const row = addCommentBtn.closest('tr');
    const existingComment = (row?.dataset.cofoComment || row?.dataset.cofoFallbackComment || '').trim();

    let nextComment = existingComment;

    if (typeof Swal !== 'undefined') {
      const res = await Swal.fire({
        title: 'Add CofO Comment',
        input: 'textarea',
        inputLabel: 'Comment',
        inputValue: existingComment,
        inputPlaceholder: 'Enter CofO comment...',
        showCancelButton: true,
        confirmButtonText: 'Save',
        cancelButtonText: 'Cancel',
      });
      if (!res.isConfirmed) return;
      nextComment = (res.value || '').trim();
    } else {
      const fallback = prompt('Enter CofO comment:', existingComment);
      if (fallback === null) return;
      nextComment = fallback.trim();
    }

    try {
      const saveRes = await fetch('/legal_search/cofo-comment', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          cofo_id: cofoId,
          cofo_comment: nextComment,
        }),
      });

      const payload = await saveRes.json();
      if (!saveRes.ok || !payload.success) {
        throw new Error(payload.message || 'Failed to update CofO comment.');
      }

      if (row) row.dataset.cofoComment = nextComment;

      if (typeof Swal !== 'undefined') {
        await Swal.fire({ icon: 'success', title: 'Saved', text: payload.message || 'CofO comment updated.' });
      } else {
        alert(payload.message || 'CofO comment updated.');
      }
    } catch (err) {
      if (typeof Swal !== 'undefined') {
        await Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Failed to update comment.' });
      } else {
        alert(err.message || 'Failed to update comment.');
      }
    }
  });

  // File number picker buttons inside edit modal
  document.addEventListener('click', (e) => {
    const pickerBtn = e.target.closest('.edit-fileno-picker');
    if (!pickerBtn) return;

    if (typeof window.GlobalFileNoModal === 'undefined' || typeof window.GlobalFileNoModal.open !== 'function') {
      alert('Global File Number selector is not available.');
      return;
    }

    const targetName = pickerBtn.dataset.target;

    window.GlobalFileNoModal.open({
      autoPopulateGenericFields: false,
      callback: function(fileData) {
        const fileNo = fileData?.fileNumber || '';
        const rec = fileData?.record || {};

        const setIfExists = (name, value) => {
          const input = document.querySelector(`#edit-modal-body [name="${name}"]`);
          if (input && value !== null && value !== undefined && String(value).trim() !== '') {
            input.value = String(value);
          }
        };

        setIfExists(targetName, fileNo);

        const mlsValue = firstFilled(rec.mlsFNo, rec.mls_fileno, rec.mls_file_no, rec.MLSFileNo, rec.mls, fileNo);
        const kangisValue = firstFilled(rec.kangisFileNo, rec.kangis_file_no, rec.kangis, rec.KANGISFileNo, fileNo);
        const newKangisValue = firstFilled(rec.NewKANGISFileno, rec.new_kangis_fileno, rec.new_kangis, rec.newKangisFileNo, fileNo);
        const genericFileNo = firstFilled(rec.fileno, rec.file_no, rec.fileNumber, fileNo);
        const parentFileNo = firstFilled(rec.parent_fileno, rec.parentFileNo, rec.np_fileno, rec.npFileNo, fileNo);

        setIfExists('mlsFNo', mlsValue);
        setIfExists('kangisFileNo', kangisValue);
        setIfExists('NewKANGISFileno', newKangisValue);
        setIfExists('fileno', genericFileNo);
        setIfExists('np_fileno', parentFileNo);
        setIfExists('parent_fileno', parentFileNo);
      }
    });
  });

  // Conflict modal events
  document.getElementById('conflict-cancel')?.addEventListener('click', () => {
    document.getElementById('conflict-modal').classList.add('hidden');
  });
  document.getElementById('conflict-modal-backdrop')?.addEventListener('click', () => {
    document.getElementById('conflict-modal').classList.add('hidden');
  });

  // ================================================================
  // SECTION: Arrange Mode (drag-and-drop in timeline)
  // ================================================================
  let arrangeModeActive = false;

  const toggleArrangeMode = (active) => {
    arrangeModeActive = active;
    const btn = document.getElementById('arrange-mode-btn');
    const wrapper = document.getElementById('timeline-table-wrapper');

    if (active) {
      btn.classList.add('bg-purple-600', 'text-white', 'border-purple-600');
      btn.classList.remove('bg-purple-50', 'text-purple-700', 'border-purple-200');
      btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> Done`;
      wrapper?.classList.add('arrange-active');
      document.querySelectorAll('.arrange-col').forEach(el => el.classList.remove('hidden'));
      enableDragAndDrop();
    } else {
      btn.classList.remove('bg-purple-600', 'text-white', 'border-purple-600');
      btn.classList.add('bg-purple-50', 'text-purple-700', 'border-purple-200');
      btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg> Arrange`;
      wrapper?.classList.remove('arrange-active');
      document.querySelectorAll('.arrange-col').forEach(el => el.classList.add('hidden'));
      disableDragAndDrop();
    }
  };

  let draggedRow = null;

  const saveTimelineArrangement = async () => {
    const propId = getTimelinePropId();
    if (!propId) return;

    const tbody = document.getElementById('timeline-table');
    if (!tbody) return;

    const items = Array.from(tbody.querySelectorAll('tr'))
      .map((row, index) => ({
        table: row.dataset.table,
        id: parseInt(row.dataset.id, 10),
        order: index + 1,
      }))
      .filter(item => item.table && Number.isInteger(item.id) && item.id > 0);

    if (items.length === 0) return;

    const res = await cleanupAjax('/legal_search/save-arrangement', { prop_id: propId, items });
    if (!res || !res.success) {
      throw new Error(res?.message || 'Failed to save arrangement.');
    }
  };

  const enableDragAndDrop = () => {
    const tbody = document.getElementById('timeline-table');
    if (!tbody) return;

    tbody.querySelectorAll('tr').forEach(row => {
      row.draggable = true;
      row.addEventListener('dragstart', handleDragStart);
      row.addEventListener('dragover', handleDragOver);
      row.addEventListener('drop', handleDrop);
      row.addEventListener('dragend', handleDragEnd);
    });
  };

  const disableDragAndDrop = () => {
    const tbody = document.getElementById('timeline-table');
    if (!tbody) return;

    tbody.querySelectorAll('tr').forEach(row => {
      row.draggable = false;
      row.removeEventListener('dragstart', handleDragStart);
      row.removeEventListener('dragover', handleDragOver);
      row.removeEventListener('drop', handleDrop);
      row.removeEventListener('dragend', handleDragEnd);
    });
  };

  function handleDragStart(e) {
    draggedRow = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
  }

  function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
  }

  async function handleDrop(e) {
    e.preventDefault();
    if (draggedRow !== this) {
      const tbody = this.parentNode;
      const rows = Array.from(tbody.children);
      const dragIdx = rows.indexOf(draggedRow);
      const dropIdx = rows.indexOf(this);
      if (dragIdx < dropIdx) {
        tbody.insertBefore(draggedRow, this.nextSibling);
      } else {
        tbody.insertBefore(draggedRow, this);
      }
      renumberTimeline();

      try {
        await saveTimelineArrangement();
      } catch (err) {
        console.error('Timeline arrangement save failed:', err);
        alert('Could not save arrangement. Please try again.');
      }
    }
  }

  function handleDragEnd() {
    this.classList.remove('dragging');
    draggedRow = null;
  }

  const renumberTimeline = () => {
    const tbody = document.getElementById('timeline-table');
    if (!tbody) return;
    tbody.querySelectorAll('tr').forEach((row, idx) => {
      const numCell = row.querySelector('.arrange-col');
      if (numCell) numCell.textContent = idx + 1;
    });
  };

  // Arrange button event
  const arrangeBtn = document.getElementById('arrange-mode-btn');
  if (arrangeBtn) {
    arrangeBtn.addEventListener('click', () => {
      toggleArrangeMode(!arrangeModeActive);
    });
  }

  // Print report button - uses the timeline order
  if (printReportBtn) {
    printReportBtn.addEventListener('click', (ev) => {
      ev.preventDefault();
      const tplUrl = window.LEGAL_SEARCH_CONTEXT?.printTemplateUrl;
      if (tplUrl) {
        const q = new URLSearchParams();
        const fileNo = selectedFile?.fileno || selectedFile?.file_number || selectedFile?.mlsFNo || '';
        const propId = selectedFile?.prop_id || '';
        if (fileNo) q.set('file_number', fileNo);
        if (propId) q.set('prop_id', propId);
        const url = q.toString() ? `${tplUrl}?${q.toString()}` : tplUrl;

        // If print-manager doc type is set, open print-manager modal instead
        const pmDocType = window.LEGAL_SEARCH_CONTEXT?.printManagerDocType;
        if (pmDocType) {
          window.dispatchEvent(new CustomEvent('open-print-manager', {
            detail: { ref: fileNo, type: pmDocType, url: url }
          }));
          return;
        }

        window.open(url, '_blank', 'noopener,noreferrer');
        return;
      }

      if (typeof renderLegalSearchReport === 'function') {
        renderLegalSearchReport();
      }
    });
  }
  document.querySelectorAll('[data-view]').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('[data-view]').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      currentView = tab.getAttribute('data-view');
      renderSearchResults();
    });
  });

  // Add event delegation for tab switching and View Detailed Records button
  document.addEventListener('click', (e) => {
    // Tab switching
    if (e.target.closest('.tab')) {
      const tabName = e.target.closest('.tab').getAttribute('data-tab');
      if (tabName) {
        switchTab(tabName);
      }
    }

    // View Detailed Records button
    if (e.target.closest('#view-detailed-records-btn')) {
      const tplUrl = window.LEGAL_SEARCH_CONTEXT?.printTemplateUrl;
      if (tplUrl) {
        const q = new URLSearchParams();
        const fileNo = selectedFile?.fileno || selectedFile?.file_number || selectedFile?.mlsFNo || '';
        const propId = selectedFile?.prop_id || '';
        if (fileNo) q.set('file_number', fileNo);
        if (propId) q.set('prop_id', propId);
        const url = q.toString() ? `${tplUrl}?${q.toString()}` : tplUrl;

        // If print-manager doc type is set, open print-manager modal instead
        const pmDocType = window.LEGAL_SEARCH_CONTEXT?.printManagerDocType;
        if (pmDocType) {
          window.dispatchEvent(new CustomEvent('open-print-manager', {
            detail: { ref: fileNo, type: pmDocType, url: url }
          }));
          return;
        }

        window.open(url, '_blank', 'noopener,noreferrer');
        return;
      }

      // Fallback only if template route is unavailable
      fileHistoryView.classList.add('hidden');
      legalSearchReportView.classList.remove('hidden');
      renderLegalSearchReport();
    }

    // Back to file details from legal search report view
    if (e.target.closest('#back-to-file-details-btn')) {
      legalSearchReportView.classList.add('hidden');
      fileHistoryView.classList.remove('hidden');
    }

    // Delete action button (placeholder)
    if (e.target.closest('.delete-action')) {
      alert('Delete functionality would be implemented here.');
    }
  });

  // Close modal when pressing Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (searchModal) {
        searchModal.classList.add('hidden');
      }
      const editModal = document.getElementById('edit-record-modal');
      if (editModal && !editModal.classList.contains('hidden')) {
        closeEditModal();
      }
      const conflictModal = document.getElementById('conflict-modal');
      if (conflictModal && !conflictModal.classList.contains('hidden')) {
        conflictModal.classList.add('hidden');
      }
    }
  });



