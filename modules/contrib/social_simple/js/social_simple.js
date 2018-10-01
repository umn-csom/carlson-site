/**
 * @file
 */

(function ($) {
    // 'use strict';.
    Drupal.behaviors.social_simple = {
        attach: function(context, settings) {
            // Your code.
          if ($(window).width() > 500) {
              $(".social-buttons-links a").click(function(e){
                  e.preventDefault();
                  var h = $(this).data("popup-height"),
                      w = $(this).data("popup-width");

                  var dualScreenLeft = window.screenLeft != undefined ? window.screenLeft : screen.left;
                  var dualScreenTop = window.screenTop != undefined ? window.screenTop : screen.top;

                  var width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
                  var height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;

                  var left = ((width / 2) - (w / 2)) + dualScreenLeft;
                  var top = ((height / 2) - (h / 2)) + dualScreenTop;

                  window.open(
                      $(this).attr("href"),
                      "share",
                      "top=" + top + ",left=" + left + ",width=" + w + ",height=" + h
                  );
              });
          }

        }
  };

}(jQuery));
