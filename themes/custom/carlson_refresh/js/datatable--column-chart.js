(function (Drupal, $) {
  Drupal.behaviors.verticalStackedBarChart = {
    attach: function () {
      var table = $(".csom-datatable--column-chart");
      table.find("tbody tr > :last-child").each(function (index) {
        var data = $(this).text();
        $(this).parents("tr").css("height", data);
      });
    },
  };
})(Drupal, jQuery);
