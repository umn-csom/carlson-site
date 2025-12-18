/**
 * @file
 * Native <dialog>-based modal replacement for legacy Colorbox links.
 */

(function (Drupal, once) {
  'use strict';

  // Stable ID for the single shared dialog instance (prevents duplicates when
  // Drupal behaviors re-attach via AJAX/BigPipe).
  const DIALOG_ID = 'csm-dialog-modal';

  /**
   * Feature-detect native <dialog> support.
   *
   * We only want to run the replacement behavior when the browser
   * supports `dialog.showModal()`. If unsupported, we gracefully fall back to
   * normal link navigation instead of partially broken modals.
   */
  function supportsDialog() {
    return typeof HTMLDialogElement !== 'undefined' && typeof HTMLDialogElement.prototype.showModal === 'function';
  }

  /**
   * Coerce common legacy query param values into a boolean.
   *
   * Existing Colorbox-style links often use `iframe=true` / `iframe=1`
   * semantics; URLSearchParams returns strings, so we normalize.
   */
  function toBool(value) {
    return value === true || value === 'true' || value === '1' || value === 1;
  }

  /**
   * Parse legacy `?width=` / `?height=` query params.
   *
   * Some legacy Colorbox links store desired modal dimensions in the URL.
   * We map them to CSS variables used by the dialog styles.
   */
  function parseSizeFromUrl(url) {
    const width = parseInt(url.searchParams.get('width') || '', 10);
    const height = parseInt(url.searchParams.get('height') || '', 10);
    return {
      width: Number.isFinite(width) && width > 0 ? width : null,
      height: Number.isFinite(height) && height > 0 ? height : null,
    };
  }

  /**
   * Determine whether a link should open in an iframe modal.
   *
   * Colorbox was frequently used to open embedded videos and internal
   * "node/123" content in an iframe. Non-iframe links should behave normally.
   */
  function isLikelyIframe(triggerEl, url) {
    const iframeParam = url.searchParams.get('iframe');
    if (toBool(iframeParam)) return true;
    if (triggerEl.classList.contains('youtube-vid')) return true;
    if (triggerEl.classList.contains('colorbox-node')) return true;
    if (triggerEl.classList.contains('colorbox-load')) return true;

    const host = (url.hostname || '').toLowerCase();
    if (host.includes('youtube.com') || host.includes('youtu.be') || host.includes('vimeo.com')) return true;

    return false;
  }

  /**
   * Toggle background scroll lock while the dialog is open.
   *
   * Native <dialog> does not automatically prevent background scrolling.
   * We mimic typical modal behavior by applying an `overflow: hidden` class.
   */
  function setBodyScrollLocked(locked) {
    document.documentElement.classList.toggle('csm-dialog-modal--open', locked);
    document.body.classList.toggle('csm-dialog-modal--open', locked);
  }

  /**
   * Create the shared dialog element once (or return it if it already exists).
   *
   * Drupal behaviors can attach multiple times (AJAX/BigPipe). A single
   * reusable dialog avoids duplicate DOM, stacking issues, and duplicated
   * event handlers.
   */
  function buildDialogIfMissing() {
    let dialog = document.getElementById(DIALOG_ID);
    if (dialog) return dialog;

    dialog = document.createElement('dialog');
    dialog.id = DIALOG_ID;
    dialog.className = 'csm-dialog-modal';

    dialog.innerHTML = [
      '<div class="csm-dialog-modal__surface" role="document">',
      '  <button type="button" class="csm-dialog-modal__close" aria-label="Close"></button>',
      '  <div class="csm-dialog-modal__content" data-csm-dialog-content></div>',
      '</div>',
    ].join('\n');

    document.body.appendChild(dialog);
    return dialog;
  }

  /**
   * Populate the dialog with an iframe pointing at the requested URL.
   *
   * This is the closest drop-in replacement for Colorbox's iframe mode.
   * We also map legacy width/height to CSS variables for sizing.
   */
  function openDialogWithIframe(dialog, href, opts) {
    const content = dialog.querySelector('[data-csm-dialog-content]');
    if (!content) return;

    const iframe = document.createElement('iframe');
    iframe.className = 'csm-dialog-modal__iframe';
    iframe.src = href;
    iframe.loading = 'lazy';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    iframe.title = opts.title || 'Dialog content';

    if (opts.width) {
      dialog.style.setProperty('--csm-dialog-width', `${opts.width}px`);
    } else {
      dialog.style.removeProperty('--csm-dialog-width');
    }

    if (opts.height) {
      dialog.style.setProperty('--csm-dialog-height', `${opts.height}px`);
    } else {
      dialog.style.removeProperty('--csm-dialog-height');
    }

    content.replaceChildren(iframe);
  }

  /**
   * Populate the dialog with inline DOM content referenced by an anchor hash.
   *
   * Some legacy "Colorbox" links are really "open this hidden markup" via
   * `href="#some-id"`. We temporarily move the element into the dialog and
   * restore it when the dialog closes.
   */
  function openDialogWithInline(dialog, targetEl) {
    const content = dialog.querySelector('[data-csm-dialog-content]');
    if (!content) return;

    const placeholder = document.createComment('csm-dialog-inline-placeholder');
    const originalParent = targetEl.parentNode;
    if (!originalParent) return;

    const originalNextSibling = targetEl.nextSibling;
    const originalHidden = targetEl.hasAttribute('hidden');
    const originalDisplay = targetEl.style.display;

    originalParent.insertBefore(placeholder, targetEl);
    content.replaceChildren(targetEl);
    targetEl.removeAttribute('hidden');
    targetEl.style.display = '';

    dialog.__csmInlineRestore = function restoreInline() {
      if (originalNextSibling) {
        originalParent.insertBefore(targetEl, originalNextSibling);
      } else {
        originalParent.appendChild(targetEl);
      }
      placeholder.remove();

      if (originalHidden) {
        targetEl.setAttribute('hidden', 'hidden');
      } else {
        targetEl.removeAttribute('hidden');
      }
      targetEl.style.display = originalDisplay;
    };
  }

  /**
   * Open the dialog modally.
   *
   * Centralizes `showModal()` call and ensures the scroll lock is applied.
   */
  function openDialog(dialog) {
    if (!supportsDialog()) return false;
    if (dialog.open) return true;
    try {
      dialog.showModal();
      setBodyScrollLocked(true);
      return true;
    } catch (e) {
      return false;
    }
  }

  /**
   * Close the dialog.
   *
   * Keeps closing behavior consistent across close button, backdrop click,
   * and other callers.
   */
  function closeDialog(dialog) {
    try {
      if (dialog.open) dialog.close();
    } catch (e) {}
  }

  /**
   * Reset dialog state after it closes.
   *
   * Ensures subsequent opens start clean (no leftover iframe, sizing, or
   * moved inline content) and restores page scrolling.
   */
  function cleanupDialog(dialog) {
    setBodyScrollLocked(false);
    dialog.style.removeProperty('--csm-dialog-width');
    dialog.style.removeProperty('--csm-dialog-height');

    const content = dialog.querySelector('[data-csm-dialog-content]');
    if (content) content.replaceChildren();

    if (typeof dialog.__csmInlineRestore === 'function') {
      try {
        dialog.__csmInlineRestore();
      } catch (e) {}
    }
    dialog.__csmInlineRestore = null;
  }

  /**
   * Bind one-time shell event handlers to the shared dialog.
   *
   * We only want to wire close/backdrop behavior once, even if Drupal
   * behaviors re-run.
   */
  function bindDialogShell(dialog) {
    if (dialog.__csmBound) return;
    dialog.__csmBound = true;

    // Close button.
    const closeBtn = dialog.querySelector('.csm-dialog-modal__close');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => closeDialog(dialog));
    }

    // Click outside closes.
    dialog.addEventListener('click', (e) => {
      if (e.target === dialog) closeDialog(dialog);
    });

    dialog.addEventListener('close', () => cleanupDialog(dialog));
  }

  /**
   * Detect legacy Colorbox trigger links based on known classes.
   *
   * Existing content already has these classes in WYSIWYG HTML; we use
   * them as the signal to open a dialog instead of navigating.
   */
  function isLegacyColorboxTrigger(el) {
    if (!el || el.tagName !== 'A') return false;
    if (el.classList.contains('colorbox')) return true;
    if (el.classList.contains('colorbox-load')) return true;
    if (el.classList.contains('colorbox-node')) return true;
    if (el.classList.contains('cboxElement')) return true;
    return false;
  }

  /**
   * Choose a best-effort title for accessibility.
   *
   * The iframe needs a title, and we want a reasonable label derived from
   * the link (aria-label > title attribute > text).
   */
  function getTriggerTitle(el) {
    const aria = el.getAttribute('aria-label');
    if (aria) return aria;
    const title = el.getAttribute('title');
    if (title) return title;
    const text = (el.textContent || '').trim();
    return text || 'Dialog content';
  }

  /**
   * Handle a legacy trigger click and open the dialog.
   *
   * This is the "replacement" for Colorbox's click behavior.
   * - Inline `#id` links open the referenced DOM in the dialog.
   * - Video/iframe-ish links open an iframe in the dialog.
   * - Everything else is left alone to behave as a normal link.
   */
  function handleTriggerClick(e, dialog, triggerEl) {
    const hrefAttr = triggerEl.getAttribute('href') || '';
    if (!hrefAttr) return;

    // Let modified clicks behave normally.
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;

    // Inline content.
    if (hrefAttr.startsWith('#')) {
      const target = document.querySelector(hrefAttr);
      if (!target) return;
      e.preventDefault();
      bindDialogShell(dialog);
      openDialogWithInline(dialog, target);
      openDialog(dialog);
      return;
    }

    let url;
    try {
      let hrefForUrl = hrefAttr;
      const likelySiteRelative =
        (triggerEl.classList.contains('colorbox-node') || triggerEl.classList.contains('colorbox-load')) &&
        !hrefAttr.startsWith('/') &&
        !hrefAttr.startsWith('?') &&
        !hrefAttr.startsWith('#') &&
        !hrefAttr.startsWith('//') &&
        !/^https?:\/\//i.test(hrefAttr) &&
        !hrefAttr.startsWith('./') &&
        !hrefAttr.startsWith('../');

      if (likelySiteRelative) {
        hrefForUrl = `/${hrefAttr}`;
      }

      url = new URL(hrefForUrl, window.location.href);
    } catch (err) {
      return;
    }

    const title = getTriggerTitle(triggerEl);
    const size = parseSizeFromUrl(url);
    const openAsIframe = isLikelyIframe(triggerEl, url);
    if (!openAsIframe) {
      // For non-iframe links, allow normal navigation.
      return;
    }

    e.preventDefault();
    bindDialogShell(dialog);
    openDialogWithIframe(dialog, url.toString(), {
      title,
      width: size.width,
      height: size.height,
    });
    openDialog(dialog);
  }

  Drupal.behaviors.csmDialogModal = {
    attach: function (context) {
      if (!supportsDialog()) return;

      const dialog = buildDialogIfMissing();
      bindDialogShell(dialog);

      const triggers = Array.from(context.querySelectorAll('a'));
      triggers
        .filter(isLegacyColorboxTrigger)
        .forEach((triggerEl) => {
          once('csm-dialog-modal-trigger', triggerEl).forEach(() => {
            triggerEl.addEventListener('click', (e) => handleTriggerClick(e, dialog, triggerEl));
          });
        });
    },
  };
})(Drupal, once);
