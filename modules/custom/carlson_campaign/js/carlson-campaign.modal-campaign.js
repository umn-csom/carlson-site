/**
 * @file
 * Modal campaign behavior.
 *
 * Uses native <dialog> behavior and persists user actions in localStorage.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  const DEFAULT_DISMISS_DAYS = 7;
  const OPEN_DELAY_MS = 1000;

  function escapeSelectorId(id) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(id);
    }
    return id.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  function getConfig() {
    const settings = drupalSettings.csmModalCampaign || {};
    const dismissDays = parseInt(settings.dismissDays, 10);

    return {
      campaignId: settings.campaignId || '',
      modalId: settings.modalId || '',
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

        case 'dismissed':
        case 'declined': {
          const daysMs = dismissDays * 24 * 60 * 60 * 1000;
          const dismissedTime = data.timestamp || 0;
          const now = Date.now();

          if (now - dismissedTime > daysMs) {
            localStorage.removeItem(storageKey);
            return { shouldShow: true };
          }

          return { shouldShow: false, reason: data.action };
        }

        default:
          localStorage.removeItem(storageKey);
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

  function setModalState(storageKey, action) {
    try {
      if (action === 'viewed') {
        return;
      }
      localStorage.setItem(
        storageKey,
        JSON.stringify({ action, timestamp: Date.now() }),
      );
    }
    catch (e) {}
  }

  function getTargetText(target) {
    if (!target || typeof target.textContent !== 'string') {
      return '';
    }
    return target.textContent.trim();
  }

  function dispatchCampaignInteraction(
    modalElement,
    campaignId,
    action,
    nativeEvent,
    target,
  ) {
    const detail = {
      event: nativeEvent && nativeEvent.type ? nativeEvent.type : 'unknown',
      target: target || modalElement,
      text: getTargetText(target),
      action,
      campaignId,
    };

    modalElement.dispatchEvent(
      new CustomEvent('campaign:interaction', {
        bubbles: true,
        detail,
      }),
    );
  }

  Drupal.behaviors.carlsonCampaignModalCampaign = {
    attach(context) {
      const config = getConfig();
      const selector =
        config.modalId !== '' ?
          '#' + escapeSelectorId(config.modalId) :
          '.campaign-modal';

      once('csm-modal-campaign', selector, context).forEach((modalElement) => {
        if (!(modalElement instanceof HTMLDialogElement)) {
          return;
        }
        if (typeof modalElement.showModal !== 'function') {
          return;
        }

        const campaignId =
          modalElement.dataset.campaignId || config.campaignId || '';
        const storageKey = config.sessionKey || 'campaign_modal';
        const state = getModalState(storageKey, config.dismissDays);

        if (!state.shouldShow) {
          return;
        }

        let explicitAction = null;

        const closeWithAction = (action, nativeEvent, target) => {
          explicitAction = action;
          setModalState(storageKey, action);
          dispatchCampaignInteraction(
            modalElement,
            campaignId,
            action,
            nativeEvent,
            target,
          );
          if (modalElement.open) {
            modalElement.close(action);
          }
        };

        modalElement.addEventListener('cancel', (event) => {
          event.preventDefault();
          closeWithAction('dismissed', event, modalElement);
        });

        modalElement.addEventListener('click', (event) => {
          if (event.target === modalElement) {
            closeWithAction('dismissed', event, modalElement);
          }
        });

        modalElement.querySelectorAll('.campaign-banner-close').forEach(
          (closeButton) => {
            closeButton.addEventListener('click', (event) => {
              event.preventDefault();
              closeWithAction('dismissed', event, event.currentTarget);
            });
          },
        );

        modalElement.querySelectorAll('.campaign-modal-decline').forEach(
          (declineButton) => {
            declineButton.addEventListener('click', (event) => {
              event.preventDefault();
              closeWithAction('declined', event, event.currentTarget);
            });
          },
        );

        modalElement.querySelectorAll('.campaign-banner-acknowledge').forEach(
          (acknowledgeTarget) => {
            acknowledgeTarget.addEventListener('click', (event) => {
              event.preventDefault();

              if (
                acknowledgeTarget instanceof HTMLAnchorElement &&
                acknowledgeTarget.href
              ) {
                window.open(acknowledgeTarget.href, '_blank', 'noopener');
              }

              closeWithAction('acknowledged', event, event.currentTarget);
            });
          },
        );

        modalElement.addEventListener('close', (event) => {
          if (explicitAction === null) {
            setModalState(storageKey, 'dismissed');
            dispatchCampaignInteraction(
              modalElement,
              campaignId,
              'dismissed',
              event,
              modalElement,
            );
          }
          explicitAction = null;
        });

        setTimeout(() => {
          if (!modalElement.open) {
            modalElement.showModal();
          }
        }, OPEN_DELAY_MS);
      });
    },
  };
})(Drupal, drupalSettings, once);
