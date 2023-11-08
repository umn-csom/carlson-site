(function (Drupal, $) {
  Drupal.behaviors.verticalStackedBarChart = {
    attach: function () {
      var table = $(".csm-vertical-stacked-bar-chart");
      table.find("tbody tr > :first-child").each(function (index) {
        var data = $(this).text();
        $(this).parents("tr").css("height", data);
      });
    },
  };
})(Drupal, jQuery);
