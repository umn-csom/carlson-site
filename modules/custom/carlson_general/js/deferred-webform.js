/**
 * Loads deferred Webforms into cacheable page shells.
 *
 * The initial HTML contains a lightweight placeholder instead of the live
 * Webform markup. This behavior fetches the real form from a dedicated Drupal
 * route after page load so the page can stay cacheable while the form remains
 * request-specific.
 */
((Drupal, once) => {
  Drupal.behaviors.carlsonGeneralDeferredWebform = {
    attach(context) {
      // Attach once per placeholder so AJAX behaviors and reattachments do not
      // issue duplicate form requests.
      once('carlsonGeneralDeferredWebform', '[data-deferred-webform-url]', context)
        .forEach((element) => {
          const url = element.dataset.deferredWebformUrl;
          const fallbackUrl = element.dataset.deferredWebformFallbackUrl;
          if (!url) {
            return;
          }

          // Use Drupal.ajax because the endpoint returns standard Drupal AJAX
          // commands that replace the placeholder with the rendered Webform.
          const ajax = Drupal.ajax({
            base: element.id || 'carlson-deferred-webform',
            url,
            event: null,
            keypress: false,
            progress: false,
            httpMethod: 'GET',
          });

          const request = ajax.execute();
          if (request && typeof request.fail === 'function') {
            request.fail(() => {
              element.classList.remove('carlson-deferred-webform--loading');

              // Leave a plain error message behind and link to the standalone
              // fallback route so the form is still reachable if the deferred
              // request fails.
              const message = document.createElement('p');
              message.textContent = Drupal.t('Unable to load the request information form.');
              element.replaceChildren(message);

              if (fallbackUrl) {
                const fallback = document.createElement('p');
                const link = document.createElement('a');
                fallback.append(
                  document.createTextNode(Drupal.t('Open the form directly:')),
                  document.createTextNode(' '),
                );
                link.href = fallbackUrl;
                link.textContent = Drupal.t('Open form');
                fallback.append(link);
                element.append(fallback);
              }
            });
          }
        });
    },
  };
})(Drupal, once);
