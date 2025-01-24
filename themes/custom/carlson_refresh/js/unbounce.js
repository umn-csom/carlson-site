document.addEventListener('DOMContentLoaded', function() {
  // Initialize execution count and maximum executions
  let executionCount = 0;
  let maxExecutions = 10;

  // Set an interval to check for the Unbounce Sticky Bar every 2 seconds
  let checkExist = setInterval(function() {
    // Query for the sticky bar container
    let stickyBar = document.querySelector('.ub-emb-container');
    executionCount++;

    // If the sticky bar exists, set the role attribute and stop checking
    if (stickyBar) {
      stickyBar.setAttribute('role', 'banner');
      // Verify that the role attribute has been set successfully
      if (stickyBar.getAttribute('role') === 'banner') {
        clearInterval(checkExist);
      }
    }

    // Stop checking after the maximum number of executions
    if (executionCount >= maxExecutions) {
      clearInterval(checkExist);
    }
  }, 2000);
});