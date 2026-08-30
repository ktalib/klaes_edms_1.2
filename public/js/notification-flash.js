(function (window, document) {
  'use strict';

  const displayInterval = 4500;
  const STORAGE_PREFIX = 'notificationFlash-';

  // localStorage, not sessionStorage: sessionStorage is scoped to a single tab,
  // so opening the app in a second tab replayed the whole queue. The key
  // carries the server-side login timestamp, so a new login mints a new key
  // and the flash plays exactly once per login.
  function markPlayed(storageKey) {
    if (!storageKey) {
      return;
    }
    try {
      for (let i = localStorage.length - 1; i >= 0; i -= 1) {
        const key = localStorage.key(i);
        if (key && key.startsWith(STORAGE_PREFIX) && key !== storageKey) {
          localStorage.removeItem(key);
        }
      }
      localStorage.setItem(storageKey, '1');
    } catch (error) {
      /* private mode / storage disabled - flash simply replays */
    }
  }

  function hasPlayed(storageKey) {
    try {
      return localStorage.getItem(storageKey) === '1';
    } catch (error) {
      return false;
    }
  }

  // Post-login flash toasts (same card as the file-tracker toasts, see
  // file-tracker-notifications.js). Set to false to disable them again.
  const TOASTS_ENABLED = true;

  function createContainer() {
    let container = document.querySelector('.notification-flash-container');
    if (container) return container;
    container = document.createElement('div');
    container.className = 'notification-flash-container';
    document.body.appendChild(container);
    return container;
  }

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

  function renderToast(item, container, duration) {
    // Only ever one card on screen: the live poll toasts
    // (file-tracker-notifications.js) share this container and clear it the
    // same way, so the two systems can never stack on top of each other.
    container.querySelectorAll('.notification-flash-toast').forEach((existing) => existing.remove());

    const toast = document.createElement('div');
    toast.className = 'notification-flash-toast';
    toast.style.setProperty('--toast-duration', `${duration}ms`);

    const fileNo = item.meta?.fileNumber;
    const source = item.meta?.source;

    // Must match the markup the live toasts use — the legacy structure
    // (__icon / __title / __meta) inherits white text from the original dark
    // card and renders invisible against the current white card.
    toast.innerHTML = `
      <div class="toast-floating-bell-wrapper">
        <div class="toast-floating-bell">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2a5.006 5.006 0 0 0-5 5v4.586l-.707.707A1 1 0 0 0 7 14h10a1 1 0 0 0 .707-1.707L17 11.586V7a5.006 5.006 0 0 0-5-5zm-2 13a2 2 0 0 0 4 0h-4z"/>
          </svg>
        </div>
      </div>

      <div class="notification-flash-toast__content text-center">
        <div class="toast-header-text">${escapeHtml(item.title || 'Notification')}</div>
        <p class="toast-body-text mt-2 px-1">${escapeHtml(item.body || '')}</p>
        <div class="mt-2.5 flex justify-center gap-1.5 text-[9px] uppercase tracking-wide text-slate-400 font-bold">
          ${fileNo ? `<span class="px-2 py-0.5 bg-slate-100 rounded-full">File ${escapeHtml(fileNo)}</span>` : ''}
          ${!fileNo && source ? `<span class="px-2 py-0.5 bg-slate-100 rounded-full">${escapeHtml(source)}</span>` : ''}
        </div>
      </div>

      <div class="toast-action-bar">
        <button type="button" class="toast-bar-action text-blue-600 hover:bg-slate-50 transition" data-flash-action="open">
          Open
        </button>
        <button type="button" class="toast-bar-action text-slate-500 hover:bg-slate-50 transition" data-flash-action="close">
          Close
        </button>
      </div>
      <div class="notification-flash-toast__progress">
        <span class="notification-flash-toast__progress-bar"></span>
      </div>
    `;

    container.appendChild(toast);

    return toast;
  }

  async function fetchSettings(config) {
    try {
      const response = await fetch(config.settingsEndpoint, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!response.ok) {
        throw new Error('Unable to load notification settings');
      }
      const payload = await response.json();
      return Boolean(payload?.data?.flash_on_login ?? true);
    } catch (error) {
      console.warn('Notification flash: falling back to defaults', error);
      return true;
    }
  }

  async function fetchQueue(config) {
    const qs = `flash=1&status=unread&perPage=${config.limit || 5}`;
    const url = config.endpoint.includes('?') ? `${config.endpoint}&${qs}` : `${config.endpoint}?${qs}`;

    const response = await fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    if (!response.ok) {
      throw new Error('Unable to fetch flash notifications');
    }
    const payload = await response.json();
    return payload?.data?.items || [];
  }

  function playQueue(items, storageKey) {
    if (!items.length) {
      markPlayed(storageKey);
      return;
    }

    const container = createContainer();
    let index = 0;

    const showNext = () => {
      if (index >= items.length) {
        markPlayed(storageKey);
        return;
      }

      const toast = renderToast(items[index], container, displayInterval);
      const closeBtn = toast.querySelector('[data-flash-action="close"]');
      const openBtn = toast.querySelector('[data-flash-action="open"]');
      let settled = false;

      const removeToast = () => {
        if (settled) {
          return;
        }
        settled = true;
        clearTimeout(autoDismiss);
        toast.classList.add('notification-flash-toast--closing');
        setTimeout(() => {
          toast.remove();
          index += 1;
          showNext();
        }, 150);
      };

      const autoDismiss = setTimeout(removeToast, displayInterval);

      closeBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        removeToast();
      });

      openBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        window.location.href = '/notifications';
      });

      toast.addEventListener('click', (event) => {
        if (!event.target.closest('.toast-bar-action')) {
          window.location.href = '/notifications';
        }
      });
    };

    showNext();
  }

  async function initFlashNotifications() {
    if (!TOASTS_ENABLED && !window.KLAES_ENABLE_NOTIFICATION_TOASTS) {
      return;
    }
    const config = window.NotificationFlashConfig;
    const storageKey = (config && config.sessionKey) || 'notificationFlashPlayed';
    if (!config || hasPlayed(storageKey)) {
      return;
    }

    const allowed = await fetchSettings(config);
    if (!allowed) {
      return;
    }

    try {
      const items = await fetchQueue(config);
      if (!items.length) {
        markPlayed(storageKey);
        return;
      }
      if (document.hidden) {
        document.addEventListener('visibilitychange', function handleVisibility() {
          if (!document.hidden) {
            document.removeEventListener('visibilitychange', handleVisibility);
            playQueue(items, storageKey);
          }
        });
      } else {
        playQueue(items, storageKey);
      }
    } catch (error) {
      console.warn('Notification flash failed', error);
    }
  }

  document.addEventListener('DOMContentLoaded', initFlashNotifications);
})(window, document);
