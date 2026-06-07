(function () {
  const cfg = window.PHS_ORG || {};
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const org = cfg.institution || {};
  let members = Array.isArray(cfg.members) ? cfg.members : [];
  let logoFile = null;
  let bannerFile = null;

  const $ = (id) => document.getElementById(id);
  const fmt = (n) => new Intl.NumberFormat().format(Number(n || 0));
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));

  function postJson(url, method, payload) {
    return fetch(url, {
      method,
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
      body: payload ? JSON.stringify(payload) : null,
    }).then(async (response) => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw data;
      return data;
    });
  }

  function roleLabel(value) {
    return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (m) => m.toUpperCase());
  }

  function currentSettings() {
    return {
      name: $('org-name')?.value || org.name || 'Your Organization',
      primaryColor: $('primary-color')?.value || org.primary_color || '#3b82f6',
      secondaryColor: $('secondary-color')?.value || org.secondary_color || '#7c3aed',
      logoUrl: cfg.assets?.logo || '',
      bannerUrl: cfg.assets?.banner || '',
    };
  }

  function applyHeader() {
    const settings = currentSettings();
    $('org-header-name').textContent = `Manage ${settings.name}`;
    $('mobile-org-header-name').textContent = `Manage ${settings.name}`;
    $('org-tokens-display').textContent = fmt(org.token_balance || 0);
    const sidebarName = $('sidebar-org-name');
    if (sidebarName) sidebarName.textContent = settings.name;
    const sidebarTokens = $('sidebar-org-tokens-display');
    if (sidebarTokens) sidebarTokens.textContent = fmt(org.token_balance || 0);
    const switcher = $('org-switcher');
    if (switcher) {
      switcher.innerHTML = `<option value="current">${esc(settings.name)}</option>`;
    }
    const logo = $('org-header-logo');
    if (logo) {
      logo.style.background = `linear-gradient(135deg, ${settings.primaryColor}, ${settings.secondaryColor})`;
      logo.innerHTML = settings.logoUrl
        ? `<img src="${settings.logoUrl}" class="w-full h-full object-contain" alt="Logo">`
        : '<i data-lucide="users" class="h-5 w-5 sm:h-6 sm:w-6 text-white"></i>';
    }
  }

  function updateStats() {
    $('total-users').textContent = members.length;
    $('active-users').textContent = members.filter((m) => m.status === 'active').length;
    $('super-admin-count').textContent = members.filter((m) => m.user_type === 'super_admin').length;
    $('regular-user-count').textContent = members.filter((m) => m.user_type === 'regular_user').length;
  }

  function renderUsersTable() {
    const tbody = $('users-table-body');
    if (!tbody) return;
    tbody.innerHTML = members.map((user) => {
      const used = Number(user.tokens_used || 0);
      const pct = Math.min((used / 500) * 100, 100);
      const isSelf = Number(user.id) === Number(cfg.member?.id);
      return `<tr class="table-row">
        <td class="py-3 sm:py-4 px-4 sm:px-6">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">${esc((user.name || '?').charAt(0).toUpperCase())}</div>
            <div><p class="font-medium text-sm sm:text-base">${esc(user.name)}</p><p class="text-xs text-gray-500 md:hidden">${esc(user.email)}</p></div>
          </div>
        </td>
        <td class="py-3 sm:py-4 px-4 sm:px-6 text-sm text-gray-600">${esc(user.email)}</td>
        <td class="py-3 sm:py-4 px-4 sm:px-6 text-sm text-gray-600 hidden md:table-cell">${esc(user.job_title || '-')}</td>
        <td class="py-3 sm:py-4 px-4 sm:px-6"><span class="px-2.5 py-1 rounded-full text-xs font-medium ${user.user_type === 'super_admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}">${esc(roleLabel(user.user_type))}</span></td>
        <td class="py-3 sm:py-4 px-4 sm:px-6">
          <div class="flex items-center gap-2 min-w-[110px]"><div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden"><div class="h-full bg-blue-600 rounded-full token-usage-bar" style="width:${pct}%"></div></div><span class="text-xs sm:text-sm font-medium">${fmt(used)}</span></div>
        </td>
        <td class="py-3 sm:py-4 px-4 sm:px-6"><span class="px-2.5 py-1 rounded-full text-xs font-medium ${user.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'}">${esc(roleLabel(user.status))}</span></td>
        <td class="py-3 sm:py-4 px-4 sm:px-6">
          ${isSelf ? '<span class="text-xs text-gray-400">Current user</span>' : `<button class="text-red-600 hover:text-red-700 text-sm font-medium" data-delete-user="${user.id}"><i data-lucide="trash-2" class="w-4 h-4 inline mr-1"></i>Remove</button>`}
        </td>
      </tr>`;
    }).join('');

    document.querySelectorAll('[data-delete-user]').forEach((button) => {
      button.addEventListener('click', () => deleteUser(button.dataset.deleteUser));
    });
    window.lucide?.createIcons();
  }

  async function deleteUser(id) {
    if (!confirm('Remove this PHS member?')) return;
    try {
      await postJson(`${cfg.routes.membersBase}/${id}`, 'DELETE');
      members = members.filter((m) => Number(m.id) !== Number(id));
      renderUsersTable();
      updateStats();
    } catch (error) {
      alert(error.message || 'Unable to remove member.');
    }
  }

  function renderActivityLog(rows) {
    const container = $('activity-log-container');
    if (!container) return;
    if (!rows.length) {
      container.innerHTML = '<p class="text-sm text-gray-500">No activity recorded yet.</p>';
      return;
    }
    container.innerHTML = rows.map((item) => `<div class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-5">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div class="flex items-start gap-3"><div class="w-9 h-9 rounded-2xl bg-blue-50 flex items-center justify-center"><i data-lucide="activity" class="w-4 h-4 text-blue-600"></i></div><div><p class="font-medium text-gray-900 text-sm sm:text-base">${esc(item.description || '-')}</p><p class="text-xs text-gray-500">${esc(item.member || 'System')}${item.reference ? ' - ' + esc(item.reference) : ''}</p></div></div>
        <span class="text-xs text-gray-500">${esc(item.at || '')}</span>
      </div>
    </div>`).join('');
    window.lucide?.createIcons();
  }

  async function loadActivityLog() {
    const container = $('activity-log-container');
    if (!container || container.dataset.loaded === '1') return;
    try {
      const response = await fetch(cfg.routes.activity, { headers: { Accept: 'application/json' } });
      const data = await response.json();
      container.dataset.loaded = '1';
      renderActivityLog(data.data || []);
    } catch (e) {
      container.innerHTML = '<p class="text-sm text-red-600">Unable to load activity.</p>';
    }
  }

  function loadBrandingValues() {
    $('org-name').value = org.name || '';
    $('primary-color').value = org.primary_color || '#3b82f6';
    $('primary-color-hex').value = org.primary_color || '#3b82f6';
    $('secondary-color').value = org.secondary_color || '#7c3aed';
    $('secondary-color-hex').value = org.secondary_color || '#7c3aed';
    updateLogoPreview(cfg.assets?.logo || '');
    updateBannerPreview(cfg.assets?.banner || '');
    updatePreview();
  }

  function updateLogoPreview(url) {
    const area = $('logo-preview-area');
    const preview = $('logo-preview');
    const remove = $('remove-logo-btn');
    if (url) {
      area.innerHTML = `<img src="${url}" class="w-full h-full object-contain" alt="Logo">`;
      preview.innerHTML = `<img src="${url}" class="w-full h-full object-contain" alt="Logo">`;
      remove?.classList.remove('hidden');
    } else {
      area.innerHTML = '<i data-lucide="image" class="w-8 h-8 sm:w-12 sm:h-12 text-gray-300"></i>';
      preview.innerHTML = '<i data-lucide="building" class="h-5 w-5 sm:h-6 sm:w-6 text-white"></i>';
      remove?.classList.add('hidden');
    }
    window.lucide?.createIcons();
  }

  function updateBannerPreview(url) {
    const area = $('banner-preview-area');
    const banner = $('org-banner');
    if (url) {
      area.innerHTML = `<img src="${url}" class="w-full h-full object-cover rounded-2xl" alt="Banner">`;
      banner.style.backgroundImage = `linear-gradient(90deg, rgba(15,23,42,.6), rgba(15,23,42,.15)), url(${url})`;
      banner.style.backgroundSize = 'cover';
      banner.style.backgroundPosition = 'center';
      $('banner-text').style.display = 'none';
    } else {
      area.innerHTML = '<i data-lucide="image" class="w-8 h-8 sm:w-12 sm:h-12 text-gray-300"></i>';
      $('banner-text').style.display = 'block';
    }
    window.lucide?.createIcons();
  }

  function updatePreview() {
    const settings = currentSettings();
    $('org-name-preview').textContent = settings.name;
    $('button-preview-primary').style.backgroundColor = settings.primaryColor;
    $('button-preview-secondary').style.borderColor = settings.primaryColor;
    $('button-preview-secondary').style.color = settings.primaryColor;
    $('logo-preview').style.background = `linear-gradient(135deg, ${settings.primaryColor}, ${settings.secondaryColor})`;
    $('org-header-logo').style.background = `linear-gradient(135deg, ${settings.primaryColor}, ${settings.secondaryColor})`;
    if (!cfg.assets?.banner && !bannerFile) $('org-banner').style.background = `linear-gradient(135deg, ${settings.primaryColor}, ${settings.secondaryColor})`;
  }

  function setupTabs() {
    const setSidebarActive = (activeName) => {
      document.querySelectorAll('[data-sidebar-tab]').forEach((btn) => {
        const active = btn.dataset.sidebarTab === activeName;
        btn.classList.toggle('bg-blue-50', active);
        btn.classList.toggle('text-blue-700', active);
        btn.classList.toggle('font-semibold', active);
        btn.classList.toggle('text-gray-600', !active);
        btn.classList.toggle('font-medium', !active);
      });
    };

    document.querySelectorAll('[data-tab]').forEach((tab) => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('[data-tab]').forEach((btn) => btn.classList.remove('tab-active'));
        document.querySelectorAll('[data-tab]').forEach((btn) => btn.classList.add('text-gray-500'));
        tab.classList.add('tab-active');
        tab.classList.remove('text-gray-500');
        ['users', 'roles', 'activity', 'branding'].forEach((name) => $(name + '-tab').classList.add('hidden'));
        $(tab.dataset.tab + '-tab').classList.remove('hidden');
        setSidebarActive(tab.dataset.tab);
        if (tab.dataset.tab === 'activity') loadActivityLog();
      });
    });

    document.querySelectorAll('[data-sidebar-tab]').forEach((button) => {
      button.addEventListener('click', () => {
        document.querySelector(`[data-tab="${button.dataset.sidebarTab}"]`)?.click();
      });
    });
  }

  function setupColorInputs() {
    [['primary-color', 'primary-color-hex'], ['secondary-color', 'secondary-color-hex']].forEach(([colorId, hexId]) => {
      const color = $(colorId);
      const hex = $(hexId);
      color?.addEventListener('input', () => { hex.value = color.value; updatePreview(); });
      hex?.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) color.value = hex.value; updatePreview(); });
    });
    $('org-name')?.addEventListener('input', updatePreview);
  }

  function setupUploads() {
    $('upload-logo-btn')?.addEventListener('click', () => $('logo-upload').click());
    $('logo-dropzone')?.addEventListener('click', (event) => { if (event.target.id !== 'remove-logo-btn') $('logo-upload').click(); });
    $('logo-upload')?.addEventListener('change', (event) => {
      logoFile = event.target.files[0] || null;
      if (!logoFile) return;
      const reader = new FileReader();
      reader.onload = (e) => updateLogoPreview(e.target.result);
      reader.readAsDataURL(logoFile);
    });
    $('remove-logo-btn')?.addEventListener('click', (event) => { event.stopPropagation(); logoFile = null; cfg.assets.logo = ''; updateLogoPreview(''); });

    $('upload-banner-btn')?.addEventListener('click', () => $('banner-upload').click());
    $('banner-dropzone')?.addEventListener('click', () => $('banner-upload').click());
    $('banner-upload')?.addEventListener('change', (event) => {
      bannerFile = event.target.files[0] || null;
      if (!bannerFile) return;
      const reader = new FileReader();
      reader.onload = (e) => updateBannerPreview(e.target.result);
      reader.readAsDataURL(bannerFile);
    });
  }

  async function saveSettings() {
    const form = new FormData();
    form.append('name', $('org-name').value);
    form.append('primary_color', $('primary-color').value);
    form.append('secondary_color', $('secondary-color').value);
    if (logoFile) form.append('logo', logoFile);
    if (bannerFile) form.append('banner', bannerFile);
    try {
      const response = await fetch(cfg.routes.branding, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: form });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw data;
      alert(data.message || 'Branding updated.');
      org.name = $('org-name').value;
      org.primary_color = $('primary-color').value;
      org.secondary_color = $('secondary-color').value;
      applyHeader();
      updatePreview();
    } catch (error) {
      alert(error.message || Object.values(error.errors || {})[0]?.[0] || 'Unable to save branding.');
    }
  }

  async function addNewUser() {
    const password = $('temp-password').value;
    if (password !== $('confirm-password').value) return alert('Passwords do not match.');
    const payload = {
      name: $('user-fullname').value.trim(),
      email: $('user-email').value.trim(),
      password,
      job_title: $('job-title').value.trim(),
      department: $('department').value.trim(),
      user_type: document.querySelector('input[name="user-type"]:checked')?.value || 'regular_user',
      access_role: document.querySelector('input[name="access-role"]:checked')?.value || 'search_only',
    };
    try {
      const data = await postJson(cfg.routes.membersStore, 'POST', payload);
      members.push(data.data);
      renderUsersTable();
      updateStats();
      closeModal();
      alert(data.message || 'Member added.');
    } catch (error) {
      alert(error.message || Object.values(error.errors || {})[0]?.[0] || 'Unable to add member.');
    }
  }

  function closeModal() {
    $('add-user-modal').classList.add('hidden');
    ['user-fullname', 'user-email', 'job-title', 'department', 'temp-password', 'confirm-password'].forEach((id) => { if ($(id)) $(id).value = ''; });
    $('token-allocation').value = '250';
  }

  function setupModal() {
    $('add-user-btn')?.addEventListener('click', () => $('add-user-modal').classList.remove('hidden'));
    $('close-modal-btn')?.addEventListener('click', closeModal);
    $('cancel-add-user')?.addEventListener('click', closeModal);
    $('confirm-add-user')?.addEventListener('click', addNewUser);
  }

  function setupMisc() {
    $('close-user-management')?.addEventListener('click', () => { window.location.href = cfg.routes.dashboard; });
    $('sidebar-org-logout-btn')?.addEventListener('click', () => {
      fetch(cfg.routes.logout, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'text/html' } })
        .finally(() => { window.location.href = '/phs'; });
    });
    $('save-settings')?.addEventListener('click', saveSettings);
    $('reset-settings')?.addEventListener('click', () => { loadBrandingValues(); });
  }

  document.addEventListener('DOMContentLoaded', () => {
    applyHeader();
    updateStats();
    renderUsersTable();
    renderActivityLog([]);
    loadBrandingValues();
    setupTabs();
    setupColorInputs();
    setupUploads();
    setupModal();
    setupMisc();
    window.lucide?.createIcons();
  });
})();
