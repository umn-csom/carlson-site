/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
    'use strict';

  Drupal.behaviors.bootstrap_barrio_subtheme_centennial = {
    attach: function (context, settings) {

      // run test on initial page load
      stickyTimeline();
      highlightTimeline();

      // run test on resize of the window
      $(window).resize(stickyTimeline);
      $(window).scroll(highlightTimeline);

      //Function to the css rule
      function stickyTimeline() {
        var top = $('#centennial__timeline').scrollTop();
        var bottom = $('.footer').height() + $('#umnhf-f').height() + 60;
        $('.centennial__timeline--nav-wrapper').sticky({
          topSpacing: top,
          bottomSpacing: bottom,
          zIndex: 99999
        });
      }

      function highlightTimeline() {
        var found = false;
        var highlightId = null;
        $.each($('.centennial__timeline--story-group-wrapper'), function () {
          if (!found) {
            var distance = $(this).offset().top - $(window).scrollTop();
            if (distance > 100) {
              found = true;
            } else {
              highlightId = $(this).attr('id');
            }
          }
        });
        if (highlightId != null) {
          var $navLi = $('.centennial__timeline--nav-item[data-nav-ref="' + highlightId + '"]');
          var $navUl = $('.centennial__timeline--nav');
          var currentScroll = $navUl.scrollLeft();
          var goToScroll = $navLi.position().left - $('.centennial__timeline--nav-item').first().position().left;
          var $anchor = $navLi.find('a');
          if (!$anchor.hasClass('active-anchor')) {
            $navUl.animate({
              scrollLeft: goToScroll
            });
            $('.centennial__timeline--nav-item-anchor').removeClass('active-anchor');
            $anchor.addClass('active-anchor');
          }
        } else {
          $('.centennial__timeline--nav-item-anchor').first().addClass('active-anchor');
        }
      }
    }
  }

  })(jQuery, Drupal);