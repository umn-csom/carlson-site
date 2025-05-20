/**
 * @file
 * Navigation dropdowns.
 */
(function ($, Drupal) {
  "use strict";
  // Execute code when the DOM is fully loaded
  $(document).ready(function () {
    // Add click event handler to dropdown toggle buttons
    $(".dropdown .dropdown-toggle").on("click", function (e) {
      // Store reference to the clicked element
      var $this = $(this),
        shouldExpand = $this.attr("aria-expanded") !== "true";

      // Toggle active class on the clicked dropdown toggle
      $this.attr("aria-expanded", shouldExpand);

      // Find the parent dropdown menu container
      var $parent = $this.closest(".dropdown");

      var openSiblings = $this.parent().siblings(":has([aria-expanded='true'])");
      // Close any other open dropdowns at the same level and lower.
      if (openSiblings.length > 0) {
        openSiblings.each(function () {
          var $sibling = $(this);
          $sibling.removeClass("show");
          // Remove "show" class from any open dropdown menus
          $sibling.find(".show").removeClass("show");
          // Remove aria-expanded from the toggle button
          $sibling.find(".dropdown-toggle").attr("aria-expanded", "false");
        });
      }

      // Toggle the display of the submenu
      $this.next(".dropdown-menu").toggleClass("show");

      // Toggle the class on the parent list item
      $this.parent("li").toggleClass("show");

      // When a parent dropdown is closed, clean up all child dropdowns
      $this
        .parents("li.dropdown.show")
        .on("hidden.bs.dropdown", function (e) {
          // Remove "show" class from any open dropdown menus
          $(".dropdown-menu .show", e.target).removeClass("show");
          // Remove aria-expanded from the toggle button
          $(".dropdown-toggle", e.target).attr("aria-expanded", "false");
        });

      // Position nested dropdown menus correctly for submenu flyouts.
      if (!$parent.parent().hasClass("site-nav__ul")) {
        // Position the submenu to the right of its parent
        $this
          .next()
          .css({ top: $this[0].offsetTop, left: $parent.outerWidth() - 4 });
      }

      // Prevent default link behavior
      return false;
    });
  });
})(jQuery, Drupal);
