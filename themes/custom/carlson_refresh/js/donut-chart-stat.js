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
  ".csm-donut-chart-stat:not(.animate)"
);

donutChartStats.forEach(function (container) {
  let observer = new IntersectionObserver(handleIntersectionDonutChart, {
    threshold: 0.5,
  });
  observer.observe(container);
});
