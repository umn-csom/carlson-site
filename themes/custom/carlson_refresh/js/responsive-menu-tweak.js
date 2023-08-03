/*!
 * Adapted from https://github.umn.edu/drupalthemes/folwell/blob/9-dev/js/responsive-menu-tweak.js
 */
(function (Drupal, $) {
  Drupal.behaviors.responsiveMenuTweak = {
    attach: function () {
      var mm_menu = $(".mm-menu"),
        gsc_search = $(".umn-search-form").clone()
          .addClass('mm-navbar__search'),
        branding = $(".site-branding__logo--mobile").clone()
          .wrap('<div class="mm-navbar__logo" />').parent()
          .wrap('<div class="mm-navbar__branding" />').parent()
          .append(
            '<a class="mm-btn--close" id="close-nav" href="#mm-0" aria-label="close menu">&times;</a>'
          );
      if (!mm_menu.hasClass("mm-menu--csm-customized")) {
        mm_menu
          .prepend(gsc_search)
          .prepend(branding)
          .addClass("mm-menu--csm-customized");
      }

      $(".mm-btn_next").attr("aria-haspopup", "true");
      $(".mm-tabstart").attr("aria-label", "tab start button");
      $(".mm-tabend").attr("aria-label", "tab end button");

      $("#toggle-icon").click(function () {
        $("section#featured").attr("tabindex", "-1");
        $("main").attr("tabindex", "-1");
      });
      $("#close-nav").click(function () {
        $("section#featured").attr("tabindex", "0");
        $("main").attr("tabindex", "0");
      });
    },
  };
})(Drupal, jQuery);
