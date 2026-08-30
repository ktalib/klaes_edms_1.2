/**
 * Mandatory profile-picture card.
 *
 * Opens from the "Upload photo" control in the missing-photo banner (the card is not
 * a page of its own, so the banner never navigates to the profile page). On success it
 * swaps every avatar on the page, unlocks the sidebar and drops the banner, so the user
 * carries on without logging out. The server-side gate is RequireProfilePhoto.
 */
(function (window, document) {
  'use strict';

  var ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
  }

  var ProfilePhotoCard = {
    init: function () {
      this.root = document.getElementById('profilePhotoCard');
      if (!this.root) {
        return;
      }

      this.input = document.getElementById('profilePhotoInput');
      this.avatar = document.getElementById('profilePhotoDropzone');
      this.preview = document.getElementById('profilePhotoPreview');
      this.placeholder = document.getElementById('profilePhotoPreviewPlaceholder');
      this.submit = document.getElementById('profilePhotoSubmit');
      this.error = document.getElementById('profilePhotoError');
      this.maxBytes = (parseInt(this.root.dataset.maxKb, 10) || 2048) * 1024;

      this.bind();
      this.lockSidebar();

      if (this.root.dataset.autoOpen === 'true') {
        this.open();
      }
    },

    bind: function () {
      var self = this;

      // Any control that asks for the card, wherever it lives on the page.
      document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-profile-photo-trigger]');
        if (trigger) {
          event.preventDefault();
          self.open();
        }
      });

      this.input.addEventListener('change', function () {
        self.clearError();
        var file = self.input.files && self.input.files[0];
        if (!file) {
          self.resetPreview();
          return;
        }
        if (!self.validate(file)) {
          self.input.value = '';
          self.resetPreview();
          return;
        }
        var reader = new FileReader();
        reader.onload = function (e) {
          self.preview.src = e.target.result;
          self.preview.classList.remove('ppc-hidden');
          self.placeholder.classList.add('ppc-hidden');
          self.avatar.classList.add('ppc-filled');
        };
        reader.readAsDataURL(file);
      });

      this.submit.addEventListener('click', function () {
        self.upload();
      });

      // The preview circle doubles as a picker.
      this.avatar.addEventListener('click', function () {
        self.input.click();
      });
      this.avatar.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          self.input.click();
        }
      });
    },

    validate: function (file) {
      if (ALLOWED_TYPES.indexOf(file.type) === -1) {
        this.showError('Please select a JPG, PNG or GIF image.');
        return false;
      }
      if (file.size > this.maxBytes) {
        this.showError('The photo must be 2MB or smaller.');
        return false;
      }
      return true;
    },

    upload: function () {
      var self = this;
      var file = this.input.files && this.input.files[0];

      if (!file) {
        this.showError('Please choose a passport photo to upload.');
        return;
      }
      if (!this.validate(file)) {
        return;
      }

      var data = new FormData();
      data.append('profile', file);

      this.setBusy(true);

      fetch(this.root.dataset.uploadUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        },
        body: data,
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().then(function (body) {
            return { ok: response.ok, body: body };
          });
        })
        .then(function (result) {
          if (!result.ok || !result.body.success) {
            var message = result.body.message
              || (result.body.errors && result.body.errors.profile && result.body.errors.profile[0])
              || 'The photo could not be uploaded. Please try again.';
            self.showError(message);
            self.setBusy(false);
            return;
          }

          self.onUploaded(result.body);
        })
        .catch(function () {
          self.showError('The photo could not be uploaded. Please check your connection and try again.');
          self.setBusy(false);
        });
    },

    onUploaded: function (body) {
      this.refreshAvatars(body.profile_url);
      this.unlockSidebar();

      var banner = document.getElementById('missingProfilePhotoBanner');
      if (banner) {
        banner.remove();
      }

      this.close();
      this.setBusy(false);

      if (window.AppLayout && typeof window.AppLayout.showAlerts === 'function') {
        window.AppLayout.showAlerts({ success: body.message });
      } else if (typeof window.Swal !== 'undefined') {
        window.Swal.fire({ icon: 'success', title: 'Success', text: body.message });
      } else {
        window.alert(body.message);
      }
    },

    /**
     * Swap the freshly uploaded photo into the header, sidebar and welcome card
     * without a reload. Placeholder icons next to a slot are hidden.
     */
    refreshAvatars: function (url) {
      if (!url) {
        return;
      }

      var slots = document.querySelectorAll('[data-user-avatar]');
      slots.forEach(function (slot) {
        var img = slot.querySelector('img');
        if (!img) {
          img = document.createElement('img');
          img.className = 'w-full h-full object-cover';
          img.alt = 'Profile';
          slot.appendChild(img);
        }
        img.src = url;
        img.classList.remove('hidden');

        slot.querySelectorAll('svg').forEach(function (icon) {
          icon.classList.add('hidden');
        });
      });
    },

    lockSidebar: function () {
      var sidebar = document.querySelector('.sidebar');
      if (sidebar) {
        sidebar.classList.add('sidebar-locked');
        sidebar.setAttribute('aria-disabled', 'true');
      }
    },

    unlockSidebar: function () {
      var sidebar = document.querySelector('.sidebar');
      if (sidebar) {
        sidebar.classList.remove('sidebar-locked');
        sidebar.removeAttribute('aria-disabled');
      }
    },

    setBusy: function (busy) {
      this.submit.disabled = busy;
      this.submit.textContent = busy ? 'Uploading…' : 'Upload Profile Picture';
    },

    showError: function (message) {
      this.error.textContent = message;
      this.error.classList.remove('ppc-hidden');
    },

    clearError: function () {
      this.error.textContent = '';
      this.error.classList.add('ppc-hidden');
    },

    resetPreview: function () {
      this.preview.src = '';
      this.preview.classList.add('ppc-hidden');
      this.placeholder.classList.remove('ppc-hidden');
      this.avatar.classList.remove('ppc-filled');
    },

    open: function () {
      this.root.classList.add('ppc-open');
      this.root.setAttribute('aria-hidden', 'false');
    },

    close: function () {
      this.root.classList.remove('ppc-open');
      this.root.setAttribute('aria-hidden', 'true');
    }
  };

  window.ProfilePhotoCard = ProfilePhotoCard;

  document.addEventListener('DOMContentLoaded', function () {
    ProfilePhotoCard.init();
  });
})(window, document);
