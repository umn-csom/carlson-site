(function ($, Drupal) {
  Drupal.behaviors.closeMegaMenu = {
    attach: function (context, settings) {
      $("#navbar-primary .dropdown--mega-menu").on("hide.bs.dropdown", function (e) {
        if (e.clickEvent && e.clickEvent.target) {
          var target = $(e.clickEvent.target);
          if (target.hasClass("show") || target.parents(".show").length) {
            return false;
          }
        }
      });

      $(document).keyup(function(e) {
        if (e.key === "Escape") {
          $('div.dropdown-menu--mega-menu').removeClass('show');
        }
      });
    },
  };
})(jQuery, Drupal);
