(function (Drupal) {
  Drupal.behaviors.addExternalLinkAccessibility = {
    attach: function (context) {
      // Find all links with target="_blank"
      document
        .querySelectorAll('a[target="_blank"]', context)
        .forEach((link, index) => {
          // Ignore overlay anchors and any link that already exposes external cues.
          if (
            link.classList.contains('stretched-link') ||
            link.dataset.extlink === 'processed' ||
            link.querySelector(".icon") ||
            link.querySelector("img") ||
            link.querySelector("svg:not(.icon-cta-apply, .icon-cta-request, .icon-cta-attend, .icon-cta-play, .icon-cta-person") ||
            link.querySelector(".external-link-icon") ||
            link.querySelector(".external-link-text")
          ) {
            return;
          }
          link.insertAdjacentHTML(
            "beforeend",
            `<svg class="external-link-icon" aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="1.75" style="width:0.8em;height:0.8em;margin-left:0.2em;vertical-align:middle;"><path d="M16 11L16 16.5L2.5 16.5L2.5 3.5L8 3.5 M9 9.5L17.5 1.5 M11.5 1.5L17.5 1.5L17.5 7.5"/></svg><span class="external-link-text sr-only">(this link opens in a new browser window or tab)</span>`,
          );
        });
    },
  };
})(Drupal);
