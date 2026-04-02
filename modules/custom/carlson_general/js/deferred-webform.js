((Drupal, once) => {
  Drupal.behaviors.carlsonGeneralDeferredWebform = {
    attach(context) {
      once('carlsonGeneralDeferredWebform', '[data-deferred-webform-url]', context)
        .forEach((element) => {
          const url = element.dataset.deferredWebformUrl;
          const fallbackUrl = element.dataset.deferredWebformFallbackUrl;
          if (!url) {
            return;
          }

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
