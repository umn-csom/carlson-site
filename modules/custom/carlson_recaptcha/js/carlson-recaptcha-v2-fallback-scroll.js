/**
 * @file
 * Scrolls the v2 fallback notice into view without waiting for Drupal.
 *
 * Loaded from the page head (see v2_fallback_scroll library). The script waits
 * for DOMContentLoaded when needed so the form markup exists before querying.
 */
(function () {
  'use strict';

  /**
   * Parsed --drupal-displace-offset-top on the document root (px).
   *
   * @return {number}
   *   Non-negative offset in CSS pixels.
   */
  function getDrupalDisplaceOffsetTopPx() {
    var raw = window.getComputedStyle(document.documentElement)
      .getPropertyValue('--drupal-displace-offset-top')
      .trim();
    var n = parseFloat(raw);
    return !isNaN(n) && n >= 0 ? n : 0;
  }

  /**
   * Target distance (px) from viewport top for the notice's top edge.
   *
   * Prefer html scroll-padding-top when set (carlson_refresh matches
   * scroll-padding-top: calc(55px + var(--drupal-displace-offset-top, 0px))
   * and 110px on larger nav). Otherwise use that mobile-style sum as default.
   *
   * Optional override: set --carlson-recaptcha-v2-fallback-scroll-top on the
   * form (pixel length only).
   *
   * @param {HTMLElement} notice
   *   The notice container.
   *
   * @return {number}
   *   Pixel offset from top of viewport.
   */
  function getNoticeTopTargetPx(notice) {
    var form = notice.closest('form.carlson-recaptcha-v2-fallback');
    if (form) {
      var overrideRaw = window.getComputedStyle(form).getPropertyValue(
        '--carlson-recaptcha-v2-fallback-scroll-top'
      ).trim();
      if (overrideRaw !== '') {
        var overridden = parseFloat(overrideRaw);
        if (!isNaN(overridden) && overridden >= 0) {
          return overridden;
        }
      }
    }

    var scrollPad = window.getComputedStyle(document.documentElement)
      .scrollPaddingTop;
    var fromHtml = parseFloat(scrollPad);
    if (!isNaN(fromHtml) && fromHtml > 0) {
      return fromHtml;
    }

    return 55 + getDrupalDisplaceOffsetTopPx();
  }

  /**
   * Whether we should scroll so the notice sits near the top of the viewport.
   *
   * Any intersection used to skip scrolling, which left the notice mid-screen
   * on tall viewports. We scroll when off-screen or when the top edge sits
   * below the target band.
   *
   * @param {HTMLElement} notice
   *   The notice container.
   *
   * @return {boolean}
   *   TRUE when scroll is needed.
   */
  function needsScrollToPinNotice(notice) {
    var rect = notice.getBoundingClientRect();
    var vh = window.innerHeight || document.documentElement.clientHeight;
    var targetTop = getNoticeTopTargetPx(notice);
    if (rect.bottom < 0 || rect.top > vh) {
      return true;
    }
    return rect.top > targetTop;
  }

  /**
   * Pins the notice's top edge near the top of the viewport (see target px).
   *
   * @param {HTMLElement} notice
   *   The notice container.
   */
  function scrollNoticeIntoViewIfNeeded(notice) {
    if (!needsScrollToPinNotice(notice)) {
      return;
    }
    var rect = notice.getBoundingClientRect();
    var targetTop = getNoticeTopTargetPx(notice);
    var y = window.pageYOffset + rect.top - targetTop;
    window.scrollTo({
      top: Math.max(0, y),
      behavior: 'auto',
    });
  }

  /**
   * Scrolls each matching form's notice when needed.
   */
  function run() {
    var forms = document.querySelectorAll('form.carlson-recaptcha-v2-fallback');
    if (!forms.length) {
      return;
    }
    forms.forEach(function (form) {
      var notice = form.querySelector('.carlson-recaptcha-v2-fallback-notice');
      if (notice) {
        scrollNoticeIntoViewIfNeeded(notice);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  }
  else {
    run();
  }
})();
