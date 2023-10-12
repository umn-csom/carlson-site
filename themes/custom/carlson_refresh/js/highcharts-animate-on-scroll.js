var myHighcharts = typeof myHighcharts === "undefined" ? [] : myHighcharts;
(function (Drupal, myHighcharts) {
  Drupal.behaviors.highchartsAnimateOnLoad = {
    attach: function () {
      let chartContainers = [];
      myHighcharts.forEach(function (item) {
        let chartId = Object.keys(item)[0];
        chartContainers.push(document.getElementById(chartId));
      });

      function startChartAnimation(chartId, chartOptions) {
        if (chartOptions) {
          const chart = document.getElementById(chartId);
          // Ensure animation runs only once.
          if (!chart.getAttribute("data-highcharts-chart")) {
            Highcharts.chart(chartId, chartOptions);
          }
        }
      }

      function handleChartIntersection(entries, observer) {
        entries.forEach(function (entry) {
          let chartContainer = entry.target;

          if (entry.isIntersecting) {
            let chartId = entry.target.id;
            let chart = myHighcharts.find(
              (chart) => Object.keys(chart)[0] === chartId
            );
            let chartOptions = chart[chartId];
            startChartAnimation(chartId, chartOptions);
            observer.observe(entry.target);
          }
        });
      }

      let chartObservers = [];

      chartContainers.forEach(function (chartContainer) {
        let chartObserver = new IntersectionObserver(handleChartIntersection, {
          threshold: 0.5,
        });
        chartObservers.push(chartObserver);
        chartObserver.observe(chartContainer);
      });
    },
  };
})(Drupal, myHighcharts);
