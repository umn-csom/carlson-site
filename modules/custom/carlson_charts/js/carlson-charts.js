/**
 * @file
 * Alter Highcharts charts settings.
 */
(function (Drupal, once) {
  "use strict";
  Drupal.carlson_charts = Drupal.carlson_charts || {};
  Drupal.carlson_charts.highchartsTooltipFormatter = function () {
    const y = this.y || "";
    const total = this.total || 0;
    const percentage = this.percentage ? Math.round(this.percentage) : 0;
    const name = this.point.name || this.series.name || "";
    const has_accurate_y_value = (total == 100);
    const value = has_accurate_y_value ? y : percentage;
    if (has_accurate_y_value) {
      return `<span class="highcharts-tooltip__name">${name}</span><br> <span class="highcharts-tooltip__value">${value}%</span>`;
    }
    return `<span class="highcharts-tooltip__name">${name}</span><br> <span class="highcharts-tooltip__value">${value}%</span> <span class="highcharts-tooltip__description">(${y} of ${total})</span>`;
  };
  Drupal.carlson_charts.highchartsLegendLabelFormatter = function () {
    const y = this.y || false;
    const total = this.total || false;
    const percentage = this.percentage || false;
    const name = this.name || false;
    const has_accurate_y_value = y && total && total < 99.0 && total < 101.0;
    const value = has_accurate_y_value ? y : percentage ? Math.round(percentage) : false;

    if (!name || !value) {
      console.log("legend label has missing name or value");
      console.log(Object.keys(this));
      console.log(this);
      console.log("---------------------");
    }
    if (value) {
      return `<strong>${value}%</strong> - ${name}`;
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
              data.tooltip.formatter =
                Drupal.carlson_charts.highchartsTooltipFormatter;
              data.legend.labelFormatter =
                Drupal.carlson_charts.highchartsLegendLabelFormatter;
              if (data.chart.type == "pie") {
                data.plotOptions.pie.shadow = false;
                data.plotOptions.pie.point = {
                  events: {
                    mouseOver: function (e) {
                      this.originalRadius = this.graphic.r;
                      this.graphic.animate(
                        {
                          r: this.originalRadius * 1.03,
                        },
                        200
                      );
                    },
                    mouseOut: function (e) {
                      this.graphic.animate(
                        {
                          r: this.originalRadius,
                        },
                        200
                      );
                    },
                  },
                };
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
