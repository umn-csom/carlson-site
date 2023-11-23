
(function (Drupal, $) {
  Drupal.behaviors.statDonut = {
    attach: function () {
      // Reset CSS animation upon intersect.
      function handleIntersectionDonutChart(entries, observer) {
        entries.forEach(function (entry) {
          let element = entry.target;

          if (entry.isIntersecting) {
            element.classList.add("animate");
          }
        });
      }

      let donutChartStats = document.querySelectorAll(
        ".csom-stat-donut__circle:not(.animate)"
      );

      donutChartStats.forEach(function (container) {
        let observer = new IntersectionObserver(handleIntersectionDonutChart, {
          threshold: 0.5,
        });
        observer.observe(container);
      });
    },
  };
})(Drupal, jQuery);
