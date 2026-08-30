/**
 * Shared creator/indexer profile card.
 *
 * Any element with data-user-card opens it; pass data-user-id where the table stores a
 * user id (land recommendations, RofOs) or data-user-name where it stores a display name
 * (file_indexings.created_by). The endpoint resolves either.
 *
 * Delegated from document, so rows rendered later by DataTables work without rebinding.
 */
(function (window, document) {
  'use strict';

  var UserProfileCard = {
    cache: {},

    init: function () {
      this.root = document.getElementById('userProfileCard');
      if (!this.root) {
        return;
      }

      this.endpoint = this.root.dataset.endpoint;
      this.photo = document.getElementById('upcPhoto');
      this.fallback = document.getElementById('upcPhotoFallback');
      this.name = document.getElementById('upcName');
      this.username = document.getElementById('upcUsername');
      this.fullNameValue = document.getElementById('upcFullName');
      this.usernameValue = document.getElementById('upcUsernameValue');
      this.phone = document.getElementById('upcPhone');
      this.badge = document.getElementById('upcBadge');
      this.photoState = document.getElementById('upcPhotoState');
      this.state = document.getElementById('upcState');

      this.bind();
    },

    bind: function () {
      var self = this;

      document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-user-card]');
        if (trigger) {
          event.preventDefault();
          // Creator cells often sit inside a row that expands or navigates on click.
          event.stopPropagation();
          self.open(trigger.dataset.userId || '', trigger.dataset.userName || '');
          return;
        }

        if (event.target.closest('[data-upc-close]') || event.target === self.root) {
          self.close();
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          self.close();
        }
      });
    },

    open: function (id, name) {
      this.reset(name);
      this.root.classList.add('upc-open');
      this.root.setAttribute('aria-hidden', 'false');

      var key = id ? 'id:' + id : 'name:' + name;
      if (this.cache[key]) {
        this.render(this.cache[key]);
        return;
      }

      this.setState('Loading…');

      var params = new URLSearchParams();
      if (id) {
        params.set('id', id);
      }
      if (name) {
        params.set('name', name);
      }

      var self = this;
      fetch(this.endpoint + '?' + params.toString(), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().catch(function () {
            return { found: false };
          });
        })
        .then(function (body) {
          if (!body || !body.found) {
            self.setState((body && body.message) || 'No matching user record was found.');
            return;
          }
          self.cache[key] = body;
          self.render(body);
        })
        .catch(function () {
          self.setState('Could not load this profile.');
        });
    },

    render: function (data) {
      this.setState('');
      this.name.textContent = data.full_name || '—';
      this.username.textContent = data.username ? '@' + data.username : '';
      this.fullNameValue.textContent = data.full_name || '—';
      this.usernameValue.textContent = data.username || '—';
      this.phone.textContent = data.phone_number || '—';

      if (data.profile_url) {
        var self = this;
        // A row can point at a file that is no longer on disk; show initials rather
        // than a broken-image glyph. The badge still reflects what the record says.
        this.photo.onerror = function () {
          self.photo.classList.add('upc-hidden');
          self.fallback.classList.remove('upc-hidden');
          self.fallback.textContent = self.initials(data.full_name || data.username || '');
        };
        this.photo.src = data.profile_url;
        this.photo.alt = data.full_name || '';
        this.photo.classList.remove('upc-hidden');
        this.fallback.classList.add('upc-hidden');
      } else {
        this.photo.classList.add('upc-hidden');
        this.fallback.classList.remove('upc-hidden');
        this.fallback.textContent = this.initials(data.full_name || data.username || '');
      }

      this.setPhotoBadge(data.has_photo !== undefined ? !!data.has_photo : !!data.profile_url);
    },

    /**
     * Verified tick once a passport photo is on file; an alert mark while it is missing,
     * matching the mandatory-upload rule the account itself is held to.
     */
    setPhotoBadge: function (verified) {
      var TICK = '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
      var ALERT = '<svg viewBox="0 0 24 24" aria-hidden="true"><line x1="12" y1="7" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';

      this.badge.className = 'upc-badge ' + (verified ? 'upc-badge-verified' : 'upc-badge-missing');
      this.badge.innerHTML = verified ? TICK : ALERT;
      this.badge.setAttribute('aria-label', verified ? 'Profile picture uploaded' : 'No profile picture uploaded');
      this.badge.setAttribute('title', verified ? 'Profile picture uploaded' : 'No profile picture uploaded');

      // Only the missing case needs saying — a photo that is there speaks for itself,
      // and the tick badge already marks it.
      this.photoState.className = 'upc-photo-state ' + (verified ? 'is-verified' : 'is-missing');
      this.photoState.textContent = verified ? '' : 'No profile picture';
    },

    initials: function (value) {
      return value
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map(function (part) {
          return part.charAt(0).toUpperCase();
        })
        .join('') || '?';
    },

    reset: function (name) {
      this.name.textContent = name || ' ';
      this.username.textContent = '';
      this.fullNameValue.textContent = '—';
      this.usernameValue.textContent = '—';
      this.phone.textContent = '—';
      this.photo.classList.add('upc-hidden');
      this.fallback.classList.remove('upc-hidden');
      this.fallback.textContent = this.initials(name || '');
      this.badge.className = 'upc-badge upc-hidden';
      this.badge.innerHTML = '';
      this.photoState.textContent = '';
      this.photoState.className = 'upc-photo-state';
    },

    setState: function (message) {
      this.state.textContent = message || '';
      this.state.classList.toggle('upc-hidden', !message);
    },

    close: function () {
      this.root.classList.remove('upc-open');
      this.root.setAttribute('aria-hidden', 'true');
    }
  };

  window.UserProfileCard = UserProfileCard;

  document.addEventListener('DOMContentLoaded', function () {
    UserProfileCard.init();
  });
})(window, document);
