(function () {
  const cfg = window.PHS_PORTAL || {};
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  let selectedPackage = null;
  let selectedResult = null;

  const $ = (id) => document.getElementById(id);
  const fmt = (n) => new Intl.NumberFormat().format(Number(n || 0));
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));

  function show(el) { el?.classList.remove('hidden'); }
  function hide(el) { el?.classList.add('hidden'); }

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify(payload || {})
    }).then(async (response) => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw data;
      return data;
    });
  }

  function initMobileMenu() {
    const btn = $('dashboard-mobile-menu-btn');
    const menu = $('dashboard-mobile-menu');
    btn?.addEventListener('click', () => {
      const closed = menu.classList.contains('max-h-0');
      menu.classList.toggle('max-h-0', !closed);
      menu.classList.toggle('opacity-0', !closed);
      menu.classList.toggle('invisible', !closed);
      menu.classList.toggle('max-h-96', closed);
      menu.classList.toggle('opacity-100', closed);
      menu.classList.toggle('visible', closed);
    });
  }

  function applyBranding() {
    const institution = cfg.institution || {};
    const name = institution.name || 'KLAES';
    const initial = name.charAt(0).toUpperCase() || 'K';
    const primary = institution.primary_color || '#3b82f6';
    const secondary = institution.secondary_color || '#7c3aed';
    const balance = institution.token_balance || 0;

    ['dashboard-org-name', 'institution-name', 'mobile-institution-name', 'sidebar-dashboard-org-name'].forEach((id) => { const el = $(id); if (el) el.textContent = name; });
    ['institution-initial', 'mobile-institution-initial'].forEach((id) => { const el = $(id); if (el) el.textContent = initial; });
    ['token-balance', 'token-display-header', 'mobile-token-display', 'sidebar-token-display'].forEach((id) => { const el = $(id); if (el) el.textContent = fmt(balance); });

    const logo = $('dashboard-logo');
    if (logo) {
      if (cfg.assets?.organizationLogo) {
        logo.style.background = '';
        logo.innerHTML = `<img src="${cfg.assets.organizationLogo}" alt="${esc(name)} Logo" style="width:100%;height:100%;object-fit:contain;">`;
      } else {
        logo.style.background = `linear-gradient(135deg, ${primary}, ${secondary})`;
        logo.style.cssText += ';width:40px;height:40px;border-radius:8px;';
        logo.innerHTML = '<i data-lucide="building" class="h-6 w-6 text-white"></i>';
      }
    }

    const banner = $('org-dashboard-banner');
    if (banner) {
      if (cfg.assets?.organizationBanner) {
        banner.style.backgroundImage = `linear-gradient(90deg, rgba(15,23,42,.65), rgba(15,23,42,.2)), url(${cfg.assets.organizationBanner})`;
        banner.style.backgroundSize = 'cover';
        banner.style.backgroundPosition = 'center';
      } else {
        banner.style.background = `linear-gradient(135deg, ${primary}, ${secondary})`;
      }
    }

    const tokenDisplay = document.querySelector('.token-display');
    if (tokenDisplay) tokenDisplay.style.background = `linear-gradient(135deg, ${primary}, ${secondary})`;
    window.lucide?.createIcons();
  }

  function setTokenBalance(balance) {
    ['token-balance', 'token-display-header', 'mobile-token-display', 'sidebar-token-display'].forEach((id) => { const el = $(id); if (el) el.textContent = fmt(balance); });
  }

  function renderSearchResults(data, query) {
    const count = Number(data.total_count || (data.transactions || []).length || 0);
    $('results-count').textContent = `(${count ? 1 : 0} found)`;
    hide($('loading-section'));
    show($('results-section'));
    hide($('file-details-section'));

    if (!count) {
      show($('no-results'));
      $('cards-results').innerHTML = '';
      return;
    }

    hide($('no-results'));
    selectedResult = { ...data, query };
    $('cards-results').innerHTML = `
      <div class="result-card bg-white rounded-xl shadow-lg border border-gray-200 p-5 cursor-pointer" data-open-result="1">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">${fmt(count)} timeline record(s)</p>
            <h3 class="mt-2 text-lg font-bold text-gray-900">${esc(data.file_index_number || query)}</h3>
            <p class="mt-1 text-sm text-gray-600">${esc(data.file_title || 'No title holder available')}</p>
            <div class="mt-3 flex flex-wrap gap-2 text-xs text-gray-500">
              <span class="inline-flex items-center gap-1"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i>${esc([data.file_district, data.file_lga].filter(Boolean).join(' / ') || '-')}</span>
              <span class="inline-flex items-center gap-1"><i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>${esc(data.file_plot_number || '-')}</span>
            </div>
          </div>
          <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">Found</span>
        </div>
      </div>`;
    document.querySelector('[data-open-result="1"]')?.addEventListener('click', () => selectResult(selectedResult));
    window.lucide?.createIcons();
  }

  function selectResult(result) {
    if (!result) return;
    hide($('results-section'));
    show($('file-details-section'));

    const now = new Date();
    const fileNo = result.file_index_number || result.query || '-';
    const rows = result.transactions || [];
    const latestRow = rows.length ? rows[rows.length - 1] : {};
    const latestDate = latestRow.transaction_date || latestRow.reg_date || latestRow.deeds_date || latestRow.sort_date || '-';

    // File Information panel — mirrors the Legal Search "File Information" field set.
    const setText = (id, val) => { const el = $(id); if (el) el.textContent = (val === undefined || val === null || val === '') ? '-' : val; };
    setText('file-reference', fileNo);
    setText('file-number-value', fileNo);
    setText('file-title-value', result.file_title);
    setText('plot-number-value', result.file_plot_number);
    setText('size-value', result.file_size);
    setText('tpno-value', result.file_tp_no);
    setText('district-value', result.file_district);
    setText('lga-value', result.file_lga);
    setText('property-type-value', result.file_land_use);
    setText('last-transaction-value', latestDate);
    setText('status-value', (result.total_count || rows.length) > 0 ? 'Found' : 'Not Found');
    setText('requesting-institution', cfg.institution?.name);
    setText('search-date', now.toLocaleString());
    setText('reference-no', result.reference_no);

    // Source-breakdown badges + total count (mirrors the LS Property Timeline modal).
    const sourceLabels = { file_history_staging: 'File History', CofO_staging: 'CofO', pra: 'PRA', deed_registrations: 'Deed Reg.' };
    const counts = {};
    rows.forEach((r) => { const s = r.source_table || ''; if (s) counts[s] = (counts[s] || 0) + 1; });
    const badgesEl = $('timeline-source-badges');
    if (badgesEl) {
      badgesEl.innerHTML = Object.entries(counts)
        .map(([s, c]) => `<span class="phs-source-tag phs-source-tag-${s}">${esc(sourceLabels[s] || s)}: ${c}</span>`)
        .join('');
    }
    const totalEl = $('timeline-total-count');
    if (totalEl) totalEl.textContent = String(rows.length);
    const uniqueFileNos = new Set(
      rows.map(r => String(r.file_no || r.fileno || r.mlsFNo || '').trim().toUpperCase())
          .filter(fn => fn !== '' && fn !== '-')
    );
    const showFileNo = uniqueFileNos.size > 1;

    $('timeline-container').innerHTML = rows.length ? rows.map((row, index) => {
      const src = row.source_table || '';
      const srcLabel = sourceLabels[src] || src || 'Record';
      const type = row.transaction_type || row.instrument_type || '-';
      const date = row.transaction_date || row.reg_date || row.deeds_date || row.sort_date || '-';
      const party1 = row.party_1 || row.grantor || '-';
      const party2 = row.party_2 || row.grantee || '-';
      const party3raw = row.party_3 || '';
      const party3 = (party3raw && party3raw !== '-') ? party3raw : '';
      const regRaw = row.registration || row.reg_no || row.regNo || row.registration_particulars || '';
      // The LS-weighed slip rows use "0/0/0" as the no-registration placeholder.
      const reg = (regRaw && regRaw !== '-' && regRaw !== '0/0/0') ? regRaw : '';
      const location = row.location || row.property_location || [result.file_district, result.file_lga].filter(Boolean).join(', ') || '';
      return `<div class="timeline-item ${index === rows.length - 1 ? '' : 'completed'}">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 sm:p-4 border border-gray-100 dark:border-gray-700 shadow-sm relative z-10">
          <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <div class="flex items-center gap-2 flex-wrap">
              ${src ? `<span class="phs-source-tag phs-source-tag-${src}">${esc(srcLabel)}</span>` : ''}
              <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">${esc(type)}</span>
              ${reg && reg !== '-' ? `<span class="text-xs text-gray-400">Reg: ${esc(reg)}</span>` : ''}
            </div>
            <span class="text-xs text-gray-500 whitespace-nowrap flex items-center"><i data-lucide="calendar" class="w-3 h-3 mr-1"></i>${esc(date)}</span>
          </div>
          <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
            <div class="flex items-center gap-2">
              <div class="w-6 h-6 sm:w-8 sm:h-8 bg-blue-100 rounded-full flex items-center justify-center"><i data-lucide="user" class="w-3 h-3 sm:w-4 sm:h-4 text-blue-600"></i></div>
              <div>
                <p class="text-[10px] text-gray-400 uppercase tracking-wide">From</p>
                ${showFileNo && row.file_no && row.file_no !== '-' ? `<p class="text-xs font-bold text-gray-500 dark:text-gray-400 mb-0.5">${esc(row.file_no)}</p>` : ''}
                <p class="font-semibold text-gray-800 dark:text-gray-200 text-xs sm:text-sm">${esc(party1)}</p>
              </div>
            </div>
            <i data-lucide="arrow-right" class="w-4 h-4 text-gray-400"></i>
            <div class="flex items-center gap-2">
              <div class="w-6 h-6 sm:w-8 sm:h-8 bg-green-100 rounded-full flex items-center justify-center"><i data-lucide="user-check" class="w-3 h-3 sm:w-4 sm:h-4 text-green-600"></i></div>
              <div><p class="text-[10px] text-gray-400 uppercase tracking-wide">To</p><p class="font-semibold text-gray-800 dark:text-gray-200 text-xs sm:text-sm">${esc(party2)}</p></div>
            </div>
            ${party3 ? `<i data-lucide="arrow-right" class="w-4 h-4 text-gray-400"></i>
            <div class="flex items-center gap-2">
              <div class="w-6 h-6 sm:w-8 sm:h-8 bg-purple-100 rounded-full flex items-center justify-center"><i data-lucide="user" class="w-3 h-3 sm:w-4 sm:h-4 text-purple-600"></i></div>
              <div><p class="text-[10px] text-gray-400 uppercase tracking-wide">Party 3</p><p class="font-semibold text-gray-800 dark:text-gray-200 text-xs sm:text-sm">${esc(party3)}</p></div>
            </div>` : ''}
          </div>
          ${location ? `<p class="text-xs text-gray-400 mt-3 flex items-center"><i data-lucide="map-pin" class="w-3 h-3 mr-1"></i>${esc(location)}</p>` : ''}
        </div>
      </div>`;
    }).join('') : '<div class="text-center py-8 text-gray-500 text-sm sm:text-base">No timeline rows available</div>';

    renderTimelineNotices(result);

    window.scrollTo({ top: $('file-details-section').offsetTop - 80, behavior: 'smooth' });
    window.lucide?.createIcons();
  }

  // Report notices — mirrors the main Legal Search remarks (caveat / W-R-C /
  // CoFO / ground rent / litigation / no-CoFO / encumbrance). Adverse flags
  // (caveat, investigation, W-R-C) render red; positive/neutral notes green.
  function renderTimelineNotices(result) {
    const box = $('timeline-notices');
    if (!box) return;

    const RED = { border: '#fecaca', bg: '#fef2f2', text: '#b91c1c' };
    const GREEN = { border: '#bbf7d0', bg: '#f0fdf4', text: '#166534' };
    const AMBER = { border: '#fde68a', bg: '#fffbeb', text: '#92400e' };

    const notices = [];
    const adverseCaveat = result.is_caveated || result.under_investigation;
    if (result.caveat_note) notices.push({ text: result.caveat_note, c: adverseCaveat ? RED : GREEN });
    if (result.wrc_comment) notices.push({ text: result.wrc_comment, c: RED });
    if (result.cofo_comment) notices.push({ text: result.cofo_comment, c: GREEN });
    if (result.ground_rent) notices.push({ text: result.ground_rent, c: AMBER });
    if (result.litigation_comment) notices.push({ text: result.litigation_comment, c: RED });
    if (result.no_cofo_comment) notices.push({ text: result.no_cofo_comment, c: GREEN });
    if (result.encumbrance_comment) notices.push({ text: result.encumbrance_comment, c: GREEN });

    box.innerHTML = notices.map(n =>
      `<div style="border:1px solid ${n.c.border};background:${n.c.bg};color:${n.c.text};border-radius:8px;padding:10px 12px;font-size:13px;font-weight:600;line-height:1.5;">${esc(n.text)}</div>`
    ).join('');
  }

  async function performSearch() {
    const query = $('search-query').value.trim();
    if (!query) return alert('Please enter a search term.');
    const filters = (typeof window.phsGetActiveFilters === 'function') ? window.phsGetActiveFilters() : {};
    hide($('results-section'));
    hide($('file-details-section'));
    show($('loading-section'));
    try {
      const data = await postJson(cfg.routes.search, { query, filters });
      setTokenBalance(data.token_balance);
      renderSearchResults(data, query);
    } catch (error) {
      hide($('loading-section'));
      alert(error.message || 'Search failed.');
    }
  }

  function packageKey(card) {
    const name = String(card?.dataset.name || '').toLowerCase();
    if (name.includes('professional')) return 'professional';
    if (name.includes('enterprise')) return 'enterprise';
    return 'starter';
  }

  function initTokenPurchase() {
    document.querySelectorAll('.package-card').forEach((card) => {
      card.addEventListener('click', () => {
        document.querySelectorAll('.package-card').forEach((el) => el.classList.remove('selected'));
        card.classList.add('selected');
        selectedPackage = packageKey(card);
      });
    });
    document.querySelector('.package-card')?.click();

    $('buy-tokens-btn')?.addEventListener('click', () => show($('token-modal')));
    $('close-token-modal')?.addEventListener('click', () => hide($('token-modal')));
    $('cancel-token-purchase')?.addEventListener('click', () => hide($('token-modal')));

    $('pay-online-token')?.addEventListener('click', async () => handleTokenAction(cfg.routes.payOnline));
    $('pay-invoice-token')?.addEventListener('click', async () => handleTokenAction(cfg.routes.requestInvoice));
  }

  async function handleTokenAction(url) {
    if (!selectedPackage) return alert('Please select a package first.');
    try {
      const data = await postJson(url, { package: selectedPackage });
      if (data.token_balance !== undefined) setTokenBalance(data.token_balance);
      hide($('token-modal'));
      alert(data.message || 'Token request submitted.');
    } catch (error) {
      alert(error.message || 'Token action failed.');
    }
  }

  function printSearchSlip() {
    if (!selectedResult) return alert('Please select a search result first.');
    const params = new URLSearchParams({ file_number: selectedResult.file_index_number || selectedResult.query || '', reference_no: selectedResult.reference_no || '' });
    window.open(`${cfg.routes.print}?${params.toString()}`, '_blank');
  }

  function logout() {
    fetch(cfg.routes.logout, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'text/html' } })
      .finally(() => { window.location.href = '/phs'; });
  }

  document.addEventListener('DOMContentLoaded', () => {
    applyBranding();
    initMobileMenu();
    initTokenPurchase();
    $('search-btn')?.addEventListener('click', performSearch);
    $('search-query')?.addEventListener('keydown', (e) => { if (e.key === 'Enter') performSearch(); });
    // Toggle between the Dashboard overview and the Search History view.
    function setView(view) {
      const isSearch = view === 'search';
      $('dashboard-view')?.classList.toggle('hidden', isSearch);
      $('search-view')?.classList.toggle('hidden', !isSearch);
      $('org-dashboard-banner')?.classList.toggle('hidden', isSearch);
      document.querySelectorAll('.phs-nav-link').forEach((link) => {
        const active = link.dataset.view === view;
        link.classList.toggle('bg-blue-50', active);
        link.classList.toggle('text-blue-700', active);
        link.classList.toggle('font-semibold', active);
        link.classList.toggle('text-gray-600', !active);
        link.classList.toggle('font-medium', !active);
      });
      if (isSearch) {
        if (typeof window.jQuery !== 'undefined' && window.jQuery('#search-query').data('select2')) {
          window.jQuery('#search-query').select2('open');
        } else {
          $('search-query')?.focus();
        }
        $('search-query')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    }

    $('sidebar-dashboard-link')?.addEventListener('click', (e) => { e.preventDefault(); setView('dashboard'); });
    $('sidebar-search-now-link')?.addEventListener('click', (e) => { e.preventDefault(); setView('search'); });
    $('sidebar-search-history-link')?.addEventListener('click', (e) => {
      e.preventDefault();
      // Search history lives in the "Recent Searches" panel on the dashboard view.
      setView('dashboard');
      $('recent-searches-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    $('dashboard-new-search-btn')?.addEventListener('click', () => setView('search'));
    // Deep-link: open the Search view when arriving with #search (e.g. from the
    // "Search Now" link on the Organization page).
    if (window.location.hash === '#search') setView('search');
    $('sidebar-buy-tokens-btn')?.addEventListener('click', () => show($('token-modal')));
    $('try-new-search')?.addEventListener('click', () => {
      const q = $('search-query');
      if (q) {
        q.value = '';
        if (typeof window.jQuery !== 'undefined' && window.jQuery(q).data('select2')) {
          window.jQuery(q).val(null).trigger('change');
        }
      }
      hide($('results-section'));
      if (typeof window.jQuery !== 'undefined' && window.jQuery('#search-query').data('select2')) {
        window.jQuery('#search-query').select2('open');
      } else {
        q?.focus();
      }
    });
    $('back-to-dashboard-btn')?.addEventListener('click', () => { hide($('file-details-section')); show($('results-section')); });
    $('print-slip-btn')?.addEventListener('click', printSearchSlip);
    $('dashboard-logout-btn')?.addEventListener('click', logout);
    $('mobile-dashboard-logout-btn')?.addEventListener('click', logout);
    $('sidebar-dashboard-logout-btn')?.addEventListener('click', logout);
    window.lucide?.createIcons();
  });
})();
