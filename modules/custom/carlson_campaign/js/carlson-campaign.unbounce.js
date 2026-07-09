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
        // Use a labeled region instead of the page-level banner landmark so
        // injected promotional content does not create extra banner landmarks.
        stickyBar.setAttribute('role', 'region');
        if (!stickyBar.hasAttribute('aria-label')) {
          stickyBar.setAttribute('aria-label', 'Campaign promotion');
        }
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
