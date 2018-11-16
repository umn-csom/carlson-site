/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
  'use strict';

  $(".card-flip").toggleClass("flip");

  $('.node__bottom').each(function () {
    if ($(this).find('.node__bottom__col:empty').length > 0){
      $(this).find('.node__bottom__col:not(:empty)').addClass('node__bottom__col--single')
    }
  });

  Drupal.behaviors.bootstrap_barrio_subtheme = {
    attach: function (context, settings) {
      // run test on initial page load
      checkStickySize();

      // run test on resize of the window
      $(window).resize(checkStickySize);

      //Function to the css rule
      function checkStickySize() {
        var wrap_width = $('html').width();
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
