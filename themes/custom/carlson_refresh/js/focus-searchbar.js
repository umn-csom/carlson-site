(function ($, Drupal) {
  Drupal.behaviors.focusSearchbar = {
    attach: function (context, settings) {
      const lastItem = $("#navbar-primary .navbar-nav > .nav-item:last-child");
      let clickedByMouse = false;

      lastItem.on("click mouseup mousedown touchstart", function (e) {
        const inputSearch = this.querySelector("input.gsc-input");
        clickedByMouse = true;
        setTimeout(function () {
          if (clickedByMouse) {
            inputSearch.focus();
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
