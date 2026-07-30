(function (window, document) {
  'use strict';

  const displayInterval = 4500;

  // Temporarily disabled: the post-login flash toasts (same card as the
  // file-tracker toasts, see file-tracker-notifications.js).
  // Set to true (or window.KLAES_ENABLE_NOTIFICATION_TOASTS = true) to re-enable.
  const TOASTS_ENABLED = false;

  function createContainer() {
    let container = document.querySelector('.notification-flash-container');
    if (container) return container;
    container = document.createElement('div');
    container.className = 'notification-flash-container';
    document.body.appendChild(container);
    return container;
  }

  function renderToast(item, container, duration) {
    const toast = document.createElement('div');
    toast.className = 'notification-flash-toast';
    toast.style.setProperty('--toast-duration', `${duration}ms`);
    toast.innerHTML = `
      <div class="notification-flash-toast__icon">🔔</div>
      <div class="notification-flash-toast__content">
        <div class="notification-flash-toast__title">${item.title || 'Notification'}</div>
        <p class="notification-flash-toast__body">${item.body || ''}</p>
        <div class="notification-flash-toast__meta">
          <span>${item.meta?.fileNumber ? `File ${item.meta.fileNumber}` : (item.meta?.source || 'System')}</span>
          <button class="notification-flash-toast__close" type="button">Close</button>
        </div>
        <div class="notification-flash-toast__progress">
          <span class="notification-flash-toast__progress-bar"></span>
        </div>
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
      if (storageKey) {
        sessionStorage.setItem(storageKey, '1');
      }
      return;
    }

    const container = createContainer();
    let index = 0;

    const showNext = () => {
      if (index >= items.length) {
        if (storageKey) {
          sessionStorage.setItem(storageKey, '1');
        }
        return;
      }

      const toast = renderToast(items[index], container, displayInterval);
      const closeBtn = toast.querySelector('.notification-flash-toast__close');

      const removeToast = () => {
        toast.classList.add('notification-flash-toast--closing');
        setTimeout(() => {
          toast.remove();
          index += 1;
          showNext();
        }, 150);
      };

      closeBtn.addEventListener('click', removeToast);
      setTimeout(removeToast, displayInterval);
    };

    showNext();
  }

  async function initFlashNotifications() {
    if (!TOASTS_ENABLED && !window.KLAES_ENABLE_NOTIFICATION_TOASTS) {
      return;
    }
    const config = window.NotificationFlashConfig;
    const storageKey = (config && config.sessionKey) || 'notificationFlashPlayed';
    if (!config || sessionStorage.getItem(storageKey) === '1') {
      return;
    }

    const allowed = await fetchSettings(config);
    if (!allowed) {
      return;
    }

    try {
      const items = await fetchQueue(config);
      if (!items.length) {
        sessionStorage.setItem(storageKey, '1');
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
