/**
 * @file
 * Alter Highcharts charts settings.
 */
(function (Drupal, once) {
  "use strict";
  Drupal.carlson_charts = Drupal.carlson_charts || {};
  Drupal.carlson_charts.highchartsTooltipFormatter = function () {
    const y = this.y || "";
    const total = this.total || false;
    const percentage = this.percentage ? Math.round(this.percentage) : false;
    const category = this.point.category ? `${this.point.category}: ` : "";
    const name = this.point.name || this.series.name || "";
    const y_is_a_percentage = y && (total == 100);
    const value = percentage
      ? `${percentage}%`
      : y_is_a_percentage
      ? `${y}%`
      : y;
    if (percentage && y && total && total !== 100) {
      return `<span class="highcharts-tooltip__bullet" style="color:${this.color}">●</span> <span class="highcharts-tooltip__name">${name}</span><br> <span class="highcharts-tooltip__value">${category}${value}</span> <span class="highcharts-tooltip__description">(${y} of ${total})</span>`;
    }
    return `<span class="highcharts-tooltip__bullet" style="color:${this.color}">●</span> <span class="highcharts-tooltip__name">${name}</span><br> <span class="highcharts-tooltip__value">${category}${value}</span>`;
  };

  Drupal.carlson_charts.highchartsLegendLabelFormatter = function () {
    const y = this.y || "";
    const total = this.total || false;
    const percentage = this.percentage ? Math.round(this.percentage) : false;
    const name = this.name || false;
    const y_is_a_percentage = y && total == 100;
    const value = percentage
      ? `${percentage}%`
      : y_is_a_percentage
      ? `${y}%`
      : y;
    if (value) {
      return `<strong>${value}</strong> - ${name}`;
    }
    return name;
  }

  Drupal.behaviors.carlsonChartsHighchartsAlterations = {
    attach: function (context) {
      once("charts-highchart-carlson", ".charts-highchart", context).forEach(
        function (el) {
          el.addEventListener(
            "drupalChartsConfigsInitialization",
            function (e) {
              let data = e.detail;
              const id = data.drupalChartDivId;
              console.log(data.title.text, data.chart.type);
              console.log(data);
              if (data.chart.type == "pie") {
                data.legend.labelFormatter =
                  Drupal.carlson_charts.highchartsLegendLabelFormatter;
                data.tooltip.formatter =
                  Drupal.carlson_charts.highchartsTooltipFormatter;
                data.plotOptions.pie.shadow = false;
                console.log("innerSize", data.series[0].innerSize);
                // Adjust the donut size to our preference. The default
                // innerSize value is 40% when using the 'Donut' preset.
                // Otherwise, this is undefined when using the 'Pie' preset.
                if (data.series[0].innerSize) {
                  data.series[0].innerSize = "66%";
                }
                data.series[0].showInLegend = true;
                data.series[0].dataLabels = {
                  enabled: false,
                };
                data.series[0].states = {
                  hover: {
                    halo: false,
                  },
                };
                data.responsive = {
                  rules: [
                    {
                      condition: {
                        maxWidth: 500,
                      },
                      chartOptions: {
                        showInLegend: true,
                        dataLabels: {
                          enabled: false,
                        },
                        legend: {
                          align: "center",
                          verticalAlign: "bottom",
                          layout: "vertical",
                        },
                      },
                    },
                  ],
                };
              }
              Drupal.Charts.Contents.update(id, data);
            }
          );
        }
      );
    },
  };
})(Drupal, once);
