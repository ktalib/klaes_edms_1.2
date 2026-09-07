/*
 * Mandatory phone-number confirmation card.
 *
 * Two steps in one card: confirm or correct the number, then type back the code
 * sent to it. The server (RequirePhoneVerification) enforces the same rule, so
 * this file is only the way through it — never the rule itself. There is no close
 * button and Escape does nothing: every other URL redirects straight back here, so
 * a dismissable card would only look like a way out.
 *
 * Written in the same plain ES5 style as js/profile-photo-card.js: these two cards
 * load on every page of the application for the accounts that owe something, and
 * they must run on whatever the counter machines have.
 */
(function (window, document) {
  'use strict';

  var PhoneVerificationCard = {
    init: function () {
      this.root = document.getElementById('phoneVerificationCard');
      if (!this.root) {
        return;
      }

      this.sendUrl = this.root.getAttribute('data-send-url');
      this.verifyUrl = this.root.getAttribute('data-verify-url');
      this.codeLength = parseInt(this.root.getAttribute('data-code-length'), 10) || 6;

      this.stepPhone = document.getElementById('pvcStepPhone');
      this.stepCode = document.getElementById('pvcStepCode');
      this.phoneInput = document.getElementById('pvcPhoneInput');
      this.codeInput = document.getElementById('pvcCodeInput');
      this.maskedPhone = document.getElementById('pvcMaskedPhone');
      this.sendBtn = document.getElementById('pvcSendBtn');
      this.verifyBtn = document.getElementById('pvcVerifyBtn');
      this.resendBtn = document.getElementById('pvcResendBtn');
      this.backBtn = document.getElementById('pvcBackBtn');
      this.phoneMessage = document.getElementById('pvcPhoneMessage');
      this.codeMessage = document.getElementById('pvcCodeMessage');

      this.csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
      this.cooldownTimer = null;

      this.bind();
      this.lockSidebar();
      this.open();

      // A cooldown still running from a code sent on an earlier page load: the row
      // remembers when it went out, so a reload cannot be used to skip the wait.
      var pending = parseInt(this.root.getAttribute('data-cooldown'), 10) || 0;
      if (pending > 0) {
        this.showCodeStep(null);
        this.startCooldown(pending);
      }
    },

    bind: function () {
      var self = this;

      this.sendBtn.addEventListener('click', function () {
        self.send(self.phoneInput.value, self.sendBtn, 'Sending…', 'Send Verification Code');
      });

      this.resendBtn.addEventListener('click', function () {
        // Resend goes to the number the code was already sent to, not to whatever
        // is sitting in the (hidden) input.
        self.send(self.phoneInput.value, self.resendBtn, 'Sending…', 'Resend code');
      });

      this.verifyBtn.addEventListener('click', function () {
        self.verify();
      });

      this.backBtn.addEventListener('click', function () {
        self.showPhoneStep();
      });

      // Enter submits the step the user is on.
      this.phoneInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          self.sendBtn.click();
        }
      });

      this.codeInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          self.verifyBtn.click();
        }
      });

      // Digits only, and submit itself once the last one is typed — the code is
      // read off a handset, and asking for a button press after that is friction
      // for nothing.
      this.codeInput.addEventListener('input', function () {
        var cleaned = self.codeInput.value.replace(/\D+/g, '').slice(0, self.codeLength);
        if (cleaned !== self.codeInput.value) {
          self.codeInput.value = cleaned;
        }
        self.clearMessage(self.codeMessage);
        if (cleaned.length === self.codeLength) {
          self.verify();
        }
      });
    },

    /* --- requests --------------------------------------------------------- */

    send: function (phone, button, busyLabel, idleLabel) {
      var self = this;

      this.clearMessage(this.phoneMessage);
      this.clearMessage(this.codeMessage);
      this.setBusy(button, true, busyLabel);

      this.post(this.sendUrl, { phone: phone })
        .then(function (result) {
          self.setBusy(button, false, idleLabel);

          var data = (result.body && result.body.data) || {};

          if (!result.body || !result.body.success) {
            // A refusal here is nearly always something the user can act on — a
            // number that is not a Nigerian mobile, a cooldown, or the network
            // holding messages until morning. The server writes those words; this
            // only shows them, on whichever step is on screen.
            var target = self.stepCode.classList.contains('pvc-hidden')
              ? self.phoneMessage
              : self.codeMessage;
            self.showMessage(target, self.messageOf(result, 'The code could not be sent. Please try again.'), false);

            if (data.retryAfter) {
              self.startCooldown(data.retryAfter);
            }
            return;
          }

          self.showCodeStep(data.masked);
          self.showMessage(self.codeMessage, result.body.message, true);
          self.startCooldown(data.retryAfter || 0);
        })
        .catch(function () {
          self.setBusy(button, false, idleLabel);
          self.showMessage(self.phoneMessage, 'The code could not be sent. Check your connection and try again.', false);
        });
    },

    verify: function () {
      var self = this;
      var code = this.codeInput.value.replace(/\D+/g, '');

      if (code.length < this.codeLength) {
        this.showMessage(this.codeMessage, 'Enter the ' + this.codeLength + '-digit code that was sent to your phone.', false);
        return;
      }

      this.clearMessage(this.codeMessage);
      this.setBusy(this.verifyBtn, true, 'Checking…');

      this.post(this.verifyUrl, { code: code })
        .then(function (result) {
          if (result.body && result.body.success) {
            self.showMessage(self.codeMessage, result.body.message, true);
            // Reload rather than unlocking in place: the sidebar, the menus and
            // every gate-aware fragment on the page were rendered for a held
            // account, and a reload is the one way to be sure none of them is
            // left behind.
            window.setTimeout(function () {
              window.location.reload();
            }, 900);
            return;
          }

          self.setBusy(self.verifyBtn, false, 'Verify My Number');
          self.showMessage(self.codeMessage, self.messageOf(result, 'That code is not correct.'), false);
          self.codeInput.value = '';
          self.codeInput.focus();
        })
        .catch(function () {
          self.setBusy(self.verifyBtn, false, 'Verify My Number');
          self.showMessage(self.codeMessage, 'The code could not be checked. Check your connection and try again.', false);
        });
    },

    post: function (url, payload) {
      return fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': this.csrf,
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      }).then(function (response) {
        return response
          .json()
          .catch(function () {
            return {};
          })
          .then(function (body) {
            return { ok: response.ok, body: body };
          });
      });
    },

    /**
     * The server's own words where there are any — including Laravel's validation
     * bag, which carries a different shape from our own responses.
     */
    messageOf: function (result, fallback) {
      var body = result.body || {};
      if (body.message) {
        return body.message;
      }
      if (body.errors) {
        for (var key in body.errors) {
          if (Object.prototype.hasOwnProperty.call(body.errors, key) && body.errors[key].length) {
            return body.errors[key][0];
          }
        }
      }
      return fallback;
    },

    /* --- steps and state -------------------------------------------------- */

    showCodeStep: function (masked) {
      this.stepPhone.classList.add('pvc-hidden');
      this.stepCode.classList.remove('pvc-hidden');

      if (masked) {
        this.maskedPhone.textContent = masked;
      } else if (!this.maskedPhone.textContent) {
        this.maskedPhone.textContent = this.root.getAttribute('data-phone') || 'your number';
      }

      this.codeInput.value = '';
      this.codeInput.focus();
    },

    showPhoneStep: function () {
      this.stepCode.classList.add('pvc-hidden');
      this.stepPhone.classList.remove('pvc-hidden');
      this.clearMessage(this.phoneMessage);
      this.phoneInput.focus();
      this.phoneInput.select();
    },

    /**
     * Count the resend button down. The server enforces the same wait — this only
     * saves the user pressing a button that is going to refuse.
     */
    startCooldown: function (seconds) {
      var self = this;
      var left = parseInt(seconds, 10) || 0;

      window.clearInterval(this.cooldownTimer);

      if (left <= 0) {
        this.resendBtn.disabled = false;
        this.resendBtn.textContent = 'Resend code';
        return;
      }

      var tick = function () {
        if (left <= 0) {
          window.clearInterval(self.cooldownTimer);
          self.resendBtn.disabled = false;
          self.resendBtn.textContent = 'Resend code';
          return;
        }
        self.resendBtn.disabled = true;
        self.resendBtn.textContent = 'Resend in ' + left + 's';
        left -= 1;
      };

      tick();
      this.cooldownTimer = window.setInterval(tick, 1000);
    },

    setBusy: function (button, busy, label) {
      button.disabled = busy;
      button.textContent = label;
    },

    showMessage: function (target, message, ok) {
      target.textContent = message;
      target.classList.remove('pvc-hidden');
      target.classList.toggle('pvc-success', !!ok);
      target.classList.toggle('pvc-error', !ok);
    },

    clearMessage: function (target) {
      target.textContent = '';
      target.classList.add('pvc-hidden');
    },

    /* Same visual half of the rule the photo gate uses: the sidebar goes inert. */
    lockSidebar: function () {
      var sidebar = document.querySelector('.sidebar');
      if (sidebar) {
        sidebar.classList.add('sidebar-locked');
        sidebar.setAttribute('aria-disabled', 'true');
      }
    },

    open: function () {
      this.root.classList.add('pvc-open');
      this.root.setAttribute('aria-hidden', 'false');
      var self = this;
      window.setTimeout(function () {
        self.phoneInput.focus();
      }, 150);
    }
  };

  window.PhoneVerificationCard = PhoneVerificationCard;

  document.addEventListener('DOMContentLoaded', function () {
    PhoneVerificationCard.init();
  });
})(window, document);
