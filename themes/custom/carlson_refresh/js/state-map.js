// Reset SVG animation upon first intersect.
const svgElement = document.querySelector(".csom-state-map__svg");
function restartSvgAnimation() {
  if (!svgElement.classList.contains("first-view")) {
    svgElement.setCurrentTime(0);
    svgElement.classList.add("first-view");
  }
}
const svgObserver = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        restartSvgAnimation();
      }
    });
  },
  { threshold: [0.0, 0.85] }
);

if (svgElement) {
  svgObserver.observe(svgElement);
}
