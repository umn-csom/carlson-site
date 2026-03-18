/**
 * @file
 * Sticky bar campaign runtime behavior for the Carlson Campaign module.
 *
 * Reads Drupal settings, decides whether the banner should appear based on
 * localStorage frequency rules, shows it after an optional delay, and emits
 * synthetic interaction events for close/link tracking.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  const DEFAULT_DISMISS_DAYS = 1;
  const DEFAULT_DELAY_SECONDS = 5;
  const STICKY_ANNOUNCER_ID = 'csm-campaign-sticky-announcer';

  /**
   * Namespaces localStorage state to avoid collisions across campaign types.
   */
  function getStorageKey(sessionKey) {
    const normalized = sessionKey || 'campaign_sticky_bar';
    return 'csm_campaign_sticky__' + normalized;
  }

  /**
   * Normalizes runtime config values passed from Drupal.
   *
   * Keeps JS resilient to missing/invalid drupalSettings by applying the same
   * defaults expected by the PHP builder.
   */
  function getConfig() {
    const settings = drupalSettings.csmStickyBarCampaign || {};
    const dismissDays = parseInt(settings.dismissDays, 10);
    const delaySeconds = parseInt(settings.delaySeconds, 10);
    const scheduleStartTimestamp = parseInt(settings.scheduleStartTimestamp, 10);
    const scheduleEndTimestamp = parseInt(settings.scheduleEndTimestamp, 10);

    return {
      campaignId: settings.campaignId || '',
      stickyId: settings.stickyId || '',
      sessionKey: settings.sessionKey || 'campaign_sticky_bar',
      position: settings.position === 'top' ? 'top' : 'bottom',
      dismissDays:
        Number.isFinite(dismissDays) && dismissDays >= 0 ?
          dismissDays :
          DEFAULT_DISMISS_DAYS,
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
   * Reads localStorage and determines whether the sticky bar should be shown.
   *
   * We store the last user action (dismissed/converted) and a timestamp so
   * subsequent page loads can honor the configured dismiss window.
   */
  function getStickyState(storageKey, dismissDays) {
    try {
      const stored = localStorage.getItem(storageKey);
      if (!stored) {
        return { shouldShow: true };
      }

      const data = JSON.parse(stored);
      switch (data.action) {
        case 'viewed':
          // Viewing alone should not suppress the banner on later page loads.
          return { shouldShow: true };

        case 'dismissed':
        case 'converted':
        // Backward compatibility for older stored positive-action entries.
        case 'acknowledged': {
          // A dismiss window of 0 means "show every visit" without clearing
          // the recorded action state from localStorage on reload.
          if (dismissDays === 0) {
            return { shouldShow: true };
          }

          // Hide until the configured dismiss window expires.
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
          // Unknown/corrupt state should not block the banner indefinitely.
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
   *
   * Stored separately from Drupal cache so behavior can vary per browser/user
   * without server-side state.
   */
  function setStickyState(storageKey, action) {
    try {
      localStorage.setItem(
        storageKey,
        JSON.stringify({ action, timestamp: Date.now() }),
      );
    }
    catch (e) {}
  }

  /**
   * Dispatches a synthetic interaction event for tracking integrations.
   *
   * GTM or other listeners can subscribe to one event name and inspect the
   * action/text/target payload instead of binding directly to banner DOM.
   */
  function dispatchCampaignInteraction(
    stickyElement,
    campaignId,
    action,
    nativeEvent,
    target,
    text,
  ) {
    const actionText = text || '';

    stickyElement.dispatchEvent(
      new CustomEvent('campaign:interaction', {
        bubbles: true,
        detail: {
          event: nativeEvent && nativeEvent.type ? nativeEvent.type : 'unknown',
          target: target || stickyElement,
          action_name: action,
          action_text: actionText,
          text: actionText,
          action,
          campaignId,
        },
      }),
    );
  }

  /**
   * Returns (or creates) a persistent live region for delayed sticky announces.
   *
   * Keeping this node always in DOM avoids relying on hidden->visible toggles
   * alone, which VoiceOver can miss when content appears after a timeout.
   */
  function getStickyAnnouncer() {
    const existing = document.getElementById(STICKY_ANNOUNCER_ID);
    if (existing instanceof HTMLElement) {
      return existing;
    }

    const announcer = document.createElement('div');
    announcer.id = STICKY_ANNOUNCER_ID;
    announcer.className = 'visually-hidden';
    announcer.setAttribute('aria-live', 'polite');
    announcer.setAttribute('aria-atomic', 'true');
    document.body.appendChild(announcer);
    return announcer;
  }

  /**
   * Announces sticky visibility without moving focus.
   */
  function announceStickyVisible(stickyElement) {
    const announcer = getStickyAnnouncer();
    const textContainer = stickyElement.querySelector('.campaign-sticky-bar-text');
    const message = textContainer ?
      textContainer.textContent.replace(/\s+/g, ' ').trim() :
      '';
    if (message === '') {
      return;
    }

    // Clear first, then set to trigger a single detectable live-region update.
    announcer.textContent = '';
    window.setTimeout(() => {
      announcer.textContent = message;
    }, 40);
  }

  Drupal.behaviors.carlsonCampaignStickyBarCampaign = {
    attach(context) {
      // Use drupalSettings when available, but allow the DOM to remain the
      // source of truth for the campaign ID if markup and settings differ.
      const config = getConfig();
      const selector =
        config.stickyId !== '' ?
          '#' + config.stickyId :
          '.campaign-sticky-bar';

      once('csm-sticky-bar-campaign', selector, context).forEach(
        (stickyElement) => {
          const campaignId =
            stickyElement.dataset.campaignId || config.campaignId || '';
          const storageKey = getStorageKey(config.sessionKey);
          const state = getStickyState(storageKey, config.dismissDays);
          if (!state.shouldShow) {
            // Respect the stored user decision until the dismiss window expires.
            return;
          }

          const showSticky = () => {
            // Server-side scheduling remains the primary control, but this
            // runtime check suppresses stale cached markup outside the active
            // window if the page response lags behind a schedule transition.
            if (!isScheduleActive(config)) {
              return;
            }

            stickyElement.hidden = false;
            stickyElement.setAttribute('aria-hidden', 'false');
            // Record that the visitor actually saw the banner without
            // treating viewed as a suppressing action on later page loads.
            setStickyState(storageKey, 'viewed');
            announceStickyVisible(stickyElement);
          };

          if (config.delaySeconds > 0) {
            // Delay display to avoid immediate interruption at page load.
            window.setTimeout(showSticky, config.delaySeconds * 1000);
          }
          else {
            showSticky();
          }

          stickyElement
            .querySelectorAll('.campaign-sticky-bar-close')
            .forEach((closeButton) => {
              closeButton.addEventListener('click', (event) => {
                event.preventDefault();
                // Closing is treated as a negative interaction and persisted so
                // frequency rules can suppress the banner on later visits.
                setStickyState(storageKey, 'dismissed');
                stickyElement.hidden = true;
                stickyElement.setAttribute('aria-hidden', 'true');
                dispatchCampaignInteraction(
                  stickyElement,
                  campaignId,
                  'dismissed',
                  event,
                  event.currentTarget,
                  'Sticky Bar Close',
                );
              });
            });

          stickyElement
            .querySelectorAll('.campaign-sticky-bar-text a')
            .forEach((linkElement) => {
              linkElement.addEventListener('click', (event) => {
                // Any link click inside the banner counts as a conversion.
                // We do not block navigation; tracking is emitted immediately.
                setStickyState(storageKey, 'converted');
                dispatchCampaignInteraction(
                  stickyElement,
                  campaignId,
                  'converted',
                  event,
                  event.currentTarget,
                  linkElement.textContent.trim(),
                );
              });
            });
        },
      );
    },
  };
})(Drupal, drupalSettings, once);
