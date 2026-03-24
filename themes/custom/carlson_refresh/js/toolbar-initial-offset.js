/**
 * @file
 * Keeps the page canvas aligned below Drupal's admin toolbar.
 *
 * Context:
 * This theme renders the sticky banner and header inside the normal page
 * canvas instead of Drupal's special page_top region. That fixes overlap with
 * the admin toolbar, but it also means the page canvas must stay in sync with
 * Drupal's toolbar displacement on first paint and after toolbar toggles.
 *
 * Flow:
 * 1. Listen for Drupal toolbar viewport offset changes.
 * 2. Wait for Drupal to finish its own DOM/displace updates.
 * 3. Measure the toolbar offset and the current page wrapper position.
 * 4. Add only the missing top padding needed to keep the page below the
 *    toolbar.
 * 5. Update scroll padding so in-page jumps land below the toolbar.
 */
(function ($, Drupal) {
  'use strict';

  function scheduleSync() {
    // Wait until Drupal finishes its own toolbar/displace DOM updates before
    // measuring positions and applying any corrective padding.
    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(syncToolbarOffset);
    });
  }

  function syncToolbarOffset() {
    const body = document.body;
    const toolbarBar = document.getElementById('toolbar-bar');
    const wrapper = document.querySelector('.dialog-off-canvas-main-canvas');

    if (
      !body ||
      !body.classList.contains('toolbar-fixed') ||
      !toolbarBar ||
      !wrapper
    ) {
      return;
    }

    // Prefer Drupal's computed displace offset. If it is not available yet,
    // approximate it from the toolbar bar plus the active horizontal tray.
    let desiredOffset = Math.round(
      typeof Drupal.displace === 'function'
        ? Drupal.displace.offsets.top
        : parseFloat(
            getComputedStyle(document.documentElement).getPropertyValue(
              '--drupal-displace-offset-top',
            ),
          ) || 0,
    );
    if (!desiredOffset) {
      const activeTray = document.querySelector('.is-active.toolbar-tray-horizontal');
      desiredOffset = Math.round(
        toolbarBar.getBoundingClientRect().height +
          (activeTray ? activeTray.getBoundingClientRect().height : 0),
      );
    }
    const currentWrapperTop = Math.round(wrapper.getBoundingClientRect().top);

    if (
      !body.dataset.carlsonToolbarOffsetSynced &&
      !body.getAttribute('style') &&
      currentWrapperTop === desiredOffset
    ) {
      // On the initial good state, keep Drupal/CSS as-is and avoid writing an
      // inline body padding that could cause double offsets later.
      return;
    }

    // Only add the portion of the toolbar offset that the page wrapper is not
    // already accounting for.
    const missingOffset = Math.max(0, desiredOffset - currentWrapperTop);

    body.style.paddingTop = `${missingOffset}px`;
    document.documentElement.style.scrollPaddingTop = `${desiredOffset}px`;
    body.dataset.carlsonToolbarOffsetSynced = 'true';
  }

  Drupal.behaviors.carlsonToolbarInitialOffset = {
    attach(context) {
      if (context !== document || this.initialized) {
        return;
      }

      this.initialized = true;
      // Re-sync after admin toolbar collapse/expand events change the top
      // displacement during the session.
      $(document).on(
        'drupalViewportOffsetChange.carlsonToolbarInitialOffset',
        scheduleSync,
      );
    },
  };
})(jQuery, Drupal);
