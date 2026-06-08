(function (Drupal, drupalSettings, once) {
  'use strict';

  /*
   * Mental model:
   * 1. PHP renders only a lightweight placeholder on the initial page load.
   * 2. This behavior prefetches the off-canvas menu when the browser is idle.
   * 3. If the user clicks first, the same one-time loader fetches the menu.
   * 4. The fetched markup replaces the placeholder, Drupal behaviors initialize
   *    mmenu, and later clicks use the initialized mmenu API directly.
   */
  const state = {
    menuReady: false,
    promise: null,
    prefetchScheduled: false,
  };
  const toggleSelector = '#toggle-icon, .responsive-menu-toggle-icon, ' +
    '#navbar-main .navbar-toggler';

  function settings() {
    return drupalSettings.carlsonGeneral &&
      drupalSettings.carlsonGeneral.responsiveOffCanvas
      ? drupalSettings.carlsonGeneral.responsiveOffCanvas
      : {};
  }

  function currentPath() {
    return window.location.pathname;
  }

  function endpointUrl() {
    const endpoint = settings().endpoint;
    if (!endpoint) {
      return null;
    }

    const separator = endpoint.indexOf('?') === -1 ? '?' : '&';
    return endpoint + separator + 'current_path=' +
      encodeURIComponent(currentPath());
  }

  // The mmenu plugin attaches its API directly to the #off-canvas element.
  function currentOffCanvas() {
    return document.querySelector('#off-canvas');
  }

  function setToggleExpanded(expanded) {
    const value = expanded ? 'true' : 'false';
    document.querySelectorAll(toggleSelector).forEach((toggle) => {
      toggle.setAttribute('aria-expanded', value);
    });
  }

  // The toggle starts hidden/disabled so early clicks cannot race the loader.
  function markToggleReady(toggle) {
    toggle.setAttribute('aria-controls', 'off-canvas');
    if (!toggle.hasAttribute('aria-expanded')) {
      toggle.setAttribute('aria-expanded', 'false');
    }
    toggle.removeAttribute('hidden');
    toggle.removeAttribute('aria-disabled');
    toggle.removeAttribute('tabindex');
    toggle.removeAttribute('data-carlson-responsive-off-canvas-pending');
    toggle.style.removeProperty('display');
    toggle.style.removeProperty('opacity');
    toggle.style.removeProperty('pointer-events');
    toggle.style.removeProperty('visibility');
  }

  function markTogglesReady() {
    document.querySelectorAll(toggleSelector).forEach(markToggleReady);
  }

  // Re-run the site's existing responsive menu tweaks after AJAX insertion.
  function attachThemeOffCanvasTweaks() {
    const tweakBehavior = Drupal.behaviors.responsiveMenuTweak;
    if (tweakBehavior && typeof tweakBehavior.attach === 'function') {
      tweakBehavior.attach(document, drupalSettings);
    }
  }

  // Keep aria-expanded synced when mmenu opens or closes through any control.
  function bindOffCanvasState(offCanvas) {
    if (
      !offCanvas ||
      !offCanvas.mmApi ||
      offCanvas.dataset.carlsonOffCanvasAriaBound
    ) {
      return;
    }

    offCanvas.dataset.carlsonOffCanvasAriaBound = 'true';
    if (typeof offCanvas.mmApi.bind === 'function') {
      offCanvas.mmApi.bind('open:start', () => setToggleExpanded(true));
      offCanvas.mmApi.bind('close:start', () => setToggleExpanded(false));
    }
    setToggleExpanded(offCanvas.classList.contains('mm-menu_opened'));
  }

  function openOffCanvas(offCanvas) {
    if (offCanvas && offCanvas.mmApi) {
      offCanvas.mmApi.open();
      setToggleExpanded(true);
    }
  }

  function toggleOffCanvas(offCanvas) {
    if (offCanvas && offCanvas.mmApi) {
      const method = offCanvas.classList.contains('mm-menu_opened')
        ? 'close'
        : 'open';
      offCanvas.mmApi[method]();
      setToggleExpanded(method === 'open');
    }
  }

  // Match the existing theme behavior that removes main content from tab order.
  function setMainTabIndex(value) {
    const featured = document.querySelector('section#featured');
    const main = document.querySelector('main');
    if (featured) {
      featured.setAttribute('tabindex', value);
    }
    if (main) {
      main.setAttribute('tabindex', value);
    }
  }

  // Convert the endpoint HTML into DOM, replace the placeholder, and let
  // Drupal/mmenu initialize the new menu subtree.
  function insertMenu(html) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();
    const wrapper = template.content.firstElementChild;
    if (!wrapper) {
      throw new Error('Off-canvas menu response was empty.');
    }

    const placeholder = document.querySelector(
      '[data-carlson-responsive-off-canvas-placeholder]'
    );
    if (placeholder) {
      placeholder.replaceWith(wrapper);
    }
    else {
      document.body.appendChild(wrapper);
    }

    Drupal.attachBehaviors(wrapper);
    attachThemeOffCanvasTweaks();

    return currentOffCanvas();
  }

  // Shared loader for idle prefetch and click-to-open. The stored promise
  // deduplicates concurrent requests so the menu is fetched only once.
  function loadMenu() {
    const existing = currentOffCanvas();
    if (existing && existing.mmApi) {
      state.menuReady = true;
      markTogglesReady();
      bindOffCanvasState(existing);
      return Promise.resolve(existing);
    }

    if (state.promise) {
      return state.promise;
    }

    const url = endpointUrl();
    if (!url) {
      return Promise.reject(new Error('Off-canvas endpoint is not configured.'));
    }

    state.promise = fetch(url, {
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Unable to load off-canvas menu.');
        }
        return response.text();
      })
      .then(insertMenu)
      .then((offCanvas) => {
        if (offCanvas && offCanvas.mmApi) {
          state.menuReady = true;
          markTogglesReady();
          bindOffCanvasState(offCanvas);
          return offCanvas;
        }

        throw new Error('Off-canvas menu did not initialize.');
      })
      .catch((error) => {
        state.promise = null;
        throw error;
      });

    return state.promise;
  }

  // Capture toggle clicks before the contrib listener. If the menu is already
  // initialized, use mmenu directly; otherwise load it and open it afterward.
  function handleToggleClick(event) {
    const offCanvas = currentOffCanvas();
    if (offCanvas && offCanvas.mmApi) {
      event.preventDefault();
      event.stopImmediatePropagation();
      setMainTabIndex('-1');
      toggleOffCanvas(offCanvas);
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    setMainTabIndex('-1');

    loadMenu()
      .then(openOffCanvas)
      .catch((error) => {
        // Keep the click non-fatal if the endpoint is temporarily unavailable.
        console.error(error);
      });
  }

  // Prefetch only when configured and only for viewports that use off-canvas.
  function shouldPrefetch() {
    if (!settings().prefetch) {
      return false;
    }

    const breakpoint = drupalSettings.responsive_menu &&
      drupalSettings.responsive_menu.breakpoint;
    if (breakpoint && 'matchMedia' in window) {
      try {
        if (window.matchMedia(breakpoint).matches) {
          return false;
        }
      }
      catch {
        return true;
      }
    }

    return true;
  }

  // Defer the fetch until idle time when supported, otherwise start it as soon
  // as the current task clears so Safari/iOS does not hold the mobile button.
  // If prefetch is disabled or fails, the toggle is still made usable and the
  // click handler can load the menu on demand.
  function schedulePrefetch() {
    if (state.menuReady) {
      markTogglesReady();
      return;
    }

    if (state.prefetchScheduled || !shouldPrefetch()) {
      if (!state.prefetchScheduled) {
        markTogglesReady();
      }
      return;
    }

    state.prefetchScheduled = true;
    const prefetch = () => loadMenu().catch(() => {
      state.prefetchScheduled = false;
      markTogglesReady();
    });

    if (typeof window.requestIdleCallback === 'function') {
      window.requestIdleCallback(prefetch, { timeout: 2000 });
    }
    else {
      window.setTimeout(prefetch, 0);
    }
  }

  Drupal.behaviors.carlsonResponsiveOffCanvasAjax = {
    attach(context) {
      // once() keeps the capture listener from being duplicated after AJAX.
      once(
        'carlson-responsive-off-canvas-ajax',
        toggleSelector,
        context
      ).forEach((toggle) => {
        toggle.addEventListener('click', handleToggleClick, true);
      });

      schedulePrefetch();
    },
  };

})(Drupal, drupalSettings, once);
