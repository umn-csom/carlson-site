document.addEventListener('DOMContentLoaded', function() {
  // Initialize execution count and maximum executions
  let executionCount = 0;
  let maxExecutions = 10;

  // Set an interval to check for the Unbounce Sticky Bar every 2 seconds
  let checkExist = setInterval(function() {
    let stickyBars = document.querySelectorAll('.ub-emb-container');
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