(function ($, Drupal) {
  "use strict";
  Drupal.behaviors.focusSearchbar = {
    attach: function (context, settings) {
      const lastItem = $(".site-nav__pl-btn--search-and-more");
      let clickedByMouse = false;

      lastItem.on("click mouseup mousedown touchstart", function (e) {
        // Find the dropdown directly using closest
        const dropdown = this.closest('.dropdown');
        if (!dropdown) {
          console.warn('Could not find .dropdown element');
          return;
        }

        const inputSearch = dropdown.querySelector("input.gsc-input");
        if (!inputSearch) {
          console.warn('Could not find search input in mega menu');
          return;
        }

        clickedByMouse = true;
        setTimeout(function () {
          if (clickedByMouse && inputSearch) {
            inputSearch.focus();
            clickedByMouse = false;
          }
        }, 100);
      });

      lastItem.on("keydown keyup", function (e) {
        if (e.key === "Enter" || (e.key === " " && clickedByMouse)) {
          e.stopPropagation();
          clickedByMouse = false;
        }
      });
    },
  };
})(jQuery, Drupal);
