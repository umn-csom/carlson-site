/**
 * @file
 * Ensures reCAPTCHA v3's response textarea gets an accessible label.
 *
 * Google injects the hidden textarea after the page has already initialized in
 * some cases, so a one-shot selector can miss the element. This behavior keeps
 * retrying for a short window and watches for DOM mutations so the label lands
 * as soon as the textarea appears.
 */

(function (Drupal, once) {
  'use strict';

  var RESPONSE_SELECTOR = '.g-recaptcha-response';
  var TOKEN_SELECTOR = '.recaptcha-v3-token';
  var RETRY_LIMIT = 20;
  var RETRY_DELAY_MS = 250;

  function applyAccessibilityFix(root) {
    var targetRoot = root || document;
    var responseElements = targetRoot.querySelectorAll(RESPONSE_SELECTOR);
    var updated = false;

    responseElements.forEach(function (element) {
      element.setAttribute('aria-hidden', 'true');
      element.setAttribute('aria-label', 'reCAPTCHA response');
      updated = true;
    });

    return updated;
  }

  function retryUntilReady(root, attemptsRemaining) {
    if (applyAccessibilityFix(root)) {
      return;
    }
    if (attemptsRemaining <= 0) {
      return;
    }
    window.setTimeout(function () {
      retryUntilReady(root, attemptsRemaining - 1);
    }, RETRY_DELAY_MS);
  }

  function observeForInjection(root) {
    var observer = new MutationObserver(function () {
      if (applyAccessibilityFix(root)) {
        observer.disconnect();
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });

    window.setTimeout(function () {
      observer.disconnect();
    }, RETRY_LIMIT * RETRY_DELAY_MS);
  }

  Drupal.behaviors.carlsonRecaptchaV3AccessibilityFix = {
    attach(context) {
      once('carlson-recaptcha-v3-accessibility-fix', TOKEN_SELECTOR, context)
        .forEach(function (tokenElement) {
          var root = tokenElement.closest('form') || document;

          retryUntilReady(root, RETRY_LIMIT);
          observeForInjection(root);
        });
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      Drupal.behaviors.carlsonRecaptchaV3AccessibilityFix.attach(document);
    });
  }
  else {
    Drupal.behaviors.carlsonRecaptchaV3AccessibilityFix.attach(document);
  }
})(Drupal, once);
