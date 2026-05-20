(function (Drupal, once) {
  'use strict';

  /**
   * Mental model:
   * 1. The initial desktop nav renders only the top-level mega menu toggles.
   * 2. Each top-level item carries an empty placeholder with a same-origin
   *    endpoint for its simple_megamenu entity.
   * 3. The first hover, focus, or click fetches the panel HTML and inserts it.
   * 4. Fetched HTML is cached in memory for the current page view, so reopening
   *    the same menu does not repeat the request.
   * 5. After insertion, Drupal behaviors are attached inside the panel and the
   *    active trail is restored client-side for links that match this page.
   */
  const responses = new Map();

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

  function insertPanel(panel, html) {
    // The HTML is rendered by Drupal from a view-access-checked entity route.
    panel.innerHTML = html;
    panel.setAttribute('data-carlson-mega-menu-loaded', 'true');
    panel.removeAttribute('aria-busy');

    markActiveLinks(panel);
    Drupal.attachBehaviors(panel);
  }

  function loadPanel(panel) {
    if (panel.getAttribute('data-carlson-mega-menu-loaded') === 'true') {
      return Promise.resolve(panel);
    }

    const url = panel.getAttribute('data-carlson-mega-menu-url');
    if (!url) {
      return Promise.reject(new Error('Megamenu panel endpoint is missing.'));
    }

    panel.setAttribute('aria-busy', 'true');

    // Store the loaded state on the placeholder, while requestPanel() stores
    // shared response text by URL to deduplicate simultaneous interactions.
    return requestPanel(url)
      .then((html) => {
        insertPanel(panel, html);
        return panel;
      })
      .catch((error) => {
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

  Drupal.behaviors.carlsonDesktopMegaMenuAjax = {
    attach(context) {
      // Bind once per placeholder. The handlers live on the stable parent item
      // so the panel can remain empty until the first meaningful interaction.
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
    },
  };

})(Drupal, once);
