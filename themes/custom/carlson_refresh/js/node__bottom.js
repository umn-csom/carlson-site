/**
 * @file
 * Node bottom.
 *
 */
(function ($, Drupal) {
  "use strict";

  // Add class to the node__bottom__col that is not empty.
  $(".node__bottom").each(function () {
    if ($(this).find(".node__bottom__col:empty").length > 0) {
      $(this)
        .find(".node__bottom__col:not(:empty)")
        .addClass("node__bottom__col--single");
    }
  });

})(jQuery, Drupal);
