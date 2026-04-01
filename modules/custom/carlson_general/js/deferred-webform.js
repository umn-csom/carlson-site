((Drupal, once) => {
  Drupal.behaviors.carlsonGeneralDeferredWebform = {
    attach(context) {
      once('carlsonGeneralDeferredWebform', '[data-deferred-webform-url]', context)
        .forEach((element) => {
          const url = element.dataset.deferredWebformUrl;
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
              element.textContent =
                Drupal.t('Unable to load the request information form. Please refresh the page and try again.');
            });
          }
        });
    },
  };
})(Drupal, once);
