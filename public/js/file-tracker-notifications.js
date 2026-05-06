(function (window, document) {
  'use strict';

  const SELECTORS = {
    root: '#file-tracker-header-notifications',
    toggle: '[data-notification-toggle]',
    panel: '[data-notification-panel]',
  };
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  function escapeHtml(value) {
    if (value === null || value === undefined) {
      return '';
    }
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatRelativeTime(value) {
    if (!value) {
      return '';
    }
    const timestamp = Date.parse(value);
    if (Number.isNaN(timestamp)) {
      return '';
    }
    const diffSeconds = Math.floor((Date.now() - timestamp) / 1000);
    if (diffSeconds < 45) {
      return 'Just now';
    }
    if (diffSeconds < 3600) {
      return `${Math.floor(diffSeconds / 60)}m ago`;
    }
    if (diffSeconds < 86400) {
      return `${Math.floor(diffSeconds / 3600)}h ago`;
    }
    if (diffSeconds < 604800) {
      return `${Math.floor(diffSeconds / 86400)}d ago`;
    }
    return new Date(timestamp).toLocaleDateString();
  }

  function buildAssignmentActions(item) {
    const raw = item.data?.raw || {};
    const trackerId = raw.file_tracker_id || raw.file_tracking_id;
    const status = (raw.assignment_status || '').toLowerCase();
    const pendingStatuses = ['pending', 'pending_acceptance', 'awaiting_acceptance'];
    const isPending = !status || pendingStatuses.includes(status);
    const isFileTracker =
      item.type?.startsWith('file_tracking') ||
      raw.module === 'file_tracking';

    if (!trackerId || !isFileTracker) {
      return '';
    }

    if (!isPending) {
      return buildCloseButton(item, true);
    }

    return `
      <div class="mt-3 space-y-2">
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="notification-action bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-3 py-1.5 rounded-full transition"
            data-notification-action="accept"
            data-tracker-id="${trackerId}"
            data-notification-id="${item.id}"
          >
            Accept
          </button>
          <button
            type="button"
            class="notification-action bg-red-600 hover:bg-red-500 text-white text-xs font-semibold px-3 py-1.5 rounded-full transition"
            data-notification-action="reject"
            data-tracker-id="${trackerId}"
            data-notification-id="${item.id}"
          >
            Reject
          </button>
        </div>
        ${buildCloseButton(item, true)}
      </div>
    `;
  }

  function buildCloseButton(item, subtle = false) {
    if (!item?.id) {
      return '';
    }

    const baseClasses = subtle
      ? 'text-xs text-white/80 hover:text-white underline decoration-dotted'
      : 'inline-flex items-center rounded-full border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition';

    return `
      <button
        type="button"
        class="${baseClasses}"
        data-notification-close
        data-notification-id="${item.id}"
      >
        ${subtle ? 'Mark as read' : 'Close'}
      </button>
    `;
  }

  async function markNotificationRead(notificationId) {
    if (!notificationId) {
      return false;
    }

    try {
      const response = await fetch(`/file-tracker-dashboard/notifications/${notificationId}/read`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error(`Unable to mark notification as read (status ${response.status}).`);
      }

      return true;
    } catch (error) {
      console.warn('Notification read acknowledgement failed', error);
      throw error;
    }
  }

  function buildEndpoints(root) {
    const endpoints = [];
    const primary = root.dataset.endpoint;
    const fallback = root.dataset.fallbackEndpoint;
    const custom = root.dataset.endpoints;

    [primary, fallback]
      .concat(
        custom
          ? custom
              .split(',')
              .map((value) => value.trim())
              .filter(Boolean)
          : []
      )
      .forEach((endpoint) => {
        if (endpoint && !endpoints.includes(endpoint)) {
          endpoints.push(endpoint);
        }
      });

    return endpoints;
  }

  function togglePanel(panel, button, open) {
    if (!panel || !button) {
      return;
    }
    const shouldOpen =
      typeof open === 'boolean' ? open : panel.classList.contains('hidden');
    if (shouldOpen) {
      panel.classList.remove('hidden');
      button.setAttribute('aria-expanded', 'true');
      return;
    }
    panel.classList.add('hidden');
    button.setAttribute('aria-expanded', 'false');
  }

  function initNotifications() {
    const root = document.querySelector(SELECTORS.root);
    if (!root || !window.PushNotificationCenter) {
      return;
    }

    const toggleBtn = root.querySelector(SELECTORS.toggle);
    const panel = root.querySelector(SELECTORS.panel);
    const list = root.querySelector('#header-notification-list');
    const listContainer = root.querySelector('#header-notification-list-container');
    const loadingState = root.querySelector('#header-notification-loading');
    const emptyState = root.querySelector('#header-notification-empty');
    const errorState = root.querySelector('#header-notification-error');
    const badge = root.querySelector('#header-notification-badge');
    const totalCountEl = root.querySelector('#header-notification-total');
    const refreshBtn = root.querySelector('#header-notification-refresh');

    const endpoints = buildEndpoints(root);
    const pollInterval = Number(root.dataset.pollInterval || 20000);

    const notificationCenter = window.PushNotificationCenter.create({
      endpoints,
      pollInterval,
      soundUrl: root.dataset.soundUrl,
      iconUrl: root.dataset.iconUrl,
    });

    function renderList(items) {
      if (!list) {
        return;
      }

      if (!items.length) {
        list.innerHTML = '';
        emptyState?.classList.remove('hidden');
        return;
      }

      emptyState?.classList.add('hidden');

      const rows = items
        .map((item) => {
          const createdLabel = formatRelativeTime(item.createdAt || item.created_at);
          const fileNo = item.data?.fileNumber || item.data?.file_number;
          const office = item.data?.officeName || item.data?.office_name;
          const primaryActions = buildAssignmentActions(item);
          const closeFallback = primaryActions ? '' : buildCloseButton(item);

          return `
            <li class="p-3 ${item.isRead ? 'bg-white' : 'bg-blue-50/60'}">
              <p class="text-sm font-semibold text-gray-800">${escapeHtml(item.title || 'File tracker update')}</p>
              <p class="mt-1 text-xs text-gray-600 leading-snug">${escapeHtml(item.body || '')}</p>
              <div class="mt-2 flex flex-wrap gap-2 text-[11px] uppercase tracking-wide text-gray-500">
                ${fileNo ? `<span class="px-2 py-1 bg-gray-100 rounded-full font-semibold">File ${escapeHtml(fileNo)}</span>` : ''}
                ${office ? `<span class="px-2 py-1 bg-gray-100 rounded-full">${escapeHtml(office)}</span>` : ''}
                ${createdLabel ? `<span class="px-2 py-1 text-gray-400">${escapeHtml(createdLabel)}</span>` : ''}
              </div>
              ${primaryActions || closeFallback}
            </li>
          `;
        })
        .join('');

      list.innerHTML = rows;
    }

    function updateCounters(unread, total) {
      if (badge) {
        if (unread > 0) {
          badge.style.display = 'flex';
          badge.textContent = unread > 99 ? '99+' : unread;
        } else {
          badge.style.display = 'none';
        }
      }

      if (totalCountEl) {
        totalCountEl.textContent = total ?? 0;
      }
    }

    function showLoading(isLoading) {
      if (isLoading) {
        loadingState?.classList.remove('hidden');
        listContainer?.classList.add('hidden');
      } else {
        loadingState?.classList.add('hidden');
        listContainer?.classList.remove('hidden');
      }
    }

    function hideErrors() {
      if (errorState) {
        errorState.classList.add('hidden');
        errorState.textContent = '';
      }
    }

    notificationCenter
      .on('update', ({ items, unreadCount, totalCount }) => {
        hideErrors();
        showLoading(false);
        renderList(items);
        updateCounters(unreadCount, totalCount);
      })
      .on('error', (error) => {
        showLoading(false);
        if (errorState) {
          errorState.textContent = error?.isAuthError
            ? 'Authentication required to load notifications. Please refresh.'
            : (error?.message || 'Unable to load notifications right now.');
          errorState.classList.remove('hidden');
        }
      });

    showLoading(true);
    notificationCenter.start();
    window.FileTrackerNotificationCenter = notificationCenter;
    if (refreshBtn) {
      refreshBtn.addEventListener('click', (event) => {
        event.preventDefault();
        showLoading(true);
        notificationCenter.refresh();
      });
    }

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        showLoading(true);
        notificationCenter.refresh();
      }
    });

    async function performAssignmentAction(trackerId, action, options = {}) {
      const endpoint =
        action === 'accept'
          ? `/api/file-trackers/${trackerId}/accept`
          : `/api/file-trackers/${trackerId}/reject`;

      const payload = {
        notification_id: options.notificationId ?? null,
      };

      if (action === 'reject') {
        payload.note = options.note || null;
      }

      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok || data?.success === false) {
        throw new Error(data?.message || 'Unable to update assignment.');
      }

      return data;
    }

    root.addEventListener('click', async (event) => {
      const closeButton = event.target.closest('[data-notification-close]');
      if (closeButton) {
        event.preventDefault();
        const notificationId = Number(closeButton.dataset.notificationId || '');
        if (!notificationId) {
          return;
        }

        const previousLabel = closeButton.innerHTML;
        closeButton.dataset.originalLabel = previousLabel;
        closeButton.innerHTML = '<span class="inline-flex items-center gap-1 text-[11px] uppercase tracking-wide text-gray-500"><span class="h-3 w-3 border-2 border-current border-t-transparent rounded-full animate-spin"></span>Updating...</span>';
        closeButton.disabled = true;

        try {
          await markNotificationRead(notificationId);
          notificationCenter.refresh();
        } catch (error) {
          alert('Unable to update notification status. Please try again.');
        } finally {
          closeButton.disabled = false;
          closeButton.innerHTML = closeButton.dataset.originalLabel || previousLabel;
          delete closeButton.dataset.originalLabel;
        }

        return;
      }

      const actionButton = event.target.closest('[data-notification-action]');
      if (!actionButton) {
        return;
      }

      event.preventDefault();

      const trackerId = Number(actionButton.dataset.trackerId || '');
      const action = actionButton.dataset.notificationAction;
      const notificationId = Number(actionButton.dataset.notificationId || '');

      if (!trackerId || !action) {
        return;
      }

      if (action === 'reject') {
        const note = window.prompt('Enter a reason for rejecting (optional):', '');
        if (note === null) {
          return;
        }
        actionButton.dataset.note = note;
      }

      const originalLabel = actionButton.innerHTML;
      actionButton.disabled = true;
      actionButton.innerHTML = '<span class="inline-flex items-center gap-1"><span class="h-4 w-4 border-2 border-white/40 border-t-transparent rounded-full animate-spin"></span>Processing...</span>';

      try {
        await performAssignmentAction(trackerId, action, {
          note: actionButton.dataset.note,
          notificationId,
        });
        if (notificationId) {
          try {
            await markNotificationRead(notificationId);
          } catch (markError) {
            console.warn('Assignment updated but notification could not be marked read', markError);
          }
        }
        notificationCenter.refresh();
        if (window.showNotification) {
          window.showNotification(
            `Assignment ${action === 'accept' ? 'accepted' : 'rejected'} successfully.`,
            action === 'accept' ? 'success' : 'warning'
          );
        }
      } catch (error) {
        alert(error?.message || 'Unable to update assignment.');
      } finally {
        delete actionButton.dataset.note;
        actionButton.disabled = false;
        actionButton.innerHTML = originalLabel;
      }
    });

    if (toggleBtn && panel) {
      toggleBtn.addEventListener('click', (event) => {
        event.preventDefault();
        const willOpen = panel.classList.contains('hidden');
        togglePanel(panel, toggleBtn, willOpen);
        if (willOpen) {
          notificationCenter.requestBrowserPermission();
        }
      });

      document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
          togglePanel(panel, toggleBtn, false);
        }
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          togglePanel(panel, toggleBtn, false);
        }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', initNotifications);
})(window, document);
