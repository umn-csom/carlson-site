/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal, window) {
  "use strict";

  Drupal.behaviors.sticky_menu = {
    attach: function (context) {
      let $viewport = $(window),
        $navbar_sentinel = $("#sticky-top-sentinel", context),
        $sidebar = $(".sticky-sidebar", context),
        $navbar = $(".sticky-top", context),
        $menu = $(".sticky-menu", context),
        options = {
          root: null,
          rootMargin: "0px",
          threshold: 0.1,
        },
        viewportObserver = new IntersectionObserver(checkStickySize, options);

      // Add sticky at page load.
      checkStickySize();

      // Adjust stickiness on viewport resize.
      $viewport.resize(checkStickySize);
      $viewport.on("drupalViewportOffsetChange", checkStickySize());

      // Adjust sticky-menu offset when sticky-top becomes sticky.
      viewportObserver.observe($navbar_sentinel[0]);

      function checkStickySize() {
        if ($viewport.width() >= 992 && $sidebar.height() > $menu.height()) {
          $menu.addClass("is-stuck");
          let
            previous_top_offset = $menu.css("top"),
            new_top_offset = $navbar.offset().top + $navbar.outerHeight(true);
          if (previous_top_offset != new_top_offset + "px") {
            $menu.css({ top: new_top_offset });
          }
        } else {
          $menu.attr("style", "");
          $menu.removeClass("is-stuck");
        }
      }
    },
  };
})(jQuery, Drupal, window);
