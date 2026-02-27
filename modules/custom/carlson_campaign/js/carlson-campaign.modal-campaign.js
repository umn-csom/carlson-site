/**
 * @file
 * Modal campaign runtime behavior for the Carlson Campaign module.
 *
 * Responsibilities in this file:
 * - Read campaign runtime settings from drupalSettings.
 * - Decide whether to show the modal based on stored user action state.
 * - Handle all modal interactions (close/backdrop/ESC/acknowledge/decline).
 * - Persist action state to localStorage for dismiss/acknowledge behavior.
 * - Emit campaign interaction events for tracking integrations (for example
 *   GTM listeners) with consistent action metadata.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  const DEFAULT_DISMISS_DAYS = 1;
  const OPEN_DELAY_MS = 1000;

  /**
   * Namespaces localStorage state to avoid collisions across campaign types.
   */
  function getStorageKey(sessionKey) {
    const normalized = sessionKey || 'modal_campaign';
    return 'csm_campaign_modal__' + normalized;
  }

  /**
   * Normalizes runtime config values passed from Drupal.
   */
  function getConfig() {
    const settings = drupalSettings.csmModalCampaign || {};
    const dismissDays = parseInt(settings.dismissDays, 10);

    return {
      campaignId: settings.campaignId || '',
      modalId: settings.modalId || '',
      sessionKey: settings.sessionKey || 'modal_campaign',
      dismissDays:
        Number.isFinite(dismissDays) && dismissDays >= 0 ?
          dismissDays :
          DEFAULT_DISMISS_DAYS,
    };
  }

  /**
   * Reads localStorage and determines whether the modal should be shown.
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

        case 'dismissed':
        case 'declined': {
          if (dismissDays === 0) {
            localStorage.removeItem(storageKey);
            return { shouldShow: true };
          }
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

  /**
   * Persists a user action so future page loads can respect that decision.
   */
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

  /**
   * Returns interaction text only for actionable controls.
   */
  function getTargetText(target) {
    if (!target || typeof target.textContent !== 'string') {
      return '';
    }

    // ESC/backdrop dismissals target the dialog container, not a tap control.
    // Only report text for actionable elements to keep analytics payload clean.
    if (
      !(target instanceof HTMLButtonElement) &&
      !(target instanceof HTMLAnchorElement)
    ) {
      return '';
    }

    return target.textContent.trim();
  }

  /**
   * Dispatches a synthetic interaction event with normalized tracking details.
   */
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

  /**
   * Focuses a predictable first control when the modal opens.
   */
  function focusInitialModalControl(modalElement) {
    const titleId = modalElement.id + '__title';
    const selectors = [
      '#' + titleId,
      '.campaign-banner-close',
      '.campaign-banner-acknowledge',
      '.campaign-modal-decline',
      'button:not([disabled])',
      'a[href]',
      '[tabindex]:not([tabindex="-1"])',
    ];

    for (const selector of selectors) {
      const candidate = modalElement.querySelector(selector);
      if (candidate instanceof HTMLElement) {
        candidate.focus();
        return;
      }
    }
  }

  /**
   * Wires modal behavior to each campaign dialog once per page context.
   */
  Drupal.behaviors.carlsonCampaignModalCampaign = {
    attach(context) {
      const config = getConfig();
      const selector =
        config.modalId !== '' ?
          '#' + config.modalId :
          '.campaign-modal';

      // Attach exactly once per dialog element for this Drupal behavior cycle.
      once('csm-modal-campaign', selector, context).forEach((modalElement) => {
        // Guard for native dialog support and expected element type.
        if (!(modalElement instanceof HTMLDialogElement)) {
          return;
        }
        if (typeof modalElement.showModal !== 'function') {
          return;
        }

        // Prefer DOM-provided campaign metadata, then settings fallback.
        const campaignId =
          modalElement.dataset.campaignId || config.campaignId || '';
        const storageKey = getStorageKey(config.sessionKey);
        const state = getModalState(storageKey, config.dismissDays);

        // Respect previously stored acknowledge/dismiss/decline decisions.
        if (!state.shouldShow) {
          return;
        }

        // Tracks whether close was triggered by an explicit user action.
        let explicitAction = null;
        let previouslyFocusedElement = null;

        // Central action handler keeps persistence + tracking consistent.
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

        // ESC key on <dialog> emits cancel; treat as dismissed.
        modalElement.addEventListener('cancel', (event) => {
          event.preventDefault();
          closeWithAction('dismissed', event, modalElement);
        });

        // Click on dialog backdrop (outside panel) counts as dismissed.
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

              // Keep legacy UX: acknowledge links open in new tab.
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

        // Defensive fallback: any unclassified close is treated as dismissed.
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

          // Restore focus to the prior element when possible.
          if (
            previouslyFocusedElement instanceof HTMLElement &&
            document.contains(previouslyFocusedElement) &&
            previouslyFocusedElement !== document.body &&
            !modalElement.contains(previouslyFocusedElement)
          ) {
            previouslyFocusedElement.focus();
          }

          explicitAction = null;
          previouslyFocusedElement = null;
        });

        // Delay avoids showing the dialog during initial paint.
        setTimeout(() => {
          if (!modalElement.open) {
            if (document.activeElement instanceof HTMLElement) {
              previouslyFocusedElement = document.activeElement;
            }
            modalElement.showModal();
            // Keep initial focus deterministic for keyboard/screen readers.
            window.requestAnimationFrame(() => {
              focusInitialModalControl(modalElement);
            });
          }
        }, OPEN_DELAY_MS);
      });
    },
  };
})(Drupal, drupalSettings, once);
