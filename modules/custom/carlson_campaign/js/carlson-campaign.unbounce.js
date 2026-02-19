/**
 * @file
 * Improves accessibility for Unbounce sticky bars.
 */

document.addEventListener('DOMContentLoaded', function () {
  let executionCount = 0;
  const maxExecutions = 10;

  const checkExist = setInterval(function () {
    const stickyBars = document.querySelectorAll('.ub-emb-container');
    executionCount++;

    if (stickyBars.length) {
      stickyBars.forEach((stickyBar) => {
        stickyBar.setAttribute('role', 'banner');
      });
      clearInterval(checkExist);
    }

    if (executionCount >= maxExecutions) {
      clearInterval(checkExist);
    }
  }, 2000);
});
