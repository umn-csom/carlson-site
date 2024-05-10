(function (Drupal) {
Drupal.behaviors.multiColumnTextList = {
  attach: function () {
    function handle_resize_and_scroll(el) {
      // Check scrollability against the original client height defined in CSS.
      var originalClientHeight = el.clientHeight;
      if (!el.dataset.originalClientHeight) {
        el.dataset.originalClientHeight = originalClientHeight;
      } else {
        originalClientHeight = el.dataset.originalClientHeight;
      }

      // The list should only be scrollable when the height is more than 2x
      // the original client height defined in CSS.
      const isFullyVisible = el.scrollHeight < originalClientHeight * 2;
      el.closest(".csom-multicol-list").classList.toggle(
        "is-fully-visible",
        isFullyVisible
      );

      const isScrollable = el.scrollHeight > el.clientHeight && !isFullyVisible;

      // If element is not scrollable, remove classes.
      if (!isScrollable) {
        el.classList.add("is-scrolled-to-bottom", "is-scrolled-to-top");
        return;
      }

      // Otherwise, the element is overflowing! Now we just need to find out
      // which direction it is overflowing to (can be both). One pixel is added
      // to the height to account for non-integer heights.
      const isScrolledToBottom =
        el.scrollHeight < el.clientHeight + el.scrollTop + 1;
      const isScrolledToTop = isScrolledToBottom ? false : el.scrollTop === 0;
      el.classList.toggle("is-scrolled-to-bottom", isScrolledToBottom);
      el.classList.toggle("is-scrolled-to-top", isScrolledToTop);
    }

    function handle_all_lists() {
      const lists = document.querySelectorAll(".csom-multicol-list ul");
      lists.forEach((el) => handle_resize_and_scroll(el));
    }

    // Refresh after resize and orientation changes.
    window.addEventListener("resize", handle_all_lists);
    window.addEventListener("orientationchange", handle_all_lists);

    // Listen for scroll changes within each list.
    const lists = document.querySelectorAll(".csom-multicol-list ul");
    lists.forEach((el) =>
      el.addEventListener("scroll", (event) => {
        handle_resize_and_scroll(event.currentTarget);
      })
    );

    // Process all lists at pageload.
    handle_all_lists();
  }
};

})(Drupal);
