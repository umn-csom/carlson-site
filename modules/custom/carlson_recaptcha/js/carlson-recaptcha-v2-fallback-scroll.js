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
   * Whether any part of the element intersects the viewport vertically.
   *
   * @param {HTMLElement} el
   *   Element to test.
   *
   * @return {boolean}
   *   TRUE when the element overlaps the visible viewport height.
   */
  function isVerticallyInViewport(el) {
    var rect = el.getBoundingClientRect();
    var vh = window.innerHeight || document.documentElement.clientHeight;
    return rect.top < vh && rect.bottom > 0;
  }

  /**
   * Scrolls the fallback notice into view if it is off-screen vertically.
   *
   * @param {HTMLElement} notice
   *   The notice container.
   */
  function scrollNoticeIntoViewIfNeeded(notice) {
    if (isVerticallyInViewport(notice)) {
      return;
    }
    notice.scrollIntoView({
      behavior: 'auto',
      block: 'center',
      inline: 'nearest',
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
