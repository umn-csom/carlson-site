(function (Drupal) {
  Drupal.behaviors.highchartsMap = {
    attach: function () {
      (async () => {
        const mapData = await fetch(
          "https://code.highcharts.com/mapdata/countries/us/us-all.topo.json"
        ).then((response) => response.json());

        const data2 = [
          // state, region
          // 1 - southeast
          // 2 - northeast
          // 3 - midwest
          // 4 - southwest
          // 5 - west
          ["Alabama", 1],
          ["Alaska", 5],
          ["Arizona", 4],
          ["Arkansas", 1],
          ["California", 5],
          ["Colorado", 4],
          ["Connecticut", 2],
          ["Delaware", 2],
          ["District of Columbia", 1],
          ["Florida", 1],
          ["Georgia", 1],
          ["Hawaii", 5],
          ["Idaho", 5],
          ["Illinois", 3],
          ["Indiana", 3],
          ["Iowa", 3],
          ["Kansas", 3],
          ["Kentucky", 1],
          ["Louisiana", 1],
          ["Maine", 2],
          ["Maryland", 2],
          ["Massachusetts", 2],
          ["Michigan", 3],
          ["Minnesota", 3],
          ["Mississippi", 1],
          ["Missouri", 3],
          ["Montana", 5],
          ["Nebraska", 3],
          ["Nevada", 5],
          ["New Hampshire", 2],
          ["New Jersey", 2],
          ["New Mexico", 4],
          ["New York", 2],
          ["North Carolina", 1],
          ["North Dakota", 3],
          ["Ohio", 3],
          ["Oklahoma", 4],
          ["Oregon", 5],
          ["Pennsylvania", 2],
          ["Rhode Island", 2],
          ["South Carolina", 1],
          ["South Dakota", 3],
          ["Tennessee", 1],
          ["Texas", 4],
          ["Utah", 5],
          ["Vermont", 2],
          ["Virginia", 2],
          ["Washington", 5],
          ["West Virginia", 2],
          ["Wisconsin", 3],
          ["Wyoming", 5],
        ];

        // Build the chart
        const chart = Highcharts.mapChart("highcharts-us-regional-map", {
          chart: {
            animation: false, // Disable animation, especially for zooming
          },

          exporting: {
            enabled: false,
          },

          accessibility: {
            description:
              "Complex map demo showing voting results for US states, where each state has a pie chart overlaid showing the vote distribution.",
          },

          colorAxis: {
            dataClasses: [
              {
                from: 1,
                to: 2,
                color: "#4d4d4a",
                name: "3% - Southeast",
              },
              {
                from: 2,
                to: 3,
                color: "#8e8e8e",
                name: "7% - Northeast",
              },
              {
                from: 3,
                to: 4,
                color: "#7A0019",
                name: "84% - Midwest",
              },
              {
                from: 4,
                to: 5,
                color: "#118dff",
                name: "2% - Southwest",
              },
              {
                from: 5,
                to: 6,
                color: "#fc3",
                name: "2% - West",
              },
            ],
          },

          mapNavigation: {
            enabled: false,
          },

          legend: {
            align: "center",
            verticalAlign: "bottom",
            layout: "vertical",
          },

          title: {
            text: "Where Students Accepted",
            align: "center",
          },

          series: [
            {
              mapData,
              data: data2,
              name: "States",
              accessibility: {
                point: {
                  descriptionFormatter(point) {
                    return point.name;
                  },
                },
              },
              borderColor: "#FFF",
              keys: ["id", "value"],
              joinBy: ["name", "id"],
              tooltip: {
                headerFormat: "",
                pointFormatter() {
                  return "<b>" + this.id + "</b>";
                },
              },
            },
          ],
        });
      })();
    },
  };
})(Drupal);
