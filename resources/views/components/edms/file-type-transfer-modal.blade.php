{{--
  EDMS File Type modal — shared by Scan Upload, the Page Typing workspace and
  the File Archive (Doc-WARE) grid.

  Open it with:  EdmsFileType.open(fileIndexingId, fileNumber, onDone)

  Everything it needs comes from /edms/file-type/{options,search,cover,preview,move},
  so all three interfaces behave identically.

  The cover preview is the point of the dialog: the instruction that decides a
  file's type ("subdivision — mother", "extension", "change of purpose") is
  written on the file's cover, so the operator reads it here rather than guessing
  from the file number.
--}}
<div id="file-type-transfer-modal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col relative">

    <div class="flex items-start justify-between gap-4 p-5 border-b border-gray-200">
      <div>
        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
          <i data-lucide="folder-tree" class="h-5 w-5 text-indigo-600"></i>
          File into a Master Folder
        </h3>
        <p class="text-sm text-gray-500 mt-1">
          Moves the scans, typed pages and Doc-WARE archive copies of
          <span class="font-semibold text-gray-700" id="file-type-transfer-file-number">—</span>
          into its master folder, inside the same registry.
        </p>
      </div>
      <button type="button" class="btn btn-ghost btn-icon" onclick="EdmsFileType.close()" title="Close">
        <i data-lucide="x" class="h-5 w-5"></i>
      </button>
    </div>

    <div class="p-5 overflow-y-auto flex-1">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- LEFT: the cover, so the operator can read the instruction on it --}}
        <div class="flex flex-col">
          <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-semibold text-gray-900">Cover page</label>
            <span class="text-xs text-gray-500" id="file-type-cover-caption"></span>
          </div>
          <div id="file-type-cover-box"
               class="flex-1 min-h-[320px] rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden">
            <p class="text-sm text-gray-500 px-4 text-center">Select a file to see its cover.</p>
          </div>
          <p class="text-xs text-gray-500 mt-2">
            The classification instruction is usually written on the cover. Click the image to open it full size.
          </p>
        </div>

        {{-- RIGHT: pick the file, then pick the folder --}}
        <div class="space-y-4">

          {{-- File picker: prefilled when opened from a file, but always changeable --}}
          <div>
            <label for="file-type-transfer-search" class="block text-sm font-semibold text-gray-900 mb-2">File number</label>
            <div class="relative">
              <i data-lucide="search" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
              <input type="text" id="file-type-transfer-search" autocomplete="off"
                     class="input input-bordered w-full pl-9"
                     placeholder="Search a file number or title…">
              <button type="button" id="file-type-transfer-clear-file"
                      class="hidden absolute right-2 top-1/2 -translate-y-1/2 btn btn-ghost btn-icon"
                      title="Pick a different file" onclick="EdmsFileType.clearFile()">
                <i data-lucide="x" class="h-4 w-4"></i>
              </button>
            </div>

            <label class="flex items-center gap-2 mt-2 text-xs text-gray-600 cursor-pointer">
              <input type="checkbox" id="file-type-unclassified-only" class="rounded border-gray-300">
              Only show files not yet filed into a master folder
            </label>

            {{-- Results --}}
            <div id="file-type-transfer-results"
                 class="hidden mt-1 border border-gray-200 rounded-lg max-h-56 overflow-y-auto divide-y divide-gray-100 shadow-sm"></div>

            {{-- The chosen file --}}
            <div id="file-type-transfer-selected"
                 class="hidden mt-2 px-3 py-2 bg-indigo-50 border border-indigo-200 rounded-lg text-sm">
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <div class="font-semibold text-indigo-900" id="file-type-transfer-selected-number">—</div>
                  <div class="text-xs text-indigo-700 truncate" id="file-type-transfer-selected-title"></div>
                </div>
                <div class="text-xs text-indigo-700 whitespace-nowrap" id="file-type-transfer-selected-counts"></div>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-2">Registry</label>
              <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700"
                   id="file-type-transfer-current">—</div>
            </div>
            <div>
              <label for="file-type-transfer-target" class="block text-sm font-semibold text-gray-900 mb-2">File type</label>
              <select id="file-type-transfer-target" class="input input-bordered w-full">
                <option value="">Select a file first…</option>
              </select>
              {{-- The folder it is in now. The select lists where it can go and
                   drops the current one, so without this the operator loses sight
                   of what the file is already classified as. --}}
              <p class="text-xs text-gray-500 mt-1">
                Currently: <span class="font-medium text-gray-700" id="file-type-transfer-current-type">—</span>
              </p>
            </div>
          </div>

          <div>
            <label for="file-type-transfer-reason" class="block text-sm font-semibold text-gray-900 mb-2">
              Reason <span class="font-normal text-gray-500">(stored in the audit log)</span>
            </label>
            <input type="text" id="file-type-transfer-reason" maxlength="500"
                   class="input input-bordered w-full"
                   placeholder="e.g. Cover marked 'Subdivision — Mother'">
          </div>

          {{-- What will move --}}
          <div id="file-type-transfer-preview" class="hidden border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wide">
              What will move
            </div>
            <div class="p-4 space-y-3">
              <div class="flex flex-wrap gap-4 text-sm">
                <span><strong id="file-type-transfer-count-scans">0</strong> scan(s)</span>
                <span><strong id="file-type-transfer-count-pages">0</strong> typed page(s)</span>
                <span id="file-type-transfer-count-missing-wrap" class="hidden text-amber-700">
                  <strong id="file-type-transfer-count-missing">0</strong> not found on disk
                </span>
              </div>
              <div class="text-xs font-mono bg-gray-50 rounded p-2 overflow-x-auto whitespace-nowrap">
                <span id="file-type-transfer-from-slug" class="text-gray-500"></span>
                <span class="mx-2">&rarr;</span>
                <span id="file-type-transfer-to-slug" class="text-indigo-700 font-semibold"></span>
              </div>
              <div class="max-h-40 overflow-y-auto overflow-x-auto border border-gray-100 rounded">
                <table class="w-full text-xs">
                  <thead class="bg-gray-50 sticky top-0">
                    <tr class="text-left text-gray-500">
                      <th class="px-3 py-2 font-medium">Record</th>
                      <th class="px-3 py-2 font-medium">On disk</th>
                      <th class="px-3 py-2 font-medium">Destination</th>
                    </tr>
                  </thead>
                  <tbody id="file-type-transfer-rows" class="divide-y divide-gray-100"></tbody>
                </table>
              </div>
            </div>
          </div>

          {{-- Blocked --}}
          <div id="file-type-transfer-blockers" class="hidden p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>

          <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex gap-2">
            <i data-lucide="alert-triangle" class="h-4 w-4 flex-shrink-0 mt-0.5"></i>
            <span>
              The master folder is part of every document's folder path. This moves the files on disk and
              re-points the indexing record, its scans and its typed pages together, in one transaction.
              The registry is not changed.
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="flex items-center justify-end gap-2 p-5 border-t border-gray-200 bg-gray-50 rounded-b-xl">
      <button type="button" class="btn btn-ghost" onclick="EdmsFileType.close()">Cancel</button>
      <button type="button" id="file-type-transfer-confirm" class="btn btn-primary" disabled
              onclick="EdmsFileType.confirm()">
        <i data-lucide="folder-tree" class="h-4 w-4 mr-1"></i>
        File Away
      </button>
    </div>

    <div id="file-type-transfer-loading" class="hidden absolute inset-0 bg-white/70 items-center justify-center rounded-xl">
      <div class="flex flex-col items-center gap-2">
        <span class="loading loading-spinner loading-lg text-indigo-600"></span>
        <span class="text-sm text-gray-600">Moving documents…</span>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

  const el = (id) => document.getElementById(id);

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));

  const EdmsFileType = {
    fileIndexingId: null,
    fileNumber: null,
    onDone: null,
    preview: null,
    coverToken: 0,

    /**
     * Open the dialog. Both arguments are optional — called with no file, it
     * opens as a standalone tool and the operator searches for one.
     */
    async open(fileIndexingId, fileNumber, onDone) {
      this.onDone = typeof onDone === 'function' ? onDone : null;
      this.preview = null;

      el('file-type-transfer-reason').value = '';
      el('file-type-transfer-preview').classList.add('hidden');
      el('file-type-transfer-blockers').classList.add('hidden');
      el('file-type-transfer-results').classList.add('hidden');
      el('file-type-transfer-confirm').disabled = true;

      const modal = el('file-type-transfer-modal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');

      this.bindSearch();

      if (fileIndexingId) {
        await this.selectFile({ id: fileIndexingId, file_number: fileNumber || '' });
      } else {
        this.clearFile();
        setTimeout(() => el('file-type-transfer-search').focus(), 50);
      }

      if (window.lucide) lucide.createIcons();
    },

    bindSearch() {
      const input = el('file-type-transfer-search');
      if (input.dataset.bound) return;
      input.dataset.bound = '1';

      let timer = null;
      input.addEventListener('input', () => {
        clearTimeout(timer);
        const term = input.value.trim();
        timer = setTimeout(() => this.runSearch(term), 250);
      });

      el('file-type-unclassified-only').addEventListener('change', () => {
        this.runSearch(input.value.trim());
      });

      // Clicking away closes the result list
      document.addEventListener('click', (e) => {
        if (!el('file-type-transfer-modal').classList.contains('flex')) return;
        if (e.target.closest('#file-type-transfer-results') || e.target.closest('#file-type-transfer-search')) return;
        el('file-type-transfer-results').classList.add('hidden');
      });
    },

    async runSearch(term) {
      const box = el('file-type-transfer-results');
      const unclassifiedOnly = el('file-type-unclassified-only').checked;

      // With the backlog filter on, an empty box is a useful query: it lists the
      // files still waiting to be classified.
      if (term.length < 2 && !unclassifiedOnly) {
        box.classList.add('hidden');
        return;
      }

      box.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">Searching…</div>';
      box.classList.remove('hidden');

      try {
        const params = new URLSearchParams({ search: term });
        if (unclassifiedOnly) params.set('unclassified_only', '1');

        const res = await fetch(`/edms/file-type/search?${params.toString()}`, {
          headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        const files = json.data || [];

        if (!files.length) {
          box.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">No files match that search.</div>';
          return;
        }

        // The file number is the identity, so it leads. A record without one used
        // to render an empty first line, leaving its title sitting where the
        // number belongs and reading as though it were the number.
        box.innerHTML = files.map((f) => `
          <button type="button" class="w-full text-left px-3 py-2 hover:bg-indigo-50 transition-colors"
                  data-file='${escapeHtml(JSON.stringify(f))}'>
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="text-sm font-semibold text-gray-900">${
                  (f.file_number || '').trim()
                    ? escapeHtml(f.file_number)
                    : '<span class="font-normal italic text-gray-400">No file number</span>'
                }</div>
                <div class="text-xs text-gray-500 truncate">${escapeHtml(f.file_title || '—')}</div>
              </div>
              <div class="text-xs text-gray-500 whitespace-nowrap text-right">
                <div>${escapeHtml(f.registry)} &middot; ${escapeHtml(f.file_type_label)}</div>
                <div>${f.scannings_count} scan(s), ${f.pagetypings_count} page(s)</div>
              </div>
            </div>
          </button>`).join('');

        box.querySelectorAll('[data-file]').forEach((btn) => {
          btn.addEventListener('click', () => {
            box.classList.add('hidden');
            this.selectFile(JSON.parse(btn.dataset.file));
          });
        });
      } catch (err) {
        box.innerHTML = `<div class="px-3 py-2 text-sm text-red-600">Search failed: ${escapeHtml(err.message)}</div>`;
      }
    },

    async selectFile(file) {
      this.fileIndexingId = file.id;
      this.fileNumber = file.file_number || '';

      el('file-type-transfer-file-number').textContent = this.fileNumber || `#${file.id}`;
      el('file-type-transfer-selected-number').textContent = this.fileNumber || `#${file.id}`;
      el('file-type-transfer-selected-title').textContent = file.file_title || '';
      el('file-type-transfer-selected-counts').textContent =
        (file.scannings_count !== undefined)
          ? `${file.scannings_count} scan(s), ${file.pagetypings_count} typed page(s)`
          : '';

      el('file-type-transfer-selected').classList.remove('hidden');
      el('file-type-transfer-clear-file').classList.remove('hidden');
      el('file-type-transfer-search').value = this.fileNumber;

      el('file-type-transfer-preview').classList.add('hidden');
      el('file-type-transfer-blockers').classList.add('hidden');
      el('file-type-transfer-confirm').disabled = true;

      await Promise.all([this.loadOptions(), this.loadCover()]);
      if (window.lucide) lucide.createIcons();
    },

    clearFile() {
      this.fileIndexingId = null;
      this.fileNumber = null;
      this.preview = null;
      this.coverToken++;

      el('file-type-transfer-search').value = '';
      el('file-type-transfer-file-number').textContent = '—';
      el('file-type-transfer-current').textContent = '—';
      el('file-type-transfer-current-type').textContent = '—';
      el('file-type-transfer-selected').classList.add('hidden');
      el('file-type-transfer-clear-file').classList.add('hidden');
      el('file-type-transfer-results').classList.add('hidden');
      el('file-type-transfer-preview').classList.add('hidden');
      el('file-type-transfer-blockers').classList.add('hidden');
      el('file-type-transfer-confirm').disabled = true;
      el('file-type-transfer-target').innerHTML = '<option value="">Select a file first…</option>';
      el('file-type-cover-caption').textContent = '';
      el('file-type-cover-box').innerHTML =
        '<p class="text-sm text-gray-500 px-4 text-center">Select a file to see its cover.</p>';
      el('file-type-transfer-search').focus();
    },

    close() {
      const modal = el('file-type-transfer-modal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      el('file-type-transfer-loading').classList.add('hidden');
      el('file-type-transfer-loading').classList.remove('flex');
    },

    /**
     * Show the file's first page. Guarded by a token so a slow response for a
     * file the operator has already moved on from cannot overwrite a newer one.
     */
    async loadCover() {
      const box = el('file-type-cover-box');
      const caption = el('file-type-cover-caption');
      const token = ++this.coverToken;

      if (!this.fileIndexingId) return;

      caption.textContent = '';
      box.innerHTML = '<p class="text-sm text-gray-500">Loading cover…</p>';

      try {
        const res = await fetch(`/edms/file-type/cover?file_indexing_id=${encodeURIComponent(this.fileIndexingId)}`, {
          headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (token !== this.coverToken) return;

        const data = json.data || {};

        if (!data.url) {
          box.innerHTML = `<p class="text-sm text-gray-500 px-4 text-center">${escapeHtml(data.message || 'No cover available.')}</p>`;
          return;
        }

        caption.textContent = data.total_scans ? `page 1 of ${data.total_scans}` : '';

        box.innerHTML = data.is_pdf
          ? `<embed src="${escapeHtml(data.url)}#page=1&view=FitH" type="application/pdf" class="w-full h-full min-h-[320px]">`
          : `<a href="${escapeHtml(data.url)}" target="_blank" rel="noopener" class="block w-full h-full">
               <img src="${escapeHtml(data.url)}" alt="Cover page of ${escapeHtml(this.fileNumber)}"
                    class="w-full h-full object-contain max-h-[52vh]">
             </a>`;
      } catch (err) {
        if (token !== this.coverToken) return;
        box.innerHTML = `<p class="text-sm text-red-600 px-4 text-center">Could not load the cover: ${escapeHtml(err.message)}</p>`;
      }
    },

    async loadOptions() {
      const select = el('file-type-transfer-target');

      if (!this.fileIndexingId) {
        select.innerHTML = '<option value="">Select a file first…</option>';
        return;
      }

      select.innerHTML = '<option value="">Loading…</option>';

      try {
        const res = await fetch(`/edms/file-type/options?file_indexing_id=${encodeURIComponent(this.fileIndexingId)}`, {
          headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Could not load the file types.');

        el('file-type-transfer-current').textContent = json.data.current_registry || 'Unknown registry';
        el('file-type-transfer-current-type').textContent = json.data.current_file_type_label || 'Unclassified';

        select.innerHTML = '<option value="">Select a master folder…</option>';

        // Group the options the way the registry names them, so Merger's two
        // folders read as one choice with two halves.
        const groups = new Map();
        json.data.file_types.forEach((t) => {
          if (t.is_current) return; // no point offering the one it is already in
          const key = t.group || '';
          if (!groups.has(key)) groups.set(key, []);
          groups.get(key).push(t);
        });

        groups.forEach((types, groupName) => {
          const parent = groupName
            ? Object.assign(document.createElement('optgroup'), { label: groupName })
            : select;

          types.forEach((t) => {
            const opt = document.createElement('option');
            opt.value = t.key;
            opt.textContent = `${t.label}  (${t.folder}/)`;
            parent.appendChild(opt);
          });

          if (groupName) select.appendChild(parent);
        });

        // Moving a file back out to the registry root — the way to undo a
        // mis-filing without leaving it in the wrong folder meanwhile.
        if (json.data.current_file_type) {
          const opt = document.createElement('option');
          opt.value = '';
          opt.dataset.unclassify = '1';
          opt.textContent = 'Unclassified  (back to the registry root)';
          select.appendChild(opt);
        }

        select.onchange = () => this.loadPreview();
      } catch (err) {
        select.innerHTML = '<option value="">Could not load the file types</option>';
        this.showBlockers([err.message]);
      }
    },

    /**
     * The chosen target, or null for "unclassified". The empty placeholder and
     * the explicit Unclassified option share a value, so the dataset flag is what
     * separates "nothing picked yet" from "move it back to the root".
     */
    selectedTarget() {
      const select = el('file-type-transfer-target');
      const option = select.options[select.selectedIndex];

      if (!option) return { chosen: false, value: null };
      if (option.dataset.unclassify === '1') return { chosen: true, value: null };
      if (!option.value) return { chosen: false, value: null };

      return { chosen: true, value: option.value };
    },

    async loadPreview() {
      const target = this.selectedTarget();
      const confirmBtn = el('file-type-transfer-confirm');

      el('file-type-transfer-blockers').classList.add('hidden');
      el('file-type-transfer-preview').classList.add('hidden');
      confirmBtn.disabled = true;

      if (!target.chosen || !this.fileIndexingId) return;

      try {
        const res = await fetch('/edms/file-type/preview', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf(),
          },
          body: JSON.stringify({
            file_indexing_id: this.fileIndexingId,
            target_file_type: target.value,
          }),
        });

        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Preview failed.');

        this.preview = json.data;
        this.renderPreview(json.data);

        if (json.data.blockers.length) {
          this.showBlockers(json.data.blockers);
          confirmBtn.disabled = true;
        } else {
          confirmBtn.disabled = false;
        }
      } catch (err) {
        this.showBlockers([err.message]);
      }
    },

    renderPreview(data) {
      el('file-type-transfer-count-scans').textContent = data.counts.scannings;
      el('file-type-transfer-count-pages').textContent = data.counts.pagetypings;
      el('file-type-transfer-from-slug').textContent = `${data.from_slug}/${data.file_number}/`;
      el('file-type-transfer-to-slug').textContent = `${data.to_slug}/${data.file_number}/`;

      const missingWrap = el('file-type-transfer-count-missing-wrap');
      if (data.counts.missing_on_disk > 0) {
        el('file-type-transfer-count-missing').textContent = data.counts.missing_on_disk;
        missingWrap.classList.remove('hidden');
      } else {
        missingWrap.classList.add('hidden');
      }

      const rows = [
        ...data.scannings.map((r) => ({ ...r, label: `Scan #${r.id}` })),
        ...data.pagetypings.map((r) => ({ ...r, label: `Page #${r.id}` })),
      ];

      el('file-type-transfer-rows').innerHTML = rows.length
        ? rows.map((r) => `
            <tr>
              <td class="px-3 py-1.5 whitespace-nowrap">${escapeHtml(r.label)}</td>
              <td class="px-3 py-1.5 whitespace-nowrap">
                ${r.on_disk
                  ? '<span class="text-green-700">yes</span>'
                  : '<span class="text-amber-700">missing</span>'}
                ${r.conflict ? '<span class="text-red-700 ml-1">conflict</span>' : ''}
              </td>
              <td class="px-3 py-1.5 font-mono text-[11px] text-gray-600 whitespace-nowrap">${escapeHtml(r.target_path || '—')}</td>
            </tr>`).join('')
        : '<tr><td colspan="3" class="px-3 py-3 text-gray-500">No scans or typed pages — only the indexing record will change.</td></tr>';

      el('file-type-transfer-preview').classList.remove('hidden');
    },

    showBlockers(messages) {
      const box = el('file-type-transfer-blockers');
      box.innerHTML = messages.map((m) => `<p>${escapeHtml(m)}</p>`).join('');
      box.classList.remove('hidden');
    },

    async confirm() {
      const target = this.selectedTarget();
      if (!target.chosen || !this.fileIndexingId) return;

      const loading = el('file-type-transfer-loading');
      const confirmBtn = el('file-type-transfer-confirm');

      loading.classList.remove('hidden');
      loading.classList.add('flex');
      confirmBtn.disabled = true;

      try {
        const res = await fetch('/edms/file-type/move', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf(),
          },
          body: JSON.stringify({
            file_indexing_id: this.fileIndexingId,
            target_file_type: target.value,
            reason: el('file-type-transfer-reason').value || null,
          }),
        });

        const json = await res.json();

        if (!json.success) {
          this.showBlockers([json.message || 'The move failed.']);
          confirmBtn.disabled = false;
          return;
        }

        this.close();

        if (window.Swal) {
          Swal.fire({ icon: 'success', title: 'File moved', text: json.message, timer: 4000 });
        } else {
          alert(json.message);
        }

        if (this.onDone) this.onDone(json.data);
      } catch (err) {
        this.showBlockers(['The move failed: ' + err.message]);
        confirmBtn.disabled = false;
      } finally {
        loading.classList.add('hidden');
        loading.classList.remove('flex');
      }
    },
  };

  window.EdmsFileType = EdmsFileType;
})();
</script>
