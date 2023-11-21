
(function (Drupal, $) {
  Drupal.behaviors.figureWithAccessibleDataTable = {
    attach: function () {
      const $figure = $(".csom-figure-with-accessible-datatable");
      const $button = $figure.find("button.sr-only");
      const $dataTable = $figure.find("table.sr-only");
      if ($button.length && $dataTable.length) {
        $button.on("click", function () {
          const $thisButton = $(this);
          $dataTable.removeClass("sr-only");
          $dataTable.attr("tabindex", 0).focus();
          $thisButton.detach();
        });
      }
    },
  };
})(Drupal, jQuery);


