/**
 * @file
 * Modal Campaign behavior (ported from DIG SHD8-757).
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  const DEFAULT_DISMISS_DAYS = 7;
  const OPEN_CLASS = 'csm-dialog-modal--open';

  function supportsDialog(dialogEl) {
    return dialogEl && typeof dialogEl.showModal === 'function';
  }

  function getConfig() {
    const settings = drupalSettings.csmModalCampaign || {};
    const dismissDays = parseInt(settings.dismissDays, 10);

    return {
      sessionKey: settings.sessionKey || 'modal_campaign',
      dismissDays: Number.isFinite(dismissDays) && dismissDays > 0 ? dismissDays : DEFAULT_DISMISS_DAYS,
    };
  }

  /**
   * Read persisted state (acknowledged/dismissed) from localStorage.
   * If snooze expired or data is invalid/legacy, clear storage to reset.
   */
  function getModalState(storageKey, dismissDays) {
    try {
      const stored = localStorage.getItem(storageKey);
      if (!stored) {
        return { shouldShow: true };
      }

      const data = JSON.parse(stored);
      switch (data.action) {
        case 'acknowledged':
          return { shouldShow: false, reason: 'acknowledged' };

        case 'dismissed': {
          const daysMs = dismissDays * 24 * 60 * 60 * 1000;
          const dismissedTime = data.timestamp || 0;
          const now = Date.now();

          if (now - dismissedTime > daysMs) {
            localStorage.removeItem(storageKey);
            return { shouldShow: true };
          }

          const daysLeft = Math.ceil((daysMs - (now - dismissedTime)) / (24 * 60 * 60 * 1000));
          return { shouldShow: false, reason: 'dismissed', daysLeft: daysLeft };
        }

        default:
          try {
            localStorage.removeItem(storageKey);
          } catch (ignored) {}
          return { shouldShow: true };
      }
    } catch (e) {
      try {
        localStorage.removeItem(storageKey);
      } catch (ignored) {}
      return { shouldShow: true };
    }
  }

  /**
   * Persist durable decisions with a timestamp:
   * acknowledged (never show) and dismissed (snooze for N days).
   *
   * Ignore 'viewed' so simply opening the modal doesn't suppress it later.
   */
  function setModalState(storageKey, action, callback) {
    try {
      if (action === 'viewed') {
        if (callback) callback();
        return;
      }

      localStorage.setItem(storageKey, JSON.stringify({ action, timestamp: Date.now() }));
      if (callback) callback();
    } catch (e) {
      if (callback) callback();
    }
  }

  Drupal.behaviors.csmModalCampaign = {
    attach: function (context) {
      once('csm-modal-campaign', '#csm-modal-campaign', context).forEach((modalEl) => {
        const dialogEl = modalEl;
        const config = getConfig();
        const storageKey = config.sessionKey;

        /**
         * Helper for QA/testing to force the modal to reappear by removing
         * any persisted state.
         */
        window.csmModalCampaign = window.csmModalCampaign || {};
        window.csmModalCampaign.clearState = function () {
          try {
            localStorage.removeItem(storageKey);
          } catch (ignored) {}
        };

        const state = getModalState(storageKey, config.dismissDays);
        if (!state.shouldShow) {
          return;
        }

        if (!supportsDialog(dialogEl)) {
          return;
        }

        // Initialize action flag before opening the modal.
        let actionTaken = false;

        const lockScroll = function (locked) {
          document.documentElement.classList.toggle(OPEN_CLASS, locked);
          document.body.classList.toggle(OPEN_CLASS, locked);
        };

        const onClose = function () {
          lockScroll(false);
          if (!actionTaken) {
            setModalState(storageKey, 'dismissed');
          }
          actionTaken = false;
        };

        // Ensure we don't double-bind if this markup is re-rendered.
        if (!dialogEl.__csmModalCampaignBound) {
          dialogEl.__csmModalCampaignBound = true;

          dialogEl.addEventListener('close', onClose);
          dialogEl.addEventListener('cancel', function () {
            // Allow ESC to close; dismissed will be recorded on "close".
          });

          // Click outside closes.
          dialogEl.addEventListener('click', function (e) {
            if (e.target === dialogEl) {
              dialogEl.close();
            }
          });

          // Close button.
          dialogEl.querySelectorAll('.js-csm-modal-campaign-close').forEach((btn) => {
            btn.addEventListener('click', function () {
              dialogEl.close();
            });
          });

          // Dismiss on "Maybe Later".
          dialogEl.querySelectorAll('.js-csm-modal-campaign-decline').forEach((btn) => {
            btn.addEventListener('click', function () {
              actionTaken = true;
              setModalState(storageKey, 'dismissed', function () {
                dialogEl.close();
              });
            });
          });

          // Acknowledge on CTA: open link in new tab and record action.
          dialogEl.querySelectorAll('.js-csm-modal-campaign-cta').forEach((link) => {
            link.addEventListener('click', function (e) {
              e.preventDefault();
              actionTaken = true;

              const href = link.getAttribute('href');
              if (href) {
                window.open(href, '_blank', 'noopener');
              }

              setModalState(storageKey, 'acknowledged', function () {
                dialogEl.close();
              });
            });
          });
        }

        setTimeout(function () {
          if (!dialogEl.open) {
            lockScroll(true);
            dialogEl.showModal();
          }
        }, 1000);
      });
    },
  };
})(Drupal, drupalSettings, once);
