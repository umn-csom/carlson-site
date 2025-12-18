/**
 * @file
 * Native <dialog>-based modal replacement for legacy Colorbox links.
 */

(function (Drupal, once) {
  'use strict';

  const DIALOG_ID = 'csm-dialog-modal';

  function supportsDialog() {
    return typeof HTMLDialogElement !== 'undefined' && typeof HTMLDialogElement.prototype.showModal === 'function';
  }

  function toBool(value) {
    return value === true || value === 'true' || value === '1' || value === 1;
  }

  function parseSizeFromUrl(url) {
    const width = parseInt(url.searchParams.get('width') || '', 10);
    const height = parseInt(url.searchParams.get('height') || '', 10);
    return {
      width: Number.isFinite(width) && width > 0 ? width : null,
      height: Number.isFinite(height) && height > 0 ? height : null,
    };
  }

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

  function setBodyScrollLocked(locked) {
    document.documentElement.classList.toggle('csm-dialog-modal--open', locked);
    document.body.classList.toggle('csm-dialog-modal--open', locked);
  }

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

  function closeDialog(dialog) {
    try {
      if (dialog.open) dialog.close();
    } catch (e) {}
  }

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

  function isLegacyColorboxTrigger(el) {
    if (!el || el.tagName !== 'A') return false;
    if (el.classList.contains('colorbox')) return true;
    if (el.classList.contains('colorbox-load')) return true;
    if (el.classList.contains('colorbox-node')) return true;
    if (el.classList.contains('cboxElement')) return true;
    return false;
  }

  function getTriggerTitle(el) {
    const aria = el.getAttribute('aria-label');
    if (aria) return aria;
    const title = el.getAttribute('title');
    if (title) return title;
    const text = (el.textContent || '').trim();
    return text || 'Dialog content';
  }

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
