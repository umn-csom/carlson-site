/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.bootstrap_barrio_subtheme = {
    attach: function (context, settings) {
      // run test on initial page load
      checkStickySize();

      // run test on resize of the window
      $(window).resize(checkStickySize);

      //Function to the css rule
      function checkStickySize(){
          if ($(".sticky-sidebar").css("max-width") === "25%" ){
            // Check the initial Position of the fixed_nav_container
            var stickyHeaderTop = $('.sticky-sidebar__inner').offset().top;
            var wrap_width = $('.landing-page__content--inner').width();
            var sidebar_width = (wrap_width * .25) - 25;


            // $(window).scroll(function(){
            //   if( $(window).scrollTop() > stickyHeaderTop-109 ) {
            //     $('.sticky-sidebar__inner').css({position: 'fixed', top: '109px',width: sidebar_width});  
            //   } else {
            //     $('.sticky-sidebar__inner').css({position: 'relative', top: '0px',width: 'inherit'});
            //   }
            // });

            // $(window).on('scroll', function () {
            //   if ($(window).scrollTop() >= 10) {
            //     $('.carlson-header, .umnhf-campus-tc').addClass('compressed');
            //   } else {
            //     $('.carlson-header, .umnhf-campus-tc').removeClass('compressed');
            //   }
            // });
          }else {
            $('.sticky-sidebar__inner').css({position: 'relative', top: '0px',width: 'inherit'});
          }
      }

      document.querySelector(".card-flip").classList.toggle("flip");

    }
  }
})(jQuery, Drupal);
