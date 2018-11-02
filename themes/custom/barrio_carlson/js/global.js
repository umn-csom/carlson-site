/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
  'use strict';

  $(".card-flip").toggleClass("flip");

  Drupal.behaviors.bootstrap_barrio_subtheme = {
    attach: function (context, settings) {
      // run test on initial page load
      checkStickySize();

      // run test on resize of the window
      $(window).resize(checkStickySize);

      //Function to the css rule
      function checkStickySize() {
        var wrap_width = $('html').width();
        console.log('recalculating for '+wrap_width);
        if (wrap_width >= 992) {
          $('.paid-media__webform--wrapper').sticky({
            topSpacing: 170,
            bottomSpacing: 470
          });
        } else {
          $('.paid-media__webform--wrapper').unstick();
        }
      }

    }
  }
})(jQuery, Drupal);
