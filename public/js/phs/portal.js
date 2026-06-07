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
      logo.style.background = `linear-gradient(135deg, ${primary}, ${secondary})`;
      if (cfg.assets?.organizationLogo) {
        logo.innerHTML = `<img src="${cfg.assets.organizationLogo}" alt="${esc(name)} Logo" class="w-full h-full object-cover">`;
      } else {
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
    const latestParty1 = latestRow.party_1 || latestRow.grantor || result.party_1 || result.grantor || '-';
    const latestParty2 = latestRow.party_2 || latestRow.grantee || result.party_2 || result.grantee || '-';
    $('file-reference').textContent = fileNo;
    $('file-number-value').textContent = fileNo;
    $('kangis-file-number-value').textContent = result.file_tp_no || '-';
    $('current-guarantor-value').textContent = latestParty1;
    $('current-guarantee-value').textContent = latestParty2;
    $('lga-value').textContent = [result.file_district, result.file_lga].filter(Boolean).join(' / ') || '-';
    $('plot-number-value').textContent = result.file_plot_number || '-';
    $('property-type-value').textContent = result.file_land_use || '-';
    $('status-value').textContent = (result.total_count || 0) > 0 ? 'Found' : 'Not Found';
    $('requesting-institution').textContent = cfg.institution?.name || '-';
    $('search-date').textContent = now.toLocaleString();
    $('reference-no').textContent = result.reference_no || '-';

    $('timeline-container').innerHTML = rows.length ? rows.map((row, index) => {
      const type = row.transaction_type || row.instrument_type || '-';
      const date = row.transaction_date || row.reg_date || row.deeds_date || row.sort_date || '-';
      const grantor = row.party_1 || row.grantor || '-';
      const grantee = row.party_2 || row.grantee || '-';
      const reg = row.registration || row.reg_no || row.regNo || '';
      const comments = row.comments && row.comments !== '-' ? row.comments : '';
      const descParts = [];
      if (reg && reg !== '-') descParts.push(`Registration Particulars: ${esc(reg)}`);
      if (comments) descParts.push(esc(comments));
      const description = descParts.join(' &middot; ');
      return `<div class="timeline-item ${index === rows.length - 1 ? '' : 'completed'}">
        <div class="flex items-start gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 sm:gap-3 mb-2 flex-wrap">
              <span class="text-xs sm:text-sm font-semibold text-blue-600 bg-blue-50 px-2 sm:px-3 py-1 rounded-full">${esc(type)}</span>
              <span class="text-xs text-gray-400 flex items-center"><i data-lucide="calendar" class="w-3 h-3 mr-1"></i>${esc(date)}</span>
            </div>
            <div class="bg-gradient-to-r from-gray-50 to-white rounded-xl p-3 sm:p-4 border border-gray-100">
              <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2">
                    <div class="w-6 h-6 sm:w-8 sm:h-8 bg-blue-100 rounded-full flex items-center justify-center"><i data-lucide="user" class="w-3 h-3 sm:w-4 sm:h-4 text-blue-600"></i></div>
                    <div><p class="text-xs text-gray-400">From</p><p class="font-semibold text-gray-800 text-xs sm:text-sm">${esc(grantor)}</p></div>
                  </div>
                </div>
                <div class="hidden md:block"><i data-lucide="arrow-right" class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400"></i></div>
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2">
                    <div class="w-6 h-6 sm:w-8 sm:h-8 bg-green-100 rounded-full flex items-center justify-center"><i data-lucide="user-check" class="w-3 h-3 sm:w-4 sm:h-4 text-green-600"></i></div>
                    <div><p class="text-xs text-gray-400">To</p><p class="font-semibold text-gray-800 text-xs sm:text-sm">${esc(grantee)}</p></div>
                  </div>
                </div>
              </div>
              ${description ? `<div class="mt-2 sm:mt-3 pt-2 sm:pt-3 border-t border-gray-100"><p class="text-xs text-gray-500"><i data-lucide="file-text" class="w-3 h-3 inline mr-1"></i>${description}</p></div>` : ''}
            </div>
          </div>
        </div>
      </div>`;
    }).join('') : '<div class="text-center py-8 text-gray-500 text-sm sm:text-base">No timeline rows available</div>';

    window.scrollTo({ top: $('file-details-section').offsetTop - 80, behavior: 'smooth' });
    window.lucide?.createIcons();
  }

  async function performSearch() {
    const query = $('search-query').value.trim();
    if (!query) return alert('Please enter a search term.');
    hide($('results-section'));
    hide($('file-details-section'));
    show($('loading-section'));
    try {
      const data = await postJson(cfg.routes.search, { query });
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
    $('sidebar-search-link')?.addEventListener('click', (e) => {
      e.preventDefault();
      $('search-query')?.focus();
      $('search-query')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    $('sidebar-buy-tokens-btn')?.addEventListener('click', () => show($('token-modal')));
    $('try-new-search')?.addEventListener('click', () => { $('search-query').value = ''; hide($('results-section')); $('search-query').focus(); });
    $('back-to-dashboard-btn')?.addEventListener('click', () => { hide($('file-details-section')); show($('results-section')); });
    $('print-slip-btn')?.addEventListener('click', printSearchSlip);
    $('dashboard-logout-btn')?.addEventListener('click', logout);
    $('mobile-dashboard-logout-btn')?.addEventListener('click', logout);
    $('sidebar-dashboard-logout-btn')?.addEventListener('click', logout);
    window.lucide?.createIcons();
  });
})();
