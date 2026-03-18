/**
 * @file
 * Modal campaign runtime behavior for the Carlson Campaign module.
 *
 * Responsibilities in this file:
 * - Read campaign runtime settings from drupalSettings.
 * - Decide whether to show the modal based on stored user action state.
 * - Handle all modal interactions (close/backdrop/ESC/convert/decline).
 * - Persist action state to localStorage for dismiss/convert behavior.
 * - Emit campaign interaction events for tracking integrations (for example
 *   GTM listeners) with consistent action metadata.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  const DEFAULT_DISMISS_DAYS = 1;
  const DEFAULT_DELAY_SECONDS = 0;

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
    const delaySeconds = parseInt(settings.delaySeconds, 10);
    const scheduleStartTimestamp = parseInt(settings.scheduleStartTimestamp, 10);
    const scheduleEndTimestamp = parseInt(settings.scheduleEndTimestamp, 10);
    const stopOnConvert = settings.stopOnConvert;

    return {
      campaignId: settings.campaignId || '',
      modalId: settings.modalId || '',
      sessionKey: settings.sessionKey || 'modal_campaign',
      dismissDays:
        Number.isFinite(dismissDays) && dismissDays >= 0 ?
          dismissDays :
          DEFAULT_DISMISS_DAYS,
      stopOnConvert: !(
        stopOnConvert === false ||
        stopOnConvert === 0 ||
        stopOnConvert === '0' ||
        stopOnConvert === 'false'
      ),
      delaySeconds:
        Number.isFinite(delaySeconds) && delaySeconds >= 0 ?
          delaySeconds :
          DEFAULT_DELAY_SECONDS,
      scheduleStartTimestamp: Number.isFinite(scheduleStartTimestamp) ?
        scheduleStartTimestamp :
        null,
      scheduleEndTimestamp: Number.isFinite(scheduleEndTimestamp) ?
        scheduleEndTimestamp :
        null,
    };
  }

  /**
   * Evaluates whether the campaign is active at the current browser time.
   */
  function isScheduleActive(config) {
    const now = Math.floor(Date.now() / 1000);

    if (
      config.scheduleStartTimestamp !== null &&
      config.scheduleEndTimestamp !== null &&
      config.scheduleStartTimestamp >= config.scheduleEndTimestamp
    ) {
      return false;
    }

    if (
      config.scheduleStartTimestamp !== null &&
      now < config.scheduleStartTimestamp
    ) {
      return false;
    }

    if (
      config.scheduleEndTimestamp !== null &&
      now >= config.scheduleEndTimestamp
    ) {
      return false;
    }

    return true;
  }

  /**
   * Reads localStorage and determines whether the modal should be shown.
   */
  function getModalState(storageKey, dismissDays, stopOnConvert) {
    try {
      const stored = localStorage.getItem(storageKey);
      if (!stored) {
        return { shouldShow: true };
      }

      const data = JSON.parse(stored);
      switch (data.action) {
        case 'viewed':
          // Viewing alone should not suppress the modal on later page loads.
          return { shouldShow: true };

        case 'converted':
          if (stopOnConvert) {
            return { shouldShow: false, reason: 'converted' };
          }
        // Fall through: when stop-on-convert is disabled, converted actions
        // (and legacy stored acknowledged actions) follow repeat-delay rules.
        case 'acknowledged':
        case 'dismissed':
        case 'declined': {
          if (dismissDays === 0) {
            // "Show every visit" should not wipe the recorded action state.
            return { shouldShow: true };
          }
          const daysMs = dismissDays * 24 * 60 * 60 * 1000;
          const actionTime = data.timestamp || 0;
          const now = Date.now();

          if (now - actionTime > daysMs) {
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
    textOverride,
  ) {
    const actionText = textOverride || getTargetText(target);
    const detail = {
      event: nativeEvent && nativeEvent.type ? nativeEvent.type : 'unknown',
      target: target || modalElement,
      // Canonical keys for analytics integrations.
      action_name: action,
      action_text: actionText,
      // Backward-compatible aliases for existing listeners.
      action: action,
      text: actionText,
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
      '.campaign-modal__close',
      '.campaign-modal-convert',
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
   * Attempts to restore focus to the pre-modal target.
   */
  function restorePriorFocus(previouslyFocusedElement, modalElement) {
    if (
      !(previouslyFocusedElement instanceof HTMLElement) ||
      !document.contains(previouslyFocusedElement) ||
      previouslyFocusedElement === document.body ||
      modalElement.contains(previouslyFocusedElement)
    ) {
      return false;
    }

    previouslyFocusedElement.focus();
    return document.activeElement === previouslyFocusedElement;
  }

  /**
   * Moves focus to a stable page target when no opener focus is available.
   */
  function focusCloseFallbackTarget() {
    const selectors = [
      'main h1',
      'main',
      '#main-content',
      '.skip-link',
      'a[href^="#main-content"]',
    ];

    for (const selector of selectors) {
      const candidate = document.querySelector(selector);
      if (!(candidate instanceof HTMLElement)) {
        continue;
      }

      // Headings/main are not usually keyboard focusable; make them
      // programmatically focusable for deterministic post-close landing.
      if (!candidate.matches('[tabindex],a[href],button,input,select,textarea')) {
        candidate.setAttribute('tabindex', '-1');
      }

      try {
        candidate.focus({ preventScroll: true });
      }
      catch (e) {
        candidate.focus();
      }

      if (document.activeElement === candidate) {
        return true;
      }
    }

    return false;
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

        // Prefer canonical tracking attribute from DOM, then legacy/fallback.
        const campaignId =
          modalElement.dataset.trackingId ||
          modalElement.dataset.campaignId ||
          config.campaignId ||
          '';
        const storageKey = getStorageKey(config.sessionKey);
        const state = getModalState(
          storageKey,
          config.dismissDays,
          config.stopOnConvert,
        );

        // Respect previously stored acknowledge/dismiss/decline decisions.
        if (!state.shouldShow) {
          return;
        }

        // Tracks whether close was triggered by an explicit user action.
        let explicitAction = null;
        let previouslyFocusedElement = null;

        // Central action handler keeps persistence + tracking consistent.
        const closeWithAction = (
          action,
          nativeEvent,
          target,
          textOverride,
        ) => {
          explicitAction = action;
          setModalState(storageKey, action);
          dispatchCampaignInteraction(
            modalElement,
            campaignId,
            action,
            nativeEvent,
            target,
            textOverride,
          );
          if (modalElement.open) {
            modalElement.close(action);
          }
        };

        // ESC key on <dialog> emits cancel; treat as dismissed.
        modalElement.addEventListener('cancel', (event) => {
          event.preventDefault();
          closeWithAction(
            'dismissed',
            event,
            modalElement,
            'Dismissed via keyboard ESC key',
          );
        });

        // Click on dialog backdrop (outside panel) counts as dismissed.
        modalElement.addEventListener('click', (event) => {
          if (event.target === modalElement) {
            closeWithAction('dismissed', event, modalElement);
          }
        });

        modalElement.querySelectorAll('.campaign-modal__close').forEach(
          (closeButton) => {
            closeButton.addEventListener('click', (event) => {
              event.preventDefault();
              closeWithAction(
                'dismissed',
                event,
                event.currentTarget,
                'Modal Close Button',
              );
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

        modalElement.querySelectorAll('.campaign-modal-convert').forEach(
          (convertTarget) => {
            convertTarget.addEventListener('click', (event) => {
              // Preserve the anchor's native navigation behavior while still
              // recording the conversion state before the browser follows it.
              closeWithAction('converted', event, event.currentTarget);
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

          // Restore opener focus when possible; otherwise use a predictable
          // page fallback for auto-open scenarios with no real opener.
          if (!restorePriorFocus(previouslyFocusedElement, modalElement)) {
            focusCloseFallbackTarget();
          }

          explicitAction = null;
          previouslyFocusedElement = null;
        });

        const showModal = () => {
          // Server-side scheduling remains authoritative, but this runtime
          // check prevents stale cached markup from opening outside the
          // campaign window if the HTML has not rotated yet.
          if (!isScheduleActive(config)) {
            return;
          }

          if (!modalElement.open) {
            if (document.activeElement instanceof HTMLElement) {
              previouslyFocusedElement = document.activeElement;
            }
            modalElement.showModal();
            // Record that the visitor actually saw the modal without letting
            // the passive viewed state suppress later displays by itself.
            setModalState(storageKey, 'viewed');
            // Keep initial focus deterministic for keyboard/screen readers.
            window.requestAnimationFrame(() => {
              focusInitialModalControl(modalElement);
            });
          }
        };

        // Delay display to avoid immediate interruption at page load.
        if (config.delaySeconds > 0) {
          window.setTimeout(showModal, config.delaySeconds * 1000);
        }
        else {
          showModal();
        }
      });
    },
  };
})(Drupal, drupalSettings, once);
