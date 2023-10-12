var myHighcharts = typeof myHighcharts === "undefined" ? [] : myHighcharts;
(function (Drupal, myHighcharts) {
  Drupal.behaviors.highchartsDonut = {
    attach: function () {
      myHighcharts.push({
        "highcharts-donut-legend-right": {
          chart: {
            type: "pie",
          },
          exporting: {
            enabled: false,
          },
          title: {
            text: "Source of Jobs Accepted",
          },
          yAxis: {
            title: {
              text: "Percentage of job sourcing activities",
            },
          },
          legend: {
            align: "right",
            verticalAlign: "middle",
            layout: "vertical",
            useHTML: true,
            className:
              "highcharts-legend--no-points highcharts-legend--no-background",
            labelFormatter: function () {
              return `<div class="text-color ff-sans-serif h4 font-weight-bolder m-0 p-0"><strong>${this.y}%</strong></div> ${this.name}`;
            },
          },
          plotOptions: {
            pie: {
              shadow: false,
              point: {
                events: {
                  mouseOver: function (e) {
                    this.originalRadius = this.graphic.r;
                    this.graphic.animate(
                      {
                        r: this.originalRadius * 1.07,
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
              },
            },
          },
          tooltip: {
            formatter: function () {
              return "<b>" + this.point.name + "</b>: " + this.y + " %";
            },
          },
          series: [
            {
              name: "Job source",
              data: [
                ["Graduate initiated job search", 60],
                ["School-facilitated recruiting", 33],
                ["Unknown", 7],
              ],
              size: "100%",
              innerSize: "66%",
              showInLegend: true,
              dataLabels: {
                enabled: false,
              },
              states: {
                hover: {
                  halo: false,
                },
              },
            },
          ],
          responsive: {
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
          },
        },
      });

      myHighcharts.push({
        "highcharts-donut-legend-below": {
          chart: {
            type: "pie",
          },
          exporting: {
            enabled: false,
          },
          title: {
            text: "Source of Jobs Accepted",
          },
          yAxis: {
            title: {
              text: "Percentage of job sourcing activities",
            },
          },
          legend: {
            align: "center",
            verticalAlign: "bottom",
            layout: "vertical",
          },
          plotOptions: {
            pie: {
              shadow: false,
              point: {
                events: {
                  mouseOver: function (e) {
                    this.originalRadius = this.graphic.r;
                    this.graphic.animate(
                      {
                        r: this.originalRadius * 1.07,
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
              },
            },
          },
          tooltip: {
            formatter: function () {
              return "<b>" + this.point.name + "</b>: " + this.y + " %";
            },
          },
          series: [
            {
              name: "Job source",
              data: [
                ["Graduate initiated job search", 60],
                ["School-facilitated recruiting", 33],
                ["Unknown", 7],
              ],
              size: "100%",
              innerSize: "66%",
              showInLegend: true,
              dataLabels: {
                enabled: false,
              },
              states: {
                hover: {
                  halo: false,
                },
              },
            },
          ],
        },
      });

      myHighcharts.push({
        "highcharts-donut-labels": {
          chart: {
            type: "pie",
          },
          exporting: {
            enabled: false,
          },
          title: {
            text: "Source of Jobs Accepted",
          },
          yAxis: {
            title: {
              text: "Percentage of job sourcing activities",
            },
          },
          plotOptions: {
            pie: {
              shadow: false,
              point: {
                events: {
                  mouseOver: function (e) {
                    this.originalRadius = this.graphic.r;
                    this.graphic.animate(
                      {
                        r: this.originalRadius * 1.07,
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
              },
            },
          },
          tooltip: {
            formatter: function () {
              return "<b>" + this.point.name + "</b>: " + this.y + " %";
            },
          },
          series: [
            {
              name: "Job source",
              data: [
                ["Graduate initiated job search", 60],
                ["School-facilitated recruiting", 33],
                ["Unknown", 7],
              ],
              size: "100%",
              innerSize: "66%",
              showInLegend: false,
              dataLabels: {
                enabled: true,
              },
              states: {
                hover: {
                  halo: false,
                },
              },
            },
          ],
          responsive: {
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
          },
        },
      });

      myHighcharts.push({
        "highcharts-donut-color-override": {
          chart: {
            type: "pie",
          },
          exporting: {
            enabled: false,
          },
          // Override default colors
          title: {
            text: "Donut chart title here",
          },
          yAxis: {
            title: {
              text: "Y axis title",
            },
          },
          legend: {
            align: "right",
            verticalAlign: "middle",
            layout: "vertical",
          },
          plotOptions: {
            pie: {
              shadow: false,
              point: {
                events: {
                  mouseOver: function (e) {
                    this.originalRadius = this.graphic.r;
                    this.graphic.animate(
                      {
                        r: this.originalRadius * 1.07,
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
              },
            },
          },
          tooltip: {
            formatter: function () {
              return "<b>" + this.point.name + "</b>: " + this.y + " %";
            },
          },
          series: [
            {
              name: "Job source",
              data: [
                ["First category 1", 30],
                ["Another category 2", 30],
                ["Yet another category 3 ", 30],
                ["Unknown", 10],
              ],
              size: "100%",
              innerSize: "66%",
              showInLegend: true,
              dataLabels: {
                enabled: false,
              },
              states: {
                hover: {
                  halo: false,
                },
              },
            },
          ],
          responsive: {
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
          },
        },
      });

      // Uncomment to test.
      // myHighcharts.forEach(function (item) {
      //   let chartId = Object.keys(item)[0];
      //   let chartOptions = item[chartId];
      //   Highcharts.chart(chartId, chartOptions);
      // });
    },
  };
})(Drupal, myHighcharts);
