/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
    'use strict';

  Drupal.behaviors.bootstrap_barrio_subtheme = {
    attach: function (context, settings) {

      $(document).on('click', '.sticky-menu__label', function(e) {
        e.preventDefault();
        var $this = $(this);
        $this.toggleClass('sticky-menu__label--opened');
        $('.sticky-menu__nav').toggleClass('sticky-menu__nav--closed');
      });
    
      $(document).on('click', '.sticky-menu__item--has-submenu', function(e) {
        e.preventDefault();
        var $this = $(this);
        $this.toggleClass('sticky-menu__item--opened');
        $this.find('ul.subnav').toggleClass('subnav--opened');
      });

      // run test on initial page load
      checkStickySize();

      // run test on resize of the window
      $(window).resize(checkStickySize);

      //Function to the css rule
      function checkStickySize() {
        var wrap_width = $('html').width();
        if (wrap_width >= 992) {
          $('.sticky-menu').sticky({
            topSpacing: 170,
            bottomSpacing: 470
          });
        } else {
          $('.sticky-menu').unstick();
        }
      }
    }
  }
  
  })(jQuery, Drupal);  