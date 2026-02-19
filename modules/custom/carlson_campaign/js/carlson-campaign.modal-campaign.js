/**
 * @file
 * Modal campaign behavior.
 *
 * This powers the Drupal-managed "modal_campaign" block type. It decides when
 * to show the modal and persists user decisions in localStorage so we avoid
 * repeatedly interrupting users on every page load.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  const DEFAULT_DISMISS_DAYS = 7;

  function getConfig() {
    // Values come from hook_page_bottom() via drupalSettings.
    const settings = drupalSettings.csmModalCampaign || {};
    const dismissDays = parseInt(settings.dismissDays, 10);

    return {
      sessionKey: settings.sessionKey || 'modal_campaign',
      dismissDays:
        Number.isFinite(dismissDays) && dismissDays > 0 ?
          dismissDays :
          DEFAULT_DISMISS_DAYS,
    };
  }

  function getModalState(storageKey, dismissDays) {
    try {
      // No stored state means this user has never seen or acted on this modal.
      const stored = localStorage.getItem(storageKey);
      if (!stored) {
        return { shouldShow: true };
      }

      const data = JSON.parse(stored);
      switch (data.action) {
        case 'acknowledged':
          return { shouldShow: false, reason: 'acknowledged' };

        case 'dismissed': {
          // "Dismissed" is a temporary snooze; after N days we show again.
          const daysMs = dismissDays * 24 * 60 * 60 * 1000;
          const dismissedTime = data.timestamp || 0;
          const now = Date.now();

          if (now - dismissedTime > daysMs) {
            localStorage.removeItem(storageKey);
            return { shouldShow: true };
          }

          const daysLeft = Math.ceil(
            (daysMs - (now - dismissedTime)) / (24 * 60 * 60 * 1000),
          );
          return {
            shouldShow: false,
            reason: 'dismissed',
            daysLeft: daysLeft,
          };
        }

        default:
          try {
            // Defensive cleanup for unknown/legacy payloads.
            localStorage.removeItem(storageKey);
          }
          catch (ignored) {}
          return { shouldShow: true };
      }
    }
    catch (e) {
      try {
        localStorage.removeItem(storageKey);
      }
      catch (ignored) {}
      return { shouldShow: true };
    }
  }

  function setModalState(storageKey, action, callback) {
    try {
      // We only persist meaningful user intent, not passive impressions.
      if (action === 'viewed') {
        if (callback) {
          callback();
        }
        return;
      }

      localStorage.setItem(
        storageKey,
        JSON.stringify({ action, timestamp: Date.now() }),
      );
      if (callback) {
        callback();
      }
    }
    catch (e) {
      if (callback) {
        callback();
      }
    }
  }

  Drupal.behaviors.carlsonCampaignModalCampaign = {
    attach(context) {
      once('csm-modal-campaign', '#csm-modal-campaign', context).forEach(
        (modalElement) => {
          const $modal = $(modalElement);
          const config = getConfig();
          const storageKey = config.sessionKey;

          const state = getModalState(storageKey, config.dismissDays);
          if (!state.shouldShow) {
            // Respect prior acknowledge/snooze decision.
            return;
          }

          // The theme provides Bootstrap modal() API; bail safely if missing.
          if (!$.fn || typeof $.fn.modal !== 'function') {
            return;
          }

          let actionTaken = false;

          $modal.off('.csm-modal-campaign').on(
            'hidden.bs.modal.csm-modal-campaign',
            function () {
              // If no explicit action happened, treat close as a dismiss/snooze.
              if (!actionTaken) {
                setModalState(storageKey, 'dismissed');
              }
              actionTaken = false;
            },
          );

          $modal.find('.js-csm-modal-campaign-decline')
            .off('click.csm-modal-campaign')
            .on('click.csm-modal-campaign', function () {
              // "Maybe later" keeps the campaign eligible after dismissDays.
              actionTaken = true;
              setModalState(storageKey, 'dismissed');
            });

          $modal.find('.js-csm-modal-campaign-cta')
            .off('click.csm-modal-campaign')
            .on('click.csm-modal-campaign', function (e) {
              e.preventDefault();
              actionTaken = true;

              const href = $(this).attr('href');
              if (href) {
                // Campaign CTA intentionally opens in a new tab.
                window.open(href, '_blank', 'noopener');
              }

              // CTA click is a strong signal: don't show this campaign again.
              setModalState(storageKey, 'acknowledged', function () {
                $modal.modal('hide');
              });
            });

          // Small delay prevents immediate visual jump on initial page paint.
          setTimeout(function () {
            $modal.modal('show');
          }, 1000);
        },
      );
    },
  };
})(jQuery, Drupal, drupalSettings, once);
