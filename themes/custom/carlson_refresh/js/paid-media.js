/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
  "use strict";

  Drupal.behaviors.paid_media = {
    attach: function () {
      // run test on initial page load
      checkStickySize();

      // run test on resize of the window
      $(window).resize(checkStickySize);

      //Function to the css rule
      function checkStickySize() {
        var wrap_width = $("html").width();
        if (wrap_width >= 992) {
          $(".paid-media__webform--wrapper").sticky({
            topSpacing: 170,
            bottomSpacing: 470,
          });
        } else {
          $(".paid-media__webform--wrapper").unstick();
        }
      }

    },
  };
})(jQuery, Drupal);
