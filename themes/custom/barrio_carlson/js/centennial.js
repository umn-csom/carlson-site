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
        var isDesktop = (($(window).width() >= 768) ? true : false);
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
          var currentScroll = 0;
          var goToScroll = 0;
          if (isDesktop) {
            currentScroll = $navUl.scrollLeft();
            goToScroll = $navLi.position().left - $('.centennial__timeline--nav-item').first().position().left;
          } else {
            currentScroll = $navUl.scrollTop();
            goToScroll = $navLi.position().top - $('.centennial__timeline--nav-item').first().position().top;
            console.log(currentScroll, 'to', goToScroll);
          }
          var $anchor = $navLi.find('a');
          if (!$anchor.hasClass('active-anchor')) {
            if (isDesktop) {
              $navUl.animate({
                scrollLeft: goToScroll
              });
            } else {
              $navUl.animate({
                scrollTop: goToScroll
              });
              console.log('scrolled to ' + $navUl.scrollTop(), 'meant to go to ' + goToScroll);
            }
            $('.centennial__timeline--nav-item-anchor').removeClass('active-anchor');
            $anchor.addClass('active-anchor');
          }
        } else {
          $('.centennial__timeline--nav-item-anchor').first().addClass('active-anchor');
        }
      }

      $('.centennial__timeline--nav-item-anchor').click(function (event) {
        event.preventDefault();
        var isDesktop = (($(window).width() >= 768) ? true : false);
        if (!isDesktop && !$('.centennial__timeline--nav-container').hasClass('choose-year')) {
          $('.centennial__timeline--nav-container').addClass('choose-year');
        } else {
          $('.centennial__timeline--nav-container').removeClass('choose-year');
          if ($('.carlson-header').is(':visible')) {
            $('.carlson-header').hide();
          }
          $('html, body').animate({
            scrollTop: $(this.hash).offset().top
          }, 1000, function () {
            $(this.hash).focus();
          });
        }
      });
    }
  }

  })(jQuery, Drupal);