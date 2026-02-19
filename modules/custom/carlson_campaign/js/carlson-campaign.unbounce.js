/**
 * @file
 * Improves accessibility for Unbounce embeds.
 *
 * Unbounce markup is often injected asynchronously by third-party scripts, so
 * we poll briefly after page load and then stop to avoid a long-running timer.
 */

document.addEventListener('DOMContentLoaded', function () {
  let executionCount = 0;
  const maxExecutions = 10;

  const checkExist = setInterval(function () {
    const stickyBars = document.querySelectorAll('.ub-emb-container');
    executionCount++;

    if (stickyBars.length) {
      stickyBars.forEach((stickyBar) => {
        // Landmark role helps assistive tech identify campaign banner content.
        stickyBar.setAttribute('role', 'banner');
      });
      // Stop once we successfully updated injected containers.
      clearInterval(checkExist);
    }

    if (executionCount >= maxExecutions) {
      // Safety stop if Unbounce never loads on this page.
      clearInterval(checkExist);
    }
  }, 2000);
});
