/**
 * @file
 * Modal campaign behavior.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  const DEFAULT_DISMISS_DAYS = 7;

  function getConfig() {
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

          window.carlsonCampaignModal = window.carlsonCampaignModal || {};
          window.carlsonCampaignModal.clearState = function () {
            try {
              localStorage.removeItem(storageKey);
            }
            catch (ignored) {}
          };

          const state = getModalState(storageKey, config.dismissDays);
          if (!state.shouldShow) {
            return;
          }

          if (!$.fn || typeof $.fn.modal !== 'function') {
            return;
          }

          let actionTaken = false;

          $modal.off('.csm-modal-campaign').on(
            'hidden.bs.modal.csm-modal-campaign',
            function () {
              if (!actionTaken) {
                setModalState(storageKey, 'dismissed');
              }
              actionTaken = false;
            },
          );

          $modal.find('.js-csm-modal-campaign-decline')
            .off('click.csm-modal-campaign')
            .on('click.csm-modal-campaign', function () {
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
                window.open(href, '_blank', 'noopener');
              }

              setModalState(storageKey, 'acknowledged', function () {
                $modal.modal('hide');
              });
            });

          setTimeout(function () {
            $modal.modal('show');
          }, 1000);
        },
      );
    },
  };
})(jQuery, Drupal, drupalSettings, once);
