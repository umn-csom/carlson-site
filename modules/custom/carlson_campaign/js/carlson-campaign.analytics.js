/**
 * @file
 * Bridges campaign interaction events into GTM-compatible dataLayer pushes.
 *
 * Campaign runtime code emits one normalized DOM event, `campaign:interaction`.
 * This file listens for that internal event and translates it into stable
 * analytics events with campaign metadata that GTM/GA4 can map as needed.
 */

(function (Drupal) {
  'use strict';

  /**
   * Maps internal action names to public analytics event names.
   */
  function getAnalyticsEventName(actionName) {
    switch (actionName) {
      case 'viewed':
        return 'campaign_view';

      case 'converted':
        return 'campaign_convert';

      case 'dismissed':
        return 'campaign_dismiss';

      case 'declined':
        return 'campaign_decline';

      default:
        return '';
    }
  }

  /**
   * Adds a payload field only when it has a meaningful value.
   */
  function setOptionalValue(payload, key, value) {
    if (value === '' || value === null || typeof value === 'undefined') {
      return;
    }

    payload[key] = value;
  }

  /**
   * Builds the GTM payload from normalized campaign interaction detail.
   */
  function buildPayload(detail) {
    const analyticsEventName = getAnalyticsEventName(detail.action_name);
    if (analyticsEventName === '') {
      return null;
    }

    const payload = {
      event: analyticsEventName,
      campaign_key: detail.campaign_key || '',
      campaign_id: detail.campaign_id || detail.campaignId || '',
      campaign_variant_name:
        detail.campaign_variant_name || detail.variant_name || '',
      campaign_type: detail.campaign_type || '',
      campaign_page_path: window.location.pathname + window.location.search,
    };

    if (detail.campaign_type === 'sticky_banner') {
      setOptionalValue(
        payload,
        'campaign_placement',
        detail.campaign_placement || '',
      );
    }

    switch (analyticsEventName) {
      case 'campaign_convert':
        setOptionalValue(
          payload,
          'campaign_cta_text',
          detail.campaign_cta_text || detail.action_text || '',
        );
        setOptionalValue(payload, 'campaign_cta_url', detail.campaign_cta_url);
        setOptionalValue(payload, 'campaign_action_id', detail.action_id);
        setOptionalValue(payload, 'campaign_action_index', detail.action_index);
        break;

      case 'campaign_dismiss':
        setOptionalValue(
          payload,
          'campaign_dismiss_type',
          detail.dismiss_type || '',
        );
        setOptionalValue(payload, 'campaign_action_id', detail.action_id);
        break;

      case 'campaign_decline':
        setOptionalValue(
          payload,
          'campaign_decline_text',
          detail.campaign_decline_text || detail.action_text || '',
        );
        setOptionalValue(payload, 'campaign_action_id', detail.action_id);
        break;
    }

    return payload;
  }

  /**
   * Pushes campaign interaction payloads into the shared GTM dataLayer.
   */
  function pushCampaignInteraction(event) {
    if (!event.detail || typeof event.detail !== 'object') {
      return;
    }

    const payload = buildPayload(event.detail);
    if (!payload) {
      return;
    }

    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(payload);
  }

  Drupal.behaviors.carlsonCampaignAnalytics = {
    attach(context) {
      if (context !== document || this.initialized) {
        return;
      }

      this.initialized = true;
      document.addEventListener('campaign:interaction', pushCampaignInteraction);
    },
  };
})(Drupal);
