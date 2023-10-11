/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal, window) {
  "use strict";

  Drupal.behaviors.sticky_menu__collapsed_trigger = {
    attach: function (context) {
      let trigger = document.querySelectorAll(".sticky-menu__collapsed-trigger", context);
      Array.prototype.forEach.call(trigger, function (el) {
        el.addEventListener("click", function () {
          this.setAttribute(
            "aria-expanded",
            this.getAttribute("aria-expanded") === "true" ? "false" : "true"
          );
        });
      });
    },
  };

  Drupal.behaviors.inner_menu__collapsed_trigger = {
    attach: function (context) {
      let trigger = document.querySelectorAll(".inner-menu__collapsed-trigger", context);
      Array.prototype.forEach.call(trigger, function (el) {
        el.addEventListener("click", function () {
          this.setAttribute(
            "aria-expanded",
            this.getAttribute("aria-expanded") === "true" ? "false" : "true"
          );
        });
      });
    },
  };
})(jQuery, Drupal, window);
