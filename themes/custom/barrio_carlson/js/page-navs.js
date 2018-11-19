/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
    'use strict';

  Drupal.behaviors.bootstrap_barrio_subtheme_nav = {
    attach: function (context, settings) {

      // run test on initial page load
      checkStickySize();
      checkListeners();

      // run test on resize of the window
      $(window).resize(checkStickySize);
      $(window).resize(checkListeners);

      //Function to the css rule
      function checkStickySize() {
        var wrap_width = $('html').width();
        if (wrap_width >= 992) {
          $('.sticky-sidebar__inner').sticky({
            topSpacing: 170,
            bottomSpacing: 470
          });
        } else {
          $('.sticky-sidebar__inner').unstick();
        }
      }

      function checkListeners() {
        var wrap_width = $(window).width();
        if (wrap_width <= 992) {
          $('.sticky-menu__label').off('click');
          $('.sticky-menu__label').on('click', function(e) {
            e.preventDefault();
            var $this = $(this);
            $this.toggleClass('sticky-menu__label--opened');
            $('.sticky-menu__nav').toggleClass('sticky-menu__nav--closed');
          });
        } else {
          $('.sticky-menu__label').off('click');
        }

        $(document).on('click', '.sticky-menu__item--has-submenu > a', function(e) {
          e.preventDefault();
          var $this = $(this);
  
          $this.parent().toggleClass('sticky-menu__item--opened');
          $this.parent().children().last().toggleClass('subnav--opened');
  
          if( $this.parent().hasClass('subnav--opened') && $this.parent().parent().parent().hasClass('menu-level-1') ) {
            var $root = $this.parent().parent().parent();
            $root.children().last().children().last().toggleClass('subnav--opened');
            $root.children().last().toggleClass('sticky-menu__item--opened');
          }
        });
      }
    }
  }
  
  })(jQuery, Drupal);  