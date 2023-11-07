var myHighcharts = typeof myHighcharts === "undefined" ? [] : myHighcharts;
(function (Drupal, myHighcharts) {
  Drupal.behaviors.highchartsDoublePieChart = {
    attach: function () {
      if (!document.getElementById("highcharts-double-pie-chart")) {
        return;
      }
      myHighcharts.push({
        "highcharts-double-pie-chart": {
          exporting: {
            enabled: false,
          },
          chart: {
            plotBackgroundColor: null,
            plotBorderWidth: 0,
            plotShadow: false,
          },
          title: {
            text: "Class of 2023",
            align: "center",
            verticalAlign: "top",
          },
          tooltip: {
            pointFormat: "<b>{point.percentage:.0f}%</b>",
          },
          legend: {
            align: "right",
            verticalAlign: "middle",
            layout: "vertical",
          },
          plotOptions: {
            series: {
              animation: false,
              states: {
                hover: {
                  halo: false,
                },
              },
            },
            pie: {
              center: ["50%", "50%"],
              allowPointSelect: false,
              shadow: false,
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
          series: [
            {
              type: "pie",
              name: "Gender",
              size: "60%",
              innerSize: "0%",
              dataLabels: {
                enabled: true,
                distance: -40,
              },
              startAngle: -45,
              endAngle: -45,
              data: [
                ["Men", 55],
                ["Women", 40],
                ["", 5],
              ],
            },
            {
              type: "pie",
              name: "Demographics",
              size: "90%",
              innerSize: "70%",
              dataLabels: {
                enabled: true,
                format: "{point.name}: {point.y}%",
                distance: 15,
              },
              startAngle: 265,
              endAngle: 265,
              data: [
                ["International students", 33],
                ["Students of color", 36],
                ["All others", 61],
              ],
            },
          ],
        },
      });

      // myHighcharts.forEach(function (item) {
      //   let chartId = Object.keys(item)[0];
      //   let chartOptions = item[chartId];
      //   Highcharts.chart(chartId, chartOptions);
      // });
    },
  };
})(Drupal, myHighcharts);
