(function (Drupal, once) {
  'use strict';

  const responses = new Map();

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
    const cached = responses.get(url);
    if (typeof cached === 'string') {
      return Promise.resolve(cached);
    }
    if (cached) {
      return cached;
    }

    const request = fetch(url, {
      credentials: 'same-origin',
      headers: {
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
        responses.set(url, html);
        return html;
      })
      .catch((error) => {
        responses.delete(url);
        throw error;
      });

    responses.set(url, request);
    return request;
  }

  function insertPanel(panel, html) {
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
