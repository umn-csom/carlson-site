/**
 * @file
 * Sticky bar campaign runtime behavior for the Carlson Campaign module.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  const DEFAULT_DISMISS_DAYS = 1;
  const DEFAULT_DELAY_SECONDS = 5;

  /**
   * Normalizes runtime config values passed from Drupal.
   */
  function getConfig() {
    const settings = drupalSettings.csmStickyBarCampaign || {};
    const dismissDays = parseInt(settings.dismissDays, 10);
    const delaySeconds = parseInt(settings.delaySeconds, 10);

    return {
      campaignId: settings.campaignId || '',
      stickyId: settings.stickyId || '',
      sessionKey: settings.sessionKey || 'campaign_sticky_bar',
      dismissDays:
        Number.isFinite(dismissDays) && dismissDays >= 0 ?
          dismissDays :
          DEFAULT_DISMISS_DAYS,
      delaySeconds:
        Number.isFinite(delaySeconds) && delaySeconds >= 0 ?
          delaySeconds :
          DEFAULT_DELAY_SECONDS,
    };
  }

  /**
   * Reads localStorage and determines whether the sticky bar should be shown.
   */
  function getStickyState(storageKey, dismissDays) {
    try {
      const stored = localStorage.getItem(storageKey);
      if (!stored) {
        return { shouldShow: true };
      }

      const data = JSON.parse(stored);
      switch (data.action) {
        case 'dismissed':
        case 'acknowledged': {
          if (dismissDays === 0) {
            localStorage.removeItem(storageKey);
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
   */
  function dispatchCampaignInteraction(
    stickyElement,
    campaignId,
    action,
    nativeEvent,
    target,
    text,
  ) {
    stickyElement.dispatchEvent(
      new CustomEvent('campaign:interaction', {
        bubbles: true,
        detail: {
          event: nativeEvent && nativeEvent.type ? nativeEvent.type : 'unknown',
          target: target || stickyElement,
          text: text || '',
          action,
          campaignId,
        },
      }),
    );
  }

  Drupal.behaviors.carlsonCampaignStickyBarCampaign = {
    attach(context) {
      const config = getConfig();
      const selector =
        config.stickyId !== '' ?
          '#' + config.stickyId :
          '.campaign-sticky-bar';

      once('csm-sticky-bar-campaign', selector, context).forEach(
        (stickyElement) => {
          const campaignId =
            stickyElement.dataset.campaignId || config.campaignId || '';
          const storageKey = config.sessionKey || 'campaign_sticky_bar';
          const state = getStickyState(storageKey, config.dismissDays);
          if (!state.shouldShow) {
            return;
          }

          const showSticky = () => {
            stickyElement.hidden = false;
            stickyElement.setAttribute('aria-hidden', 'false');
          };

          if (config.delaySeconds > 0) {
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
                setStickyState(storageKey, 'acknowledged');
                dispatchCampaignInteraction(
                  stickyElement,
                  campaignId,
                  'acknowledged',
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
