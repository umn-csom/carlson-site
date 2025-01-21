(function (Drupal) {
  Drupal.behaviors.addExternalLinkAccessibility = {
    attach: function (context) {
      // Find all links with target="_blank"
      document
        .querySelectorAll('a[target="_blank"]', context)
        .forEach((link, index) => {
          // Skip if already processed.
          if (
            link.querySelector(".external-link-icon") ||
            link.querySelector(".external-link-text")
          ) {
            return;
          }
          link.insertAdjacentHTML(
            "beforeend",
            `<svg class="external-link-icon" viewBox="0 0 24 24" style="width:.8em;height:.8em;margin-left:.2em;vertical-align:middle;"  role="presentation" focusable="false">
              <path fill="currentColor" d="M10 3H3a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-7h-2v6H4V6h6V4zm11-3h-6a1 1 0 1 0 0 2h3.59L10 10.59a1 1 0 0 0 1.41 1.41L20 4.41V8a1 1 0 1 0 2 0V1a1 1 0 0 0-1-1z"></path>
            </svg>
            <span class="external-link-text sr-only">
              (this link opens in a new browser window or tab)
            </span>`
          );
        });
    },
  };
})(Drupal);
