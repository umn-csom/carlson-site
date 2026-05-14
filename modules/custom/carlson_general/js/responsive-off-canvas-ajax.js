(function (Drupal, drupalSettings, once) {
  'use strict';

  const state = {
    promise: null,
  };

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

  function currentOffCanvas() {
    return document.querySelector('#off-canvas');
  }

  function openOffCanvas(offCanvas) {
    if (offCanvas && offCanvas.mmApi) {
      offCanvas.mmApi.open();
    }
  }

  function toggleOffCanvas(offCanvas) {
    if (offCanvas && offCanvas.mmApi) {
      const method = offCanvas.classList.contains('mm-menu_opened')
        ? 'close'
        : 'open';
      offCanvas.mmApi[method]();
    }
  }

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

    return currentOffCanvas();
  }

  function loadMenu() {
    const existing = currentOffCanvas();
    if (existing && existing.mmApi) {
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
      .catch((error) => {
        state.promise = null;
        throw error;
      });

    return state.promise;
  }

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

  Drupal.behaviors.carlsonResponsiveOffCanvasAjax = {
    attach(context) {
      once(
        'carlson-responsive-off-canvas-ajax',
        '#toggle-icon, .responsive-menu-toggle-icon, #navbar-main .navbar-toggler',
        context
      ).forEach((toggle) => {
        toggle.addEventListener('click', handleToggleClick, true);
      });

      if (settings().prefetch) {
        const prefetch = () => loadMenu().catch(() => {});
        if ('requestIdleCallback' in window) {
          window.requestIdleCallback(prefetch);
        }
        else {
          window.setTimeout(prefetch, 1500);
        }
      }
    },
  };

})(Drupal, drupalSettings, once);
