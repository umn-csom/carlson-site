(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.carlsonGeneralDevDatabaseReset = {
    attach(context) {
      once(
        'carlson-general-dev-database-reset',
        '.dev-database-reset-table-selection',
        context,
      ).forEach((wrapper) => {
        const checkboxes = wrapper.querySelectorAll('input[type="checkbox"]');

        wrapper
          .querySelector('.dev-database-reset-select-all')
          ?.addEventListener('click', (event) => {
            event.preventDefault();
            checkboxes.forEach((checkbox) => {
              checkbox.checked = true;
            });
          });

        wrapper
          .querySelector('.dev-database-reset-clear-selection')
          ?.addEventListener('click', (event) => {
            event.preventDefault();
            checkboxes.forEach((checkbox) => {
              checkbox.checked = false;
            });
          });
      });
    },
  };
})(Drupal, once);
