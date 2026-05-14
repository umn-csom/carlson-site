/**
 * @file
 * Sets inert on non–CAPTCHA field wrappers when v3 falls back to v2, and
 * scrolls the top-of-form fallback notice into view after full-page reload
 * when it sits below the fold (non-AJAX submit leaves the window at the top).
 */

(function (Drupal, once) {
  'use strict';

  var inactiveClass = 'carlson-recaptcha-v2-fallback__inactive';

  /**
   * Whether this element sits inside another .js-form-item in the same form.
   *
   * Skipping nested wrappers avoids stacking opacity on composites (radios).
   *
   * @param {HTMLElement} item
   *   Candidate wrapper.
   * @param {HTMLElement} form
   *   The form root.
   *
   * @return {boolean}
   *   TRUE when a parent .js-form-item should own dimming instead.
   */
  function isNestedFormItem(item, form) {
    var parent = item.parentElement;
    if (!parent || !form.contains(parent)) {
      return false;
    }
    var ancestorItem = parent.closest('.js-form-item');
    return Boolean(
      ancestorItem && ancestorItem !== item && form.contains(ancestorItem)
    );
  }

  /**
   * Whether this wrapper should stay interactive.
   *
   * @param {HTMLElement} item
   *   A field wrapper element.
   *
   * @return {boolean}
   *   TRUE to skip inert.
   */
  function shouldExclude(item) {
    if (item.closest('.form-actions')) {
      return true;
    }
    if (item.classList.contains('form-type-captcha')) {
      return true;
    }
    if (item.querySelector('.captcha')) {
      return true;
    }
    if (item.querySelector('input[type="password"]')) {
      return true;
    }
    if (item.querySelector('input[type="file"]')) {
      return true;
    }
    return false;
  }

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
   * @param {HTMLElement} el
   *   The notice container.
   */
  function scrollNoticeIntoViewIfNeeded(el) {
    window.requestAnimationFrame(function () {
      if (isVerticallyInViewport(el)) {
        return;
      }
      var reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
      ).matches;
      el.scrollIntoView({
        behavior: reduceMotion ? 'auto' : 'smooth',
        block: 'center',
        inline: 'nearest',
      });
    });
  }

  /**
   * Whether the wrapper contains a visible form control.
   *
   * @param {HTMLElement} item
   *   A field wrapper element.
   *
   * @return {boolean}
   *   TRUE when disabling interaction is meaningful.
   */
  function hasVisibleControl(item) {
    /** @type {NodeListOf<Element>} */
    var controls = item.querySelectorAll('input, select, textarea');
    var found = false;
    controls.forEach(function (el) {
      if (found) {
        return;
      }
      var tag = el.tagName.toLowerCase();
      if (tag === 'input') {
        var type = (el.type || '').toLowerCase();
        if (
          type === 'hidden' ||
          type === 'submit' ||
          type === 'button' ||
          type === 'image'
        ) {
          return;
        }
      }
      found = true;
    });
    return found;
  }

  Drupal.behaviors.carlsonRecaptchaV2FallbackChrome = {
    attach(context) {
      once(
        'carlson-recaptcha-v2-fallback-chrome',
        'form.carlson-recaptcha-v2-fallback',
        context
      ).forEach(function (form) {
        form.querySelectorAll('.js-form-item').forEach(function (item) {
          if (isNestedFormItem(item, form)) {
            return;
          }
          if (!hasVisibleControl(item)) {
            return;
          }
          if (shouldExclude(item)) {
            return;
          }
          item.classList.add(inactiveClass);
          item.inert = true;
        });
        var notice = form.querySelector(
          '.carlson-recaptcha-v2-fallback-notice'
        );
        if (notice) {
          scrollNoticeIntoViewIfNeeded(notice);
        }
      });
    },
  };
})(Drupal, once);
