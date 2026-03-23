(function ($, Drupal) {
  'use strict';

  function scheduleSync() {
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
      return;
    }

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
      $(document).on(
        'drupalViewportOffsetChange.carlsonToolbarInitialOffset',
        scheduleSync,
      );
    },
  };
})(jQuery, Drupal);
