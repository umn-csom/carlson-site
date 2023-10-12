var myHighcharts = typeof myHighcharts === "undefined" ? [] : myHighcharts;
(function (Drupal, myHighcharts) {
  Drupal.behaviors.highchartsStatDonut = {
    attach: function () {
      myHighcharts.push({
        "highcharts-stat-donut-small": {
          exporting: {
            enabled: false,
          },
          chart: {
            type: "pie",
          },
          title: {
            text: "A large amount of text may be added here",
            align: "center",
            verticalAlign: "middle",
            useHTML: true,
          },
          subtitle: {
            //text: "do not use, this throws off the position of the centered title."
          },
          plotOptions: {
            pie: {
              shadow: false,
              center: ["50%", "50%"],
              allowPointSelect: true,
              cursor: "default",
              dataLabels: {
                enabled: false,
              },
              showInLegend: false,
              allowPointSelect: false,
              point: {
                shadow: false,
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
            valueSuffix: "%",
            pointFormat: "", // disables unneeded tooltip line
          },
          series: [
            {
              type: "pie",
              name: "budget",
              innerSize: "60%",
              data: [
                {
                  name: "In budget (75%)",
                  y: 75.0,
                },
                {
                  name: "Over budget (25%)",
                  y: 25.0,
                },
              ],
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
        "highcharts-stat-donut-medium": {
          exporting: {
            enabled: false,
          },
          chart: {
            type: "pie",
          },
          title: {
            text: "Over<br>budget<br>25%",
            align: "center",
            verticalAlign: "middle",
            useHTML: true,
          },
          subtitle: {
            //text: "do not use, this throws off the position of the centered title."
          },
          plotOptions: {
            pie: {
              shadow: false,
              center: ["50%", "50%"],
              allowPointSelect: true,
              cursor: "default",
              dataLabels: {
                enabled: false,
              },
              showInLegend: false,
              allowPointSelect: false,
              point: {
                shadow: false,
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
            valueSuffix: "%",
            pointFormat: "", // disables unneeded tooltip line
          },
          series: [
            {
              type: "pie",
              name: "budget",
              innerSize: "60%",
              data: [
                {
                  name: "In budget (75%)",
                  y: 75.0,
                },
                {
                  name: "Over budget (25%)",
                  y: 25.0,
                },
              ],
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
        "highcharts-stat-donut-large": {
          exporting: {
            enabled: false,
          },
          chart: {
            type: "pie",
          },
          title: {
            text: "90%",
            align: "center",
            verticalAlign: "middle",
            useHTML: true,
          },
          subtitle: {
            //text: "do not use, this throws off the position of the centered title."
          },
          plotOptions: {
            pie: {
              shadow: false,
              center: ["50%", "50%"],
              allowPointSelect: true,
              cursor: "default",
              dataLabels: {
                enabled: false,
              },
              showInLegend: false,
              allowPointSelect: false,
              point: {
                shadow: false,
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
            valueSuffix: "%",
            pointFormat: "", // disables unneeded tooltip line
          },
          series: [
            {
              type: "pie",
              name: "budget",
              innerSize: "60%",
              data: [
                {
                  name: "90%",
                  y: 90.0,
                },
                {
                  name: "10%",
                  y: 10.0,
                },
              ],
              states: {
                hover: {
                  halo: false,
                },
              },
            },
          ],
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
