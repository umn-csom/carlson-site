/**
 * @file
 * Scrolls the v2 fallback notice into view without waiting for Drupal.
 *
 * Loaded from the page head (see v2_fallback_scroll library). Runs at
 * DOMContentLoaded, then again on window load and when html style changes so
 * toolbar displace() can set --drupal-displace-offset-top before we measure.
 */
(function () {
  'use strict';

  /** @type {number|undefined} */
  var debounceTimer;

  /**
   * Parsed --drupal-displace-offset-top (px); prefers inline style from
   * displace.js, then computed cascade.
   *
   * @return {number}
   *   Non-negative offset in CSS pixels.
   */
  function getDrupalDisplaceOffsetTopPx() {
    var inline = document.documentElement.style
      .getPropertyValue('--drupal-displace-offset-top')
      .trim();
    var fromInline = parseFloat(inline);
    if (!isNaN(fromInline) && fromInline >= 0) {
      return fromInline;
    }
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
   * Whether we should scroll so the notice aligns to the target offset.
   *
   * @param {HTMLElement} notice
   *   The notice container.
   * @param {number} targetTop
   *   Desired getBoundingClientRect().top for the notice.
   *
   * @return {boolean}
   *   TRUE when scroll is needed.
   */
  function needsScrollToPinNotice(notice, targetTop) {
    var rect = notice.getBoundingClientRect();
    var vh = window.innerHeight || document.documentElement.clientHeight;
    var tolerance = 10;
    if (rect.bottom < 0 || rect.top > vh) {
      return true;
    }
    return Math.abs(rect.top - targetTop) > tolerance;
  }

  /**
   * Pins the notice's top edge near the top of the viewport (see target px).
   *
   * @param {HTMLElement} notice
   *   The notice container.
   */
  function scrollNoticeIntoViewIfNeeded(notice) {
    var targetTop = getNoticeTopTargetPx(notice);
    if (!needsScrollToPinNotice(notice, targetTop)) {
      return;
    }
    var rect = notice.getBoundingClientRect();
    var y = window.pageYOffset + rect.top - targetTop;
    window.scrollTo({
      top: Math.max(0, y),
      behavior: 'auto',
    });
  }

  /**
   * Scrolls each matching form's notice when needed.
   */
  function alignFallbackNotices() {
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

  /**
   * Debounced align after displace mutates the document root style attribute.
   */
  function scheduleAlignFallbackNotices() {
    if (debounceTimer) {
      window.clearTimeout(debounceTimer);
    }
    debounceTimer = window.setTimeout(function () {
      debounceTimer = undefined;
      alignFallbackNotices();
    }, 50);
  }

  /**
   * Re-runs alignment when toolbar/displace updates --drupal-displace-*.
   */
  function watchDocumentRootStyle() {
    try {
      var obs = new MutationObserver(function () {
        scheduleAlignFallbackNotices();
      });
      obs.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['style'],
      });
    }
    catch (e) {
      // No MutationObserver (very old browsers): rely on load only.
    }
  }

  function start() {
    alignFallbackNotices();
    window.addEventListener('load', alignFallbackNotices);
    watchDocumentRootStyle();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  }
  else {
    start();
  }
})();
