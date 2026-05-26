(function (Drupal, drupalSettings, once) {
  'use strict';

  /**
   * Mental model:
   * 1. The initial desktop nav renders only the top-level mega menu toggles.
   * 2. A desktop-scoped preload hint starts one aggregate endpoint request as
   *    early as the browser can fetch it.
   * 3. This behavior also starts the aggregate request as soon as it runs on a
   *    desktop viewport, and again after resizing from mobile to desktop.
   * 4. The aggregate endpoint returns all simple_megamenu panel HTML in one
   *    cacheable response, shared across pages because active trail marking is
   *    restored client-side.
   * 5. The old per-panel endpoint remains as a fallback if the aggregate
   *    request fails or a panel is missing from the aggregate response.
   */
  const responses = new Map();
  const aggregate = {
    promise: null,
    panels: null,
    scheduled: false,
    breakpointBound: false,
  };

  function settings() {
    return drupalSettings.carlsonGeneral &&
      drupalSettings.carlsonGeneral.desktopMegaMenu
      ? drupalSettings.carlsonGeneral.desktopMegaMenu
      : {};
  }

  function endpointUrl(url) {
    try {
      const endpoint = new URL(url, window.location.origin);
      if (endpoint.origin !== window.location.origin) {
        return null;
      }

      return endpoint.toString();
    }
    catch (error) {
      return null;
    }
  }

  function aggregateEndpointUrl() {
    return settings().endpoint ? endpointUrl(settings().endpoint) : null;
  }

  function normalizedPath(pathname) {
    if (!pathname || pathname === '/') {
      return '/';
    }

    return pathname.replace(/\/+$/, '');
  }

  function linkPath(link) {
    try {
      const url = new URL(link.getAttribute('href'), window.location.origin);
      if (url.origin !== window.location.origin) {
        return null;
      }

      return normalizedPath(url.pathname);
    }
    catch (error) {
      return null;
    }
  }

  function markActiveLinks(root) {
    const current = normalizedPath(window.location.pathname);
    const links = root.querySelectorAll('a[href]');

    links.forEach((link) => {
      const path = linkPath(link);
      if (!path || path === '/') {
        return;
      }

      const isActive = path === current || current.startsWith(path + '/');
      if (!isActive) {
        return;
      }

      link.classList.add('active', 'in-active-trail');
      link.setAttribute('data-carlson-mega-menu-active', '');

      if (path === current) {
        link.setAttribute('aria-current', 'page');
      }

      const item = link.closest('li');
      if (item) {
        item.classList.add('active', 'in-active-trail');
      }
    });
  }

  function isDesktopViewport() {
    const breakpoint = settings().breakpoint;
    if (breakpoint && 'matchMedia' in window) {
      try {
        return window.matchMedia(breakpoint).matches;
      }
      catch (error) {
        return true;
      }
    }

    return true;
  }

  function shouldPreloadAggregate() {
    return settings().preload !== false &&
      !!aggregateEndpointUrl() &&
      isDesktopViewport();
  }

  function requestPanel(url) {
    const endpoint = endpointUrl(url);
    if (!endpoint) {
      return Promise.reject(new Error('Megamenu panel endpoint is invalid.'));
    }

    const cached = responses.get(endpoint);
    if (typeof cached === 'string') {
      return Promise.resolve(cached);
    }
    if (cached) {
      return cached;
    }

    const request = fetch(endpoint, {
      credentials: 'same-origin',
      headers: {
        Accept: 'text/html',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Unable to load megamenu panel.');
        }

        return response.text();
      })
      .then((html) => {
        responses.set(endpoint, html);
        return html;
      })
      .catch((error) => {
        responses.delete(endpoint);
        throw error;
      });

    responses.set(endpoint, request);
    return request;
  }

  function requestAggregatePanels() {
    if (aggregate.panels) {
      return Promise.resolve(aggregate.panels);
    }
    if (aggregate.promise) {
      return aggregate.promise;
    }

    const endpoint = aggregateEndpointUrl();
    if (!endpoint) {
      return Promise.reject(
        new Error('Megamenu aggregate endpoint is missing.')
      );
    }

    aggregate.promise = fetch(endpoint, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Unable to load megamenu panels.');
        }

        return response.json();
      })
      .then((payload) => {
        aggregate.panels = payload.panels || {};
        return aggregate.panels;
      })
      .catch((error) => {
        aggregate.promise = null;
        aggregate.panels = null;
        throw error;
      });

    return aggregate.promise;
  }

  function insertPanel(panel, html) {
    if (panel.getAttribute('data-carlson-mega-menu-loaded') === 'true') {
      return;
    }

    // The HTML is rendered by Drupal from a view-access-checked entity route.
    panel.innerHTML = html;
    panel.setAttribute('data-carlson-mega-menu-loaded', 'true');
    panel.removeAttribute('aria-busy');

    markActiveLinks(panel);
    Drupal.attachBehaviors(panel);
  }

  function insertKnownPanels(context) {
    if (!aggregate.panels) {
      return;
    }

    const root = context || document;
    root.querySelectorAll('[data-carlson-mega-menu-panel]').forEach((panel) => {
      const id = panel.getAttribute('data-carlson-mega-menu-id');
      if (!id || !Object.prototype.hasOwnProperty.call(aggregate.panels, id)) {
        return;
      }

      insertPanel(panel, aggregate.panels[id]);
    });
  }

  function loadPanelFallback(panel) {
    const url = panel.getAttribute('data-carlson-mega-menu-url');
    if (!url) {
      return Promise.reject(new Error('Megamenu panel endpoint is missing.'));
    }

    return requestPanel(url).then((html) => {
      insertPanel(panel, html);
      return panel;
    });
  }

  function loadPanel(panel) {
    if (panel.getAttribute('data-carlson-mega-menu-loaded') === 'true') {
      return Promise.resolve(panel);
    }

    panel.setAttribute('aria-busy', 'true');

    if (aggregateEndpointUrl() && isDesktopViewport()) {
      return requestAggregatePanels()
        .then((panels) => {
          const id = panel.getAttribute('data-carlson-mega-menu-id');
          if (id && Object.prototype.hasOwnProperty.call(panels, id)) {
            insertPanel(panel, panels[id]);
            return panel;
          }

          return loadPanelFallback(panel);
        })
        .catch(() => loadPanelFallback(panel))
        .catch((error) => {
          panel.removeAttribute('aria-busy');
          panel.setAttribute('data-carlson-mega-menu-error', 'true');
          throw error;
        });
    }

    return loadPanelFallback(panel).catch((error) => {
      panel.removeAttribute('aria-busy');
      panel.setAttribute('data-carlson-mega-menu-error', 'true');
      throw error;
    });
  }

  function loadPanelFromEvent(event) {
    const container = event.currentTarget.closest('.dropdown--mega-menu');
    if (!container) {
      return;
    }

    const panel = container.querySelector('[data-carlson-mega-menu-panel]');
    if (!panel) {
      return;
    }

    loadPanel(panel).catch((error) => {
      console.error(error);
    });
  }

  function preloadAggregatePanels() {
    if (
      aggregate.scheduled ||
      aggregate.panels ||
      aggregate.promise ||
      !shouldPreloadAggregate()
    ) {
      return;
    }

    aggregate.scheduled = true;
    requestAggregatePanels()
      .then(() => {
        insertKnownPanels(document);
      })
      .catch((error) => {
        aggregate.scheduled = false;
        console.error(error);
      });
  }

  function bindBreakpointPreload() {
    if (aggregate.breakpointBound) {
      return;
    }

    aggregate.breakpointBound = true;
    const breakpoint = settings().breakpoint;
    if (!breakpoint || !('matchMedia' in window)) {
      return;
    }

    try {
      const query = window.matchMedia(breakpoint);
      const onChange = (event) => {
        if (event.matches) {
          preloadAggregatePanels();
        }
      };

      if (typeof query.addEventListener === 'function') {
        query.addEventListener('change', onChange);
      }
      else if (typeof query.addListener === 'function') {
        query.addListener(onChange);
      }
    }
    catch (error) {
      // Invalid breakpoint syntax should not break menu interaction fallback.
    }
  }

  Drupal.behaviors.carlsonDesktopMegaMenuAjax = {
    attach(context) {
      // Bind once per placeholder. The handlers live on the stable parent item
      // so the panel can still load on interaction if preloading fails.
      once(
        'carlson-desktop-megamenu-ajax',
        '[data-carlson-mega-menu-panel]',
        context
      ).forEach((panel) => {
        const container = panel.closest('.dropdown--mega-menu');
        const toggle = container
          ? container.querySelector('[data-toggle="dropdown"]')
          : null;

        if (container) {
          container.addEventListener('mouseenter', loadPanelFromEvent);
          container.addEventListener('focusin', loadPanelFromEvent);
        }

        if (toggle) {
          toggle.addEventListener('click', loadPanelFromEvent, true);
        }
      });

      insertKnownPanels(context);
      bindBreakpointPreload();
      preloadAggregatePanels();
    },
  };

  bindBreakpointPreload();
  preloadAggregatePanels();

})(Drupal, drupalSettings, once);
